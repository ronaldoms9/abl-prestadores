<?php

namespace Tests\Feature\Cadastros;

use App\Actions\Remuneracoes\RemuneracaoProporcional;
use App\Models\Colaborador;
use App\Models\Configuracao;
use App\Models\Remuneracao;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RemuneracaoProporcionalTest extends TestCase
{
    use RefreshDatabase;

    private function colaborador(string $valor = '3000', string $desde = '2026-01-01'): Colaborador
    {
        $c = Colaborador::factory()->create();
        Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => $valor, 'vigencia_inicio' => $desde]);

        return $c;
    }

    private function valor(Colaborador $c, string $de, string $ate): int
    {
        return app(RemuneracaoProporcional::class)->valor($c, Carbon::parse($de), Carbon::parse($ate));
    }

    public static function periodos(): array
    {
        return [
            'mês cheio de 31 dias' => ['2026-03-01', '2026-03-31', 300000],
            'mês cheio de 30 dias' => ['2026-04-01', '2026-04-30', 300000],
            'fevereiro cheio' => ['2026-02-01', '2026-02-28', 300000],
            'fevereiro bissexto cheio' => ['2028-02-01', '2028-02-29', 300000],
            'admissão no meio (16 a 31)' => ['2026-03-16', '2026-03-31', 160000],
            'admissão no dia 2 de mês de 31 dias vale o cheio' => ['2026-03-02', '2026-03-31', 300000],
            'desligamento no meio (1 a 10)' => ['2026-03-01', '2026-03-10', 100000],
            'um dia só' => ['2026-03-15', '2026-03-15', 10000],
            'fevereiro parcial (15 a 28)' => ['2026-02-15', '2026-02-28', 140000],
            'dois meses cheios' => ['2026-03-01', '2026-04-30', 600000],
            'meio de um mês ao meio do outro' => ['2026-03-16', '2026-04-15', 160000 + 150000],
        ];
    }

    #[DataProvider('periodos')]
    public function test_base_em_dias_corridos(string $de, string $ate, int $esperado): void
    {
        $this->assertSame($esperado, $this->valor($this->colaborador(desde: '2020-01-01'), $de, $ate));
    }

    public function test_reajuste_no_meio_do_mes_divide_o_mes_em_trechos_pelo_peso_dos_dias(): void
    {
        $c = $this->colaborador('3000', '2026-01-01');
        Remuneracao::where('colaborador_id', $c->id)->update(['vigencia_fim' => '2026-03-16']);
        Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => '3300', 'vigencia_inicio' => '2026-03-16']);

        // (3000 × 15 + 3300 × 16) ÷ 31 = 3154,84
        $this->assertSame(315484, $this->valor($c, '2026-03-01', '2026-03-31'));
        // Só depois do reajuste, parcial: 3300 ÷ 30 × 10
        $this->assertSame(110000, $this->valor($c, '2026-03-16', '2026-03-25'));
        // Só antes: 3000 ÷ 30 × 10
        $this->assertSame(100000, $this->valor($c, '2026-03-01', '2026-03-10'));
    }

    public function test_reajuste_no_meio_de_mes_parcial_usa_os_dois_valores_sobre_30(): void
    {
        $c = $this->colaborador('3000', '2026-01-01');
        Remuneracao::where('colaborador_id', $c->id)->update(['vigencia_fim' => '2026-03-16']);
        Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => '3300', 'vigencia_inicio' => '2026-03-16']);

        // 10 a 20 de março: 6 dias a 3000 (10 a 15) + 5 dias a 3300 (16 a 20) = (3000×6 + 3300×5) ÷ 30 = 1150
        $this->assertSame(115000, $this->valor($c, '2026-03-10', '2026-03-20'));
    }

    public function test_dias_sem_remuneracao_nao_valem_nada(): void
    {
        $c = $this->colaborador('3000', '2026-03-16');

        $this->assertSame(160000, $this->valor($c, '2026-03-01', '2026-03-31'), 'só 16 a 31 têm remuneração: 16 dias, parcial');
        $this->assertSame(0, $this->valor($c, '2026-02-01', '2026-02-28'));
        $this->assertSame(0, $this->valor(Colaborador::factory()->create(), '2026-03-01', '2026-03-31'));
    }

    public function test_remuneracao_encerrada_nao_vale_depois_do_fim_exclusivo(): void
    {
        $c = $this->colaborador('3000', '2026-01-01');
        Remuneracao::where('colaborador_id', $c->id)->update(['vigencia_fim' => '2026-03-11']);

        $this->assertSame(100000, $this->valor($c, '2026-03-01', '2026-03-31'), 'vale até o dia 10 (10 dias)');
    }

    public function test_periodo_invertido_vale_zero(): void
    {
        $this->assertSame(0, $this->valor($this->colaborador(), '2026-03-10', '2026-03-09'));
    }

    public function test_arredonda_meio_para_cima_uma_vez_por_mes(): void
    {
        $c = $this->colaborador('1000.00', '2020-01-01');

        // 1000 ÷ 30 × 7 = 233,3333 → 233,33
        $this->assertSame(23333, $this->valor($c, '2026-03-01', '2026-03-07'));
        // 1000 ÷ 30 × 8 = 266,6667 → 266,67
        $this->assertSame(26667, $this->valor($c, '2026-03-01', '2026-03-08'));
    }

    public function test_base_em_dias_uteis_pela_configuracao(): void
    {
        Configuracao::definir('calculo.base_proporcional', 'dias_uteis');
        $c = $this->colaborador('2200', '2020-01-01');

        // Março de 2026 tem 22 dias úteis (segunda a sexta). Primeira semana útil: 2 a 6 = 5 dias.
        $this->assertSame(100000, $this->valor($c, '2026-03-02', '2026-03-13'), '10 dias úteis de 22: 2200 × 10 ÷ 22');
        $this->assertSame(220000, $this->valor($c, '2026-03-01', '2026-03-31'), 'mês cheio');
        $this->assertSame(0, $this->valor($c, '2026-03-07', '2026-03-08'), 'fim de semana');
    }

    public function test_base_desconhecida_na_configuracao_cai_nos_dias_corridos(): void
    {
        Configuracao::definir('calculo.base_proporcional', 'qualquer-coisa');

        $this->assertSame(RemuneracaoProporcional::BASE_CORRIDOS, app(RemuneracaoProporcional::class)->base());
    }

    public function test_proporcionar_um_valor_fixo_segue_a_mesma_base(): void
    {
        $calculo = app(RemuneracaoProporcional::class);

        $this->assertSame(30000, $calculo->proporcionar(30000, Carbon::parse('2026-02-01'), Carbon::parse('2026-02-28')), 'fevereiro inteiro');
        $this->assertSame(10000, $calculo->proporcionar(30000, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-10')));
        $this->assertSame(30000, $calculo->proporcionar(30000, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-31')));
        $this->assertSame(0, $calculo->proporcionar(0, Carbon::parse('2026-03-01'), Carbon::parse('2026-03-10')));
    }
}
