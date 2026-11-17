<?php

namespace App\Actions\Remuneracoes;

use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\Remuneracao;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Valor proporcional a um período (datas inclusive), mês a mês. É o mesmo
 * cálculo que a CalculadoraFechamento (E4) vai chamar; vale a remuneração
 * vigente em cada dia, então um reajuste no meio do mês divide o mês em trechos.
 *
 * Base (configuracoes[calculo.base_proporcional], padrão "corridos", a confirmar
 * com a ABL PRIME, spec 4.3):
 * - corridos: valor ÷ 30 × dias corridos ativos no mês, limitado ao valor mensal.
 *   Mês inteiro vale o valor cheio em qualquer mês (28, 29, 30 ou 31 dias; com
 *   reajuste no meio, cada trecho pesa dias ÷ dias do mês).
 * - dias_uteis: valor × dias úteis ativos ÷ dias úteis do mês, de segunda a
 *   sexta. Feriados e sábado trabalhado entram no calendário de trabalho da E4.
 *
 * Tudo em centavos inteiros, arredondando meio para cima uma vez por mês.
 */
class RemuneracaoProporcional
{
    public const BASE_CORRIDOS = 'corridos';

    public const BASE_DIAS_UTEIS = 'dias_uteis';

    public function base(): string
    {
        $base = Configuracao::obter('calculo.base_proporcional', self::BASE_CORRIDOS);

        return in_array($base, [self::BASE_CORRIDOS, self::BASE_DIAS_UTEIS], true) ? $base : self::BASE_CORRIDOS;
    }

    /** Remuneração do colaborador no período, conforme a vigente em cada dia. */
    public function valor(Colaborador $colaborador, CarbonInterface $inicio, CarbonInterface $fim): int
    {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->startOfDay();

        if ($fim->lt($inicio)) {
            return 0;
        }

        $linhas = Remuneracao::query()
            ->where('colaborador_id', $colaborador->id)
            ->whereDate('vigencia_inicio', '<=', $fim->toDateString())
            ->where(fn ($q) => $q->whereNull('vigencia_fim')->orWhereDate('vigencia_fim', '>', $inicio->toDateString()))
            ->get();

        $trechos = $linhas->map(fn (Remuneracao $r) => [
            'centavos' => (int) round(((float) $r->valor_mensal) * 100),
            'inicio' => $r->vigencia_inicio->copy()->startOfDay(),
            // Fim exclusivo da vigência: o último dia de uso é a véspera.
            'fim' => $r->vigencia_fim?->copy()->startOfDay()->subDay(),
        ])->all();

        return $this->somarMeses($inicio, $fim, $trechos);
    }

    /** Um valor mensal fixo (ex.: benefício fixo) proporcional ao período. */
    public function proporcionar(int $centavosMensais, CarbonInterface $inicio, CarbonInterface $fim): int
    {
        $inicio = $inicio->copy()->startOfDay();
        $fim = $fim->copy()->startOfDay();

        if ($fim->lt($inicio) || $centavosMensais <= 0) {
            return 0;
        }

        return $this->somarMeses($inicio, $fim, [['centavos' => $centavosMensais, 'inicio' => $inicio, 'fim' => null]]);
    }

    /**
     * @param  array<int, array{centavos: int, inicio: Carbon, fim: ?Carbon}>  $trechos
     */
    private function somarMeses(Carbon $inicio, Carbon $fim, array $trechos): int
    {
        $total = 0;
        $mes = $inicio->copy()->startOfMonth();

        while ($mes->lte($fim)) {
            $primeiro = $mes->copy();
            $ultimo = $mes->copy()->endOfMonth()->startOfDay();
            $de = $inicio->gt($primeiro) ? $inicio : $primeiro;
            $ate = $fim->lt($ultimo) ? $fim : $ultimo;
            $periodoCobreOMes = $de->equalTo($primeiro) && $ate->equalTo($ultimo);

            $total += $this->mes($trechos, $primeiro, $ultimo, $de, $ate, $periodoCobreOMes);
            $mes->addMonthNoOverflow();
        }

        return $total;
    }

    /** @param  array<int, array{centavos: int, inicio: Carbon, fim: ?Carbon}>  $trechos */
    private function mes(array $trechos, Carbon $primeiro, Carbon $ultimo, Carbon $de, Carbon $ate, bool $periodoCobreOMes): int
    {
        $uteis = $this->base() === self::BASE_DIAS_UTEIS;
        $numerador = 0;
        $maiorValor = 0;
        $diasCobertos = 0;

        foreach ($trechos as $trecho) {
            $segDe = $trecho['inicio']->gt($de) ? $trecho['inicio'] : $de;
            $segAte = $trecho['fim'] !== null && $trecho['fim']->lt($ate) ? $trecho['fim'] : $ate;

            if ($segAte->lt($segDe)) {
                continue;
            }

            $dias = $uteis ? $this->diasUteis($segDe, $segAte) : (int) $segDe->diffInDays($segAte) + 1;
            $diasCobertos += (int) $segDe->diffInDays($segAte) + 1;
            $numerador += $trecho['centavos'] * $dias;
            $maiorValor = max($maiorValor, $trecho['centavos']);
        }

        if ($numerador === 0) {
            return 0;
        }

        // Mês inteiro é o que o colaborador teve remuneração em todos os dias dele: vale o cheio em qualquer mês.
        $mesInteiro = $periodoCobreOMes && $diasCobertos === $primeiro->daysInMonth;

        // Denominador: dias úteis do mês; ou, em dias corridos, 30 (mês parcial) ou os dias do mês (mês inteiro).
        $denominador = $uteis ? max(1, $this->diasUteis($primeiro, $ultimo)) : ($mesInteiro ? $primeiro->daysInMonth : 30);
        $valor = intdiv(2 * $numerador + $denominador, 2 * $denominador);

        return $uteis ? $valor : min($valor, $maiorValor);
    }

    private function diasUteis(Carbon $de, Carbon $ate): int
    {
        $dias = 0;
        for ($dia = $de->copy(); $dia->lte($ate); $dia->addDay()) {
            if (! $dia->isWeekend()) {
                $dias++;
            }
        }

        return $dias;
    }
}
