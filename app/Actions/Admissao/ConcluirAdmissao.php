<?php

namespace App\Actions\Admissao;

use App\Actions\Beneficios\AplicarBeneficiosPadrao;
use App\Actions\Remuneracoes\DefinirRemuneracaoInicial;
use App\Enums\StatusColaborador;
use App\Models\AdmissaoChecklist;
use App\Models\Colaborador;
use App\Models\Remuneracao;
use App\Support\Auditoria;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Conclui a admissão: o colaborador passa a ativo, com a data de admissão, a
 * remuneração inicial e os benefícios padrão do cargo. Exige todos os itens
 * obrigatórios do checklist (os automáticos são reavaliados na hora).
 *
 * A remuneração inicial normalmente já foi definida antes, porque o contrato
 * precisa dela. Quem ainda não definiu pode passá-la aqui: ela é gravada com
 * vigência na data da admissão, antes de o checklist ser conferido. Tudo ou
 * nada: item pendente desfaz até a remuneração gravada agora.
 */
class ConcluirAdmissao
{
    public function __construct(
        private readonly SincronizarItensAutomaticos $sincronizar,
        private readonly DefinirRemuneracaoInicial $definirRemuneracao,
        private readonly AplicarBeneficiosPadrao $beneficiosPadrao,
    ) {
    }

    public function executar(Colaborador $colaborador, CarbonInterface $dataAdmissao, string|int|float|null $remuneracaoInicial = null): Colaborador
    {
        return DB::transaction(function () use ($colaborador, $dataAdmissao, $remuneracaoInicial) {
            $colaborador = Colaborador::query()->lockForUpdate()->findOrFail($colaborador->id);

            if ($colaborador->status !== StatusColaborador::PreAdmissao) {
                throw ValidationException::withMessages(['colaborador' => 'Só colaborador em pré-admissão pode ter a admissão concluída.']);
            }

            if (! AdmissaoChecklist::query()->where('colaborador_id', $colaborador->id)->exists()) {
                throw ValidationException::withMessages(['colaborador' => 'A admissão ainda não foi iniciada.']);
            }

            $inicio = $dataAdmissao->copy()->startOfDay();

            $temRemuneracao = Remuneracao::query()->where('colaborador_id', $colaborador->id)->exists();
            if (! $temRemuneracao && $remuneracaoInicial !== null && $remuneracaoInicial !== '') {
                $this->definirRemuneracao->executar($colaborador, $remuneracaoInicial, $inicio);
            }

            $pendentes = $this->sincronizar->executar($colaborador, $inicio)
                ->filter(fn (AdmissaoChecklist $l) => $l->obrigatorio && ! $l->concluido);

            if ($pendentes->isNotEmpty()) {
                throw ValidationException::withMessages(['checklist' => 'Itens obrigatórios pendentes: '.$pendentes->pluck('item')->implode('; ').'.']);
            }

            if (! Remuneracao::query()->where('colaborador_id', $colaborador->id)->exists()) {
                throw ValidationException::withMessages(['remuneracao_inicial' => 'Defina a remuneração do colaborador para concluir a admissão.']);
            }

            $colaborador->update(['status' => StatusColaborador::Ativo, 'data_admissao' => $inicio]);

            $beneficios = $this->beneficiosPadrao->executar($colaborador->refresh(), $inicio);

            Auditoria::registrar('admissao-concluida', $colaborador, ['data_admissao' => $inicio->toDateString(), 'beneficios_padrao' => $beneficios->count()]);

            return $colaborador;
        });
    }
}
