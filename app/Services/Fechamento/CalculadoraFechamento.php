<?php

namespace App\Services\Fechamento;

use App\Actions\Remuneracoes\RemuneracaoProporcional;
use App\Enums\TipoCalculoBeneficio;
use App\Enums\TipoItemFechamento;
use App\Models\AjusteAvulso;
use App\Models\BeneficioValor;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\Remuneracao;
use App\Support\Dinheiro;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Apura o fechamento de um colaborador em uma competência, sem gravar nada
 * (ver docs/planos/entrega-4-fatia-6-calculo.md). Tudo em centavos inteiros.
 */
class CalculadoraFechamento
{
    public function __construct(
        private readonly RemuneracaoProporcional $proporcional,
        private readonly CalendarioTrabalho $calendario,
    ) {
    }

    /** Período em que o colaborador esteve ativo no mês, ou null se não esteve. @return array{0: Carbon, 1: Carbon}|null */
    public function periodoAtivo(Colaborador $colaborador, Competencia $competencia): ?array
    {
        if ($colaborador->data_admissao === null) {
            return null;
        }

        $primeiro = Carbon::createFromFormat('Y-m-d', $competencia->ano_mes.'-01')->startOfDay();
        $ultimo = $primeiro->copy()->endOfMonth()->startOfDay();
        $inicio = $colaborador->data_admissao->copy()->startOfDay()->max($primeiro);
        $fim = $colaborador->data_desligamento === null ? $ultimo : $colaborador->data_desligamento->copy()->startOfDay()->min($ultimo);

        return $fim->lt($inicio) ? null : [$inicio, $fim];
    }

    public function calcular(Colaborador $colaborador, Competencia $competencia): ?ResultadoFechamento
    {
        $periodo = $this->periodoAtivo($colaborador, $competencia);
        if ($periodo === null) {
            return null;
        }

        [$inicio, $fim] = $periodo;
        $diasDeTrabalho = $this->calendario->diasDeTrabalho($colaborador, $inicio, $fim);
        $avisos = [];

        $itens = [$this->remuneracao($colaborador, $inicio, $fim, $avisos)];
        array_push($itens, ...$this->beneficios($colaborador, $inicio, $fim, $diasDeTrabalho, $avisos));
        array_push($itens, ...$this->ajustes($colaborador, $competencia));

        return new ResultadoFechamento($inicio, $fim, (int) $inicio->diffInDays($fim) + 1, count($diasDeTrabalho), $itens, $avisos);
    }

    /** @param  list<string>  $avisos */
    private function remuneracao(Colaborador $colaborador, Carbon $inicio, Carbon $fim, array &$avisos): ItemCalculado
    {
        $total = $this->proporcional->valor($colaborador, $inicio, $fim);

        $linhas = Remuneracao::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('vigencia_inicio', '<=', $fim)
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>', $inicio))
            ->orderBy('vigencia_inicio')->get();

        if ($linhas->isEmpty()) {
            $avisos[] = 'Sem remuneração vigente no período; o valor ficou zerado.';

            return new ItemCalculado(TipoItemFechamento::Remuneracao, 'Remuneração (sem valor vigente)', 1, 0, 0);
        }

        $trechos = $linhas->map(function (Remuneracao $r) use ($inicio, $fim) {
            $de = $r->vigencia_inicio->copy()->startOfDay()->max($inicio);
            $ate = $r->vigencia_fim === null ? $fim : $r->vigencia_fim->copy()->startOfDay()->subDay()->min($fim);

            return Dinheiro::formatar(Dinheiro::centavos($r->valor_mensal)).' de '.$de->format('d/m').' a '.$ate->format('d/m');
        });

        $diasMes = $inicio->copy()->startOfMonth()->daysInMonth;
        $dias = (int) $inicio->diffInDays($fim) + 1;
        $descricao = $dias === $diasMes && $linhas->count() === 1
            ? 'Remuneração mensal ('.Dinheiro::formatar(Dinheiro::centavos($linhas->first()->valor_mensal)).')'
            : 'Remuneração proporcional, '.$dias.' dia(s) ativo(s) de '.$inicio->format('d/m').' a '.$fim->format('d/m').' ('.$trechos->implode('; ').')';

        return new ItemCalculado(TipoItemFechamento::Remuneracao, $descricao, 1, $total, $total, $linhas->last());
    }

