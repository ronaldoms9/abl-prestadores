<?php

namespace App\Actions\Desligamento;

use App\Actions\Contratos\CriarContrato;
use App\Actions\Contratos\GerarContrato;
use App\Actions\Equipes\EncerrarVinculo;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\ColaboradorEquipe;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use App\Models\Remuneracao;
use App\Support\Auditoria;
use App\Support\Competencias\CompetenciaFechada;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Desliga o colaborador. A data é o último dia de trabalho (inclusive): tudo
 * que estava aberto fecha no dia seguinte (fim exclusivo, como nas demais
 * vigências). Na mesma transação:
 * - gera o distrato pelo modelo, se houver contrato em vigor e o RH quiser;
 * - encerra a remuneração, os benefícios e as equipes abertos, e apaga o que
 *   estava agendado para depois da data;
 * - encerra o contrato e cancela os rascunhos pendentes (aditivos);
 * - muda o status para desligado, que bloqueia novos lançamentos.
 *
 * Tudo ou nada: se o distrato não puder ser gerado (falta dado no cadastro),
 * nada muda e a mensagem diz o que falta. Desligamento só até hoje: o futuro
 * não é agendado. Em competência fechada, recusa.
 */
class DesligarColaborador
{
    public function __construct(
        private readonly CompetenciaFechada $competencias,
        private readonly CriarContrato $criarContrato,
        private readonly GerarContrato $gerarContrato,
        private readonly EncerrarVinculo $encerrarVinculo,
        private readonly PreviaDesligamento $previa,
    ) {
    }

    /**
     * @return array{colaborador: Colaborador, distrato: ?Contrato, previa: array<string, mixed>, avisos: list<string>}
     */
    public function executar(
        Colaborador $colaborador,
        CarbonInterface $data,
        string $motivo,
        ?string $observacao = null,
        bool $gerarDistrato = true,
        ?ContratoModelo $modeloDistrato = null,
    ): array {
        $data = $data->copy()->startOfDay();
        $motivo = trim($motivo);

        if ($motivo === '') {
            throw ValidationException::withMessages(['motivo' => 'Informe o motivo do desligamento.']);
        }

        if ($data->gt(now()->startOfDay())) {
            throw ValidationException::withMessages(['data' => 'A data do desligamento não pode ser futura. Registre no dia em que ele acontece.']);
        }

        if ($this->competencias->fechada($data)) {
            throw ValidationException::withMessages(['data' => 'A competência de '.$data->format('m/Y').' já está fechada. Desligamento retroativo não é permitido.']);
        }

        return DB::transaction(function () use ($colaborador, $data, $motivo, $observacao, $gerarDistrato, $modeloDistrato) {
            $colaborador = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);
            $avisos = [];

            if ($colaborador->status === StatusColaborador::Desligado) {
                throw ValidationException::withMessages(['colaborador' => 'O colaborador já está desligado.']);
            }

            if ($colaborador->data_admissao !== null && $data->lt($colaborador->data_admissao)) {
                throw ValidationException::withMessages(['data' => 'A data do desligamento não pode ser anterior à admissão ('.$colaborador->data_admissao->format('d/m/Y').').']);
            }

            $previa = $this->previa->executar($colaborador, $data);

            // O distrato lê a data e o motivo do cadastro, então eles são gravados antes dele ser gerado.
            $colaborador->update([
                'data_desligamento' => $data,
                'motivo_desligamento' => $observacao !== null && trim($observacao) !== '' ? $motivo.' — '.trim($observacao) : $motivo,
            ]);

            $distrato = $gerarDistrato ? $this->distrato($colaborador, $data, $modeloDistrato, $avisos) : null;

            $dia = $data->copy()->addDay();
            $this->encerrarRemuneracao($colaborador, $data, $dia);
            $this->encerrarBeneficios($colaborador, $data, $dia);
            $this->encerrarEquipes($colaborador, $dia);
            $this->encerrarContratos($colaborador, $distrato);

            $colaborador->update(['status' => StatusColaborador::Desligado]);

            Auditoria::registrar('desligamento-registrado', $colaborador, ['data' => $data->toDateString(), 'motivo' => $motivo, 'distrato' => $distrato?->numero]);

            return ['colaborador' => $colaborador, 'distrato' => $distrato, 'previa' => $previa, 'avisos' => $avisos];
        });
    }

    /** @param  list<string>  $avisos */
    private function distrato(Colaborador $colaborador, CarbonInterface $data, ?ContratoModelo $modelo, array &$avisos): ?Contrato
    {
        $pai = Contrato::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('tipo', TipoContrato::Contrato->value)
            ->whereIn('status', [StatusContrato::Gerado->value, StatusContrato::Assinado->value])
            ->latest('id')
            ->first();

        if ($pai === null) {
            $avisos[] = 'O colaborador não tem contrato gerado ou assinado: o distrato não foi gerado.';

            return null;
        }

        $modelo ??= ContratoModelo::query()->ativos()->where('tipo', TipoContrato::Distrato->value)->orderBy('id')->first();
        if ($modelo === null || $modelo->tipo !== TipoContrato::Distrato || ! $modelo->ativo) {
            throw ValidationException::withMessages(['modelo_distrato' => 'Não há modelo de distrato ativo. Cadastre um em Contratos > Modelos ou desligue sem gerar o distrato.']);
        }

        $rascunho = $this->criarContrato->executar($colaborador, $modelo, $data, null, $pai);

        return $this->gerarContrato->executar($rascunho, $data);
    }

    private function encerrarRemuneracao(Colaborador $colaborador, CarbonInterface $data, CarbonInterface $dia): void
    {
        Remuneracao::query()->where('colaborador_id', $colaborador->id)->whereDate('vigencia_inicio', '>', $data->toDateString())->get()->each->delete();

        Remuneracao::query()->where('colaborador_id', $colaborador->id)
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>', $dia->toDateString()))
            ->get()->each->update(['vigencia_fim' => $dia]);
    }

    private function encerrarBeneficios(Colaborador $colaborador, CarbonInterface $data, CarbonInterface $dia): void
    {
        ColaboradorBeneficio::query()->where('colaborador_id', $colaborador->id)->whereDate('inicio', '>', $data->toDateString())->get()->each->delete();

        ColaboradorBeneficio::query()->where('colaborador_id', $colaborador->id)
            ->where(fn ($q) => $q->whereNull('fim')->orWhereDate('fim', '>', $dia->toDateString()))
            ->get()->each->update(['fim' => $dia]);
    }

    private function encerrarEquipes(Colaborador $colaborador, CarbonInterface $dia): void
    {
        foreach (ColaboradorEquipe::query()->where('colaborador_id', $colaborador->id)->vigentes()->get() as $vinculo) {
            // Vínculo que só começaria depois do desligamento não chegou a existir.
            $vinculo->inicio->gte($dia) ? $vinculo->delete() : $this->encerrarVinculo->executar($vinculo, $dia);
        }
    }

    private function encerrarContratos(Colaborador $colaborador, ?Contrato $distrato): void
    {
        Contrato::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('tipo', TipoContrato::Contrato->value)
            ->whereIn('status', [StatusContrato::Gerado->value, StatusContrato::Assinado->value])
            ->get()->each->update(['status' => StatusContrato::Encerrado]);

        // Aditivos pendentes de quem saiu não fazem mais sentido.
        Contrato::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('status', StatusContrato::Rascunho->value)
            ->when($distrato, fn ($q) => $q->where('id', '!=', $distrato->id))
            ->get()->each->delete();
    }
}
