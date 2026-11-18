<?php

namespace Tests\Feature\Fechamento;

use App\Enums\AbrangenciaFeriado;
use App\Enums\StatusColaborador;
use App\Enums\StatusSolicitacaoFerias;
use App\Enums\TipoAjuste;
use App\Enums\TipoCalculoBeneficio;
use App\Enums\TipoItemFechamento;
use App\Enums\TipoOcorrencia;
use App\Models\AjusteAvulso;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\Competencia;
use App\Models\Configuracao;
use App\Models\Feriado;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\Obra;
use App\Models\Ocorrencia;
use App\Models\Remuneracao;
use App\Services\Fechamento\CalculadoraFechamento;
use App\Services\Fechamento\ResultadoFechamento;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Matriz de cenários da fatia 6 da E4. Cenário-base: remuneração de R$ 3.000,00,
 * vale transporte fixo de R$ 300,00 e vale alimentação de R$ 30,00 por dia
 * trabalhado. Os esperados estão escritos à mão (em centavos), mês a mês:
 *
 *   setembro/2026 tem 30 dias e 22 dias úteis (1/9 é terça-feira);
 *   fevereiro/2026 tem 28 dias e 20 dias úteis (1/2 é domingo);
 *   fevereiro/2028 tem 29 dias e 21 dias úteis (1/2 é terça-feira).
 */
class CalculadoraFechamentoTest extends TestCase
{
    use RefreshDatabase;

    private const REM = 300000;

    private const VT = 30000;

    private const VA = 3000;

    private Beneficio $vt;

    private Beneficio $va;

    protected function setUp(): void
    {
        parent::setUp();
        $this->vt = Beneficio::factory()->create(['nome' => 'Vale transporte', 'tipo_calculo' => TipoCalculoBeneficio::FixoMensal]);
        $this->va = Beneficio::factory()->create(['nome' => 'Vale alimentação', 'tipo_calculo' => TipoCalculoBeneficio::PorDiaTrabalhado]);
        BeneficioValor::factory()->create(['beneficio_id' => $this->vt->id, 'valor' => 300, 'vigencia_inicio' => '2020-01-01']);
        BeneficioValor::factory()->create(['beneficio_id' => $this->va->id, 'valor' => 30, 'vigencia_inicio' => '2020-01-01']);
    }