    /**
     * @param  array<string, true>  $diasDeTrabalho
     * @param  list<string>  $avisos
     * @return list<ItemCalculado>
     */
    private function beneficios(Colaborador $colaborador, Carbon $inicio, Carbon $fim, array $diasDeTrabalho, array &$avisos): array
    {
        $vinculos = ColaboradorBeneficio::query()
            ->with('beneficio')
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('inicio', '<=', $fim)
            ->where(fn ($q) => $q->whereNull('fim')->orWhereDate('fim', '>', $inicio))
            ->orderBy('inicio')->orderBy('id')->get();

        $itens = [];
        foreach ($vinculos as $vinculo) {
            array_push($itens, ...$this->itensDoVinculo($vinculo, $inicio, $fim, $diasDeTrabalho, $avisos));
        }

        return $itens;
    }

    /**
     * @param  array<string, true>  $diasDeTrabalho
     * @param  list<string>  $avisos
     * @return list<ItemCalculado>
     */
    private function itensDoVinculo(ColaboradorBeneficio $vinculo, Carbon $inicio, Carbon $fim, array $diasDeTrabalho, array &$avisos): array
    {
        $beneficio = $vinculo->beneficio;
        $de = $vinculo->inicio->copy()->startOfDay()->max($inicio);
        $ate = $vinculo->fim === null ? $fim : $vinculo->fim->copy()->startOfDay()->subDay()->min($fim);

        if ($ate->lt($de)) {
            return [];
        }

        $tabela = BeneficioValor::query()->where('beneficio_id', $beneficio->id)
            ->whereDate('vigencia_inicio', '<=', $ate)
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>', $de))
            ->get();

        $trechos = $this->trechosDeValor($vinculo, $tabela, $de, $ate);
        $itens = [];

        foreach ($trechos as $t) {
            if ($t['valor'] === null) {
                $avisos[] = "{$beneficio->nome}: sem valor na tabela entre {$t['de']->format('d/m')} e {$t['ate']->format('d/m')}; o trecho ficou zerado.";
                $itens[] = new ItemCalculado(TipoItemFechamento::Beneficio, "{$beneficio->nome} (sem valor vigente de {$t['de']->format('d/m')} a {$t['ate']->format('d/m')})", 0, 0, 0, $vinculo);

                continue;
            }

            $dias = (int) $t['de']->diffInDays($t['ate']) + 1;
            $periodo = "de {$t['de']->format('d/m')} a {$t['ate']->format('d/m')}";

            if ($beneficio->tipo_calculo === TipoCalculoBeneficio::PorDiaTrabalhado) {
                $trabalhados = $this->contarDias($diasDeTrabalho, $t['de'], $t['ate']);
                $itens[] = new ItemCalculado(
                    TipoItemFechamento::Beneficio,
                    "{$beneficio->nome}: {$trabalhados} dia(s) trabalhado(s) × ".Dinheiro::formatar($t['valor'])." {$periodo}",
                    $trabalhados, $t['valor'], $trabalhados * $t['valor'], $vinculo,
                );

                continue;
            }

            if (! $this->fixoProporcional()) {
                continue; // tratado abaixo: um único item cheio por vínculo
            }

            $itens[] = new ItemCalculado(
                TipoItemFechamento::Beneficio,
                "{$beneficio->nome}: ".Dinheiro::formatar($t['valor']).' mensal, '.($this->cobreOMes($t['de'], $t['ate']) ? 'mês inteiro' : "{$dias} dia(s) {$periodo}"),
                $dias, $t['valor'], $this->proporcional->proporcionar($t['valor'], $t['de'], $t['ate']), $vinculo,
            );
        }

        if ($beneficio->tipo_calculo === TipoCalculoBeneficio::FixoMensal && ! $this->fixoProporcional()) {
            $ultimo = collect($trechos)->last(fn ($t) => $t['valor'] !== null);
            if ($ultimo !== null) {
                $itens[] = new ItemCalculado(TipoItemFechamento::Beneficio, "{$beneficio->nome}: ".Dinheiro::formatar($ultimo['valor']).' mensal (valor cheio)', 1, $ultimo['valor'], $ultimo['valor'], $vinculo);
            }
        }

        return $itens;
    }

    /**
     * Divide [de, ate] em trechos de valor diário/mensal constante.
     *
     * @param  Collection<int, BeneficioValor>  $tabela
     * @return list<array{de: Carbon, ate: Carbon, valor: ?int}>
     */
    private function trechosDeValor(ColaboradorBeneficio $vinculo, Collection $tabela, Carbon $de, Carbon $ate): array
    {
        $trechos = [];

        for ($dia = $de->copy(); $dia->lte($ate); $dia->addDay()) {
            $valor = $this->valorNoDia($vinculo, $tabela, $dia);
            $ultimo = count($trechos) - 1;

            if ($ultimo >= 0 && $trechos[$ultimo]['valor'] === $valor) {
                $trechos[$ultimo]['ate'] = $dia->copy();
            } else {
                $trechos[] = ['de' => $dia->copy(), 'ate' => $dia->copy(), 'valor' => $valor];
            }
        }

        return $trechos;
    }

    /** @param  Collection<int, BeneficioValor>  $tabela */
    private function valorNoDia(ColaboradorBeneficio $vinculo, Collection $tabela, Carbon $dia): ?int
    {
        if ($vinculo->valor_personalizado !== null) {
            return Dinheiro::centavos($vinculo->valor_personalizado);
        }

        $linha = $tabela->first(fn (BeneficioValor $v) => $v->vigencia_inicio->copy()->startOfDay()->lte($dia)
            && ($v->vigencia_fim === null || $dia->lt($v->vigencia_fim->copy()->startOfDay())));

        return $linha === null ? null : Dinheiro::centavos($linha->valor);
    }

    /** @param  array<string, true>  $diasDeTrabalho */
    private function contarDias(array $diasDeTrabalho, Carbon $de, Carbon $ate): int
    {
        $n = 0;
        for ($dia = $de->copy(); $dia->lte($ate); $dia->addDay()) {
            $n += isset($diasDeTrabalho[$dia->toDateString()]) ? 1 : 0;
        }

        return $n;
    }

    private function cobreOMes(CarbonInterface $de, CarbonInterface $ate): bool
    {
        return $de->equalTo($de->copy()->startOfMonth()) && $ate->equalTo($ate->copy()->endOfMonth()->startOfDay());
    }

    private function fixoProporcional(): bool
    {
        return (bool) Configuracao::obter('calculo.beneficio_fixo_proporcional', true);
    }

    /** @return list<ItemCalculado> */
    private function ajustes(Colaborador $colaborador, Competencia $competencia): array
    {
        return AjusteAvulso::query()
            ->where('colaborador_id', $colaborador->id)
            ->where('competencia_id', $competencia->id)
            ->orderBy('id')->get()
            ->map(function (AjusteAvulso $a) {
                $centavos = Dinheiro::centavos($a->valor);
                $credito = $a->tipo->value === 'credito';

                return new ItemCalculado(
                    $credito ? TipoItemFechamento::Ajuste : TipoItemFechamento::Desconto,
                    ($credito ? 'Crédito: ' : 'Débito: ').$a->justificativa,
                    1, $credito ? $centavos : -$centavos, $credito ? $centavos : -$centavos, $a,
                );
            })->all();
    }
}