    /** Colaborador com a remuneração e os dois benefícios desde a admissão (ou a data dada). */
    private function colaborador(string $admissao = '2025-01-01', ?string $desligamento = null, array $extra = []): Colaborador
    {
        $c = Colaborador::factory()->create($extra + [
            'status' => $desligamento ? StatusColaborador::Desligado : StatusColaborador::Ativo,
            'data_admissao' => $admissao,
            'data_desligamento' => $desligamento,
        ]);
        Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => 3000, 'vigencia_inicio' => $admissao]);
        $fim = $desligamento ? date('Y-m-d', strtotime($desligamento.' +1 day')) : null;
        foreach ([$this->vt, $this->va] as $b) {
            ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $b->id, 'inicio' => $admissao, 'fim' => $fim]);
        }

        return $c;
    }

    private function calcular(Colaborador $c, string $anoMes): ?ResultadoFechamento
    {
        $competencia = Competencia::where('ano_mes', $anoMes)->first() ?? Competencia::factory()->create(['ano_mes' => $anoMes]);

        return app(CalculadoraFechamento::class)->calcular($c->fresh(), $competencia);
    }

    /** @return array{0: int, 1: int, 2: int} remuneração, vale transporte e vale alimentação */
    private function valores(ResultadoFechamento $r): array
    {
        $soma = fn (string $nome) => array_sum(array_map(fn ($i) => str_starts_with($i->descricao, $nome) ? $i->total : 0, $r->itens));

        return [$r->remuneracao(), $soma('Vale transporte'), $soma('Vale alimentação')];
    }

    public function test_mes_cheio(): void
    {
        $r = $this->calcular($this->colaborador(), '2026-09');

        $this->assertSame([300000, 30000, 22 * 3000], $this->valores($r));
        $this->assertSame([30, 22], [$r->diasPeriodo, $r->diasTrabalhados]);
        $this->assertSame(300000 + 30000 + 66000, $r->liquido());
    }

    public function test_admissao_dia_15(): void
    {
        // 15 a 30/9: 16 dias corridos e 12 úteis (15-18, 21-25, 28-30).
        $r = $this->calcular($this->colaborador('2026-09-15'), '2026-09');

        $this->assertSame([160000, 16000, 12 * 3000], $this->valores($r));
        $this->assertSame([16, 12], [$r->diasPeriodo, $r->diasTrabalhados]);
    }

    public function test_desligamento_dia_10(): void
    {
        // 1 a 10/9 (último dia trabalhado): 10 dias corridos e 8 úteis (1-4 e 7-10).
        $r = $this->calcular($this->colaborador('2025-01-01', '2026-09-10'), '2026-09');

        $this->assertSame([100000, 10000, 8 * 3000], $this->valores($r));
    }

    public function test_reajuste_da_remuneracao_dia_16(): void
    {
        $c = $this->colaborador();
        Remuneracao::where('colaborador_id', $c->id)->update(['vigencia_fim' => '2026-09-16']);
        Remuneracao::factory()->create(['colaborador_id' => $c->id, 'valor_mensal' => 3300, 'vigencia_inicio' => '2026-09-16']);

        $r = $this->calcular($c, '2026-09');

        // (3000 × 15 + 3300 × 15) ÷ 30 = 3150,00; benefícios sem mudança.
        $this->assertSame([315000, 30000, 66000], $this->valores($r));
        $this->assertStringContainsString('R$ 3.000,00 de 01/09 a 15/09', $r->itens[0]->descricao);
        $this->assertStringContainsString('R$ 3.300,00 de 16/09 a 30/09', $r->itens[0]->descricao);
    }

    public function test_reajuste_de_beneficio_dia_16(): void
    {
        BeneficioValor::where('beneficio_id', $this->vt->id)->update(['vigencia_fim' => '2026-09-16']);
        BeneficioValor::factory()->create(['beneficio_id' => $this->vt->id, 'valor' => 330, 'vigencia_inicio' => '2026-09-16']);
        BeneficioValor::where('beneficio_id', $this->va->id)->update(['vigencia_fim' => '2026-09-16']);
        BeneficioValor::factory()->create(['beneficio_id' => $this->va->id, 'valor' => 33, 'vigencia_inicio' => '2026-09-16']);

        $r = $this->calcular($this->colaborador(), '2026-09');

        // VT: 300 × 15/30 + 330 × 15/30 = 150,00 + 165,00 = 315,00.
        // VA: 1-15 tem 11 úteis × 30,00 = 330,00; 16-30 tem 11 úteis × 33,00 = 363,00; total 693,00.
        $this->assertSame([300000, 31500, 69300], $this->valores($r));
    }

    public function test_ferias_de_10_dias_nao_reduzem_remuneracao_nem_vt_mas_tiram_os_dias_do_va(): void
    {
        $c = $this->colaborador();
        $periodo = FeriasPeriodo::factory()->create(['colaborador_id' => $c->id]);
        // 14 a 23/9: 8 dias úteis (14-18 e 21-23).
        FeriasSolicitacao::factory()->create(['colaborador_id' => $c->id, 'ferias_periodo_id' => $periodo->id, 'inicio' => '2026-09-14', 'fim' => '2026-09-23', 'dias' => 10, 'status' => StatusSolicitacaoFerias::Aprovada]);
        // Pendente e cancelada não contam.
        FeriasSolicitacao::factory()->create(['colaborador_id' => $c->id, 'ferias_periodo_id' => $periodo->id, 'inicio' => '2026-09-28', 'fim' => '2026-09-30', 'dias' => 3, 'status' => StatusSolicitacaoFerias::Pendente]);

        $r = $this->calcular($c, '2026-09');

        $this->assertSame([300000, 30000, 14 * 3000], $this->valores($r));
        $this->assertSame(14, $r->diasTrabalhados);
    }

    public function test_feriado_municipal_da_obra_so_conta_para_quem_e_da_obra(): void
    {
        $obra = Obra::factory()->create();
        Feriado::factory()->create(['data' => '2026-09-16', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => $obra->id]);
        Feriado::factory()->create(['data' => '2026-09-17', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => Obra::factory()->create()->id]);

        $daObra = $this->calcular($this->colaborador(extra: ['obra_id' => $obra->id]), '2026-09');
        $semObra = $this->calcular($this->colaborador(), '2026-09');

        $this->assertSame([300000, 30000, 21 * 3000], $this->valores($daObra));
        $this->assertSame([300000, 30000, 22 * 3000], $this->valores($semObra));
    }

    public function test_feriado_nacional_vale_para_todos(): void
    {
        Feriado::factory()->create(['data' => '2026-09-07']); // segunda-feira

        $this->assertSame([300000, 30000, 21 * 3000], $this->valores($this->calcular($this->colaborador(), '2026-09')));
    }

    public function test_falta_abonada_nao_desconta_e_nao_abonada_e_afastamento_descontam(): void
    {
        $c = $this->colaborador();
        Ocorrencia::factory()->create(['colaborador_id' => $c->id, 'inicio' => '2026-09-02', 'fim' => '2026-09-02', 'abona' => false]); // quarta
        Ocorrencia::factory()->create(['colaborador_id' => $c->id, 'inicio' => '2026-09-03', 'fim' => '2026-09-03', 'abona' => true]);  // quinta
        $this->assertSame([300000, 30000, 21 * 3000], $this->valores($this->calcular($c, '2026-09')));

        Ocorrencia::factory()->create(['colaborador_id' => $c->id, 'tipo' => TipoOcorrencia::Afastamento, 'inicio' => '2026-09-08', 'fim' => '2026-09-10', 'abona' => false]);
        $this->assertSame([300000, 30000, 18 * 3000], $this->valores($this->calcular($c, '2026-09')));
    }

    public function test_falta_no_fim_de_semana_ou_em_feriado_nao_desconta_duas_vezes(): void
    {
        $c = $this->colaborador();
        Feriado::factory()->create(['data' => '2026-09-07']);
        Ocorrencia::factory()->create(['colaborador_id' => $c->id, 'inicio' => '2026-09-05', 'fim' => '2026-09-07', 'abona' => false]); // sáb a seg (feriado)

        $this->assertSame([300000, 30000, 21 * 3000], $this->valores($this->calcular($c, '2026-09')));
    }

    public function test_fevereiro_com_28_e_com_29_dias(): void
    {
        $this->assertSame([300000, 30000, 20 * 3000], $this->valores($this->calcular($this->colaborador(), '2026-02')));
        $this->assertSame([300000, 30000, 21 * 3000], $this->valores($this->calcular($this->colaborador(), '2028-02')));

        // Admissão em 15/2/2026: 14 dias corridos (÷ 30) e 10 úteis (16-20 e 23-27).
        $this->assertSame([140000, 14000, 10 * 3000], $this->valores($this->calcular($this->colaborador('2026-02-15'), '2026-02')));
    }

    public function test_admissao_e_desligamento_no_mesmo_mes(): void
    {
        // 10 a 20/9: 11 dias corridos; úteis 10-11 e 14-18 = 7.
        $r = $this->calcular($this->colaborador('2026-09-10', '2026-09-20'), '2026-09');

        $this->assertSame([110000, 11000, 7 * 3000], $this->valores($r));
        $this->assertSame([11, 7], [$r->diasPeriodo, $r->diasTrabalhados]);
    }

    public function test_sabado_trabalhado_por_configuracao(): void
    {
        Configuracao::definir('calculo.sabado_trabalhado', true);

        // 22 úteis + 4 sábados (5, 12, 19 e 26).
        $this->assertSame([300000, 30000, 26 * 3000], $this->valores($this->calcular($this->colaborador(), '2026-09')));
    }

    public function test_beneficio_fixo_cheio_por_configuracao(): void
    {
        Configuracao::definir('calculo.beneficio_fixo_proporcional', false);

        $r = $this->calcular($this->colaborador('2026-09-15'), '2026-09');

        $this->assertSame([160000, 30000, 12 * 3000], $this->valores($r));
    }

    public function test_beneficio_com_valor_personalizado_prevalece_sobre_a_tabela(): void
    {
        $c = $this->colaborador();
        ColaboradorBeneficio::where('colaborador_id', $c->id)->where('beneficio_id', $this->vt->id)->update(['valor_personalizado' => 150]);

        $this->assertSame([300000, 15000, 66000], $this->valores($this->calcular($c, '2026-09')));
    }

    public function test_ajustes_avulsos_somam_e_subtraem_e_entram_no_liquido(): void
    {
        $c = $this->colaborador();
        $competencia = Competencia::factory()->create(['ano_mes' => '2026-09']);
        AjusteAvulso::factory()->create(['colaborador_id' => $c->id, 'competencia_id' => $competencia->id, 'tipo' => TipoAjuste::Credito, 'valor' => 50, 'justificativa' => 'Bônus de obra']);
        AjusteAvulso::factory()->create(['colaborador_id' => $c->id, 'competencia_id' => $competencia->id, 'tipo' => TipoAjuste::Debito, 'valor' => 20, 'justificativa' => 'Adiantamento']);
        AjusteAvulso::factory()->create(['competencia_id' => $competencia->id, 'valor' => 999]); // de outro colaborador

        $r = $this->calcular($c, '2026-09');

        $this->assertSame(3000, $r->ajustes());
        $this->assertSame(300000 + 30000 + 66000 + 3000, $r->liquido());
        $tipos = array_map(fn ($i) => $i->tipo, array_slice($r->itens, -2));
        $this->assertSame([TipoItemFechamento::Ajuste, TipoItemFechamento::Desconto], $tipos);
        $this->assertSame(-2000, $r->itens[count($r->itens) - 1]->total);
    }

    public function test_quem_nao_esteve_ativo_no_mes_nao_tem_fechamento(): void
    {
        $this->assertNull($this->calcular($this->colaborador('2026-10-01'), '2026-09'));
        $this->assertNull($this->calcular($this->colaborador('2025-01-01', '2026-08-31'), '2026-09'));
        $semAdmissao = Colaborador::factory()->create();
        $this->assertNull($this->calcular($semAdmissao, '2026-09'));
    }

    public function test_sem_remuneracao_ou_sem_valor_na_tabela_avisa_e_zera(): void
    {
        $c = Colaborador::factory()->create(['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01']);
        $beneficio = Beneficio::factory()->create(['nome' => 'Plano']);
        ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $beneficio->id, 'inicio' => '2025-01-01']);

        $r = $this->calcular($c, '2026-09');

        $this->assertSame(0, $r->liquido());
        $this->assertCount(2, $r->avisos);
    }

    public function test_item_guarda_a_origem_e_a_memoria_de_calculo(): void
    {
        $c = $this->colaborador('2026-09-15');

        $r = $this->calcular($c, '2026-09');

        $this->assertInstanceOf(Remuneracao::class, $r->itens[0]->origem);
        $this->assertStringContainsString('Remuneração proporcional, 16 dia(s) ativo(s) de 15/09 a 30/09', $r->itens[0]->descricao);
        $va = collect($r->itens)->first(fn ($i) => str_starts_with($i->descricao, 'Vale alimentação'));
        $this->assertSame([12.0, 3000, 36000], [$va->quantidade, $va->valorUnitario, $va->total]);
        $this->assertInstanceOf(ColaboradorBeneficio::class, $va->origem);
    }
}
