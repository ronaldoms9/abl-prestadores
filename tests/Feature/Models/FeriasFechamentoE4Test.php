<?php

namespace Tests\Feature\Models;

use App\Enums\AbrangenciaFeriado;
use App\Enums\StatusCompetencia;
use App\Enums\StatusFechamento;
use App\Enums\StatusSolicitacaoFerias;
use App\Enums\TipoAjuste;
use App\Enums\TipoAprovadorFerias;
use App\Enums\TipoItemFechamento;
use App\Models\AjusteAvulso;
use App\Models\Colaborador;
use App\Models\Competencia;
use App\Models\Feriado;
use App\Models\FeriasAprovacao;
use App\Models\FeriasNivel;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\Fechamento;
use App\Models\FechamentoItem;
use App\Models\Obra;
use App\Models\Ocorrencia;
use App\Models\Remuneracao;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeriasFechamentoE4Test extends TestCase
{
    use RefreshDatabase;

    public function test_cada_factory_cria_um_registro_valido_com_autoria(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        foreach ([
            FeriasPeriodo::class, FeriasSolicitacao::class, FeriasNivel::class, FeriasAprovacao::class, Feriado::class,
            Ocorrencia::class, Competencia::class, Fechamento::class, FechamentoItem::class, AjusteAvulso::class,
        ] as $classe) {
            $registro = $classe::factory()->create();
            $this->assertDatabaseHas($registro->getTable(), ['id' => $registro->id, 'created_by' => $usuario->id]);
        }
    }

    public function test_saldo_e_direito_menos_utilizados_e_nunca_negativo(): void
    {
        $periodo = FeriasPeriodo::factory()->create(['dias_direito' => 30, 'dias_utilizados' => 12]);
        $this->assertSame(18, $periodo->saldo());

        $periodo->dias_utilizados = 35;
        $this->assertSame(0, $periodo->saldo());
    }

    public function test_periodo_e_unico_por_colaborador_e_inicio(): void
    {
        $colaborador = Colaborador::factory()->create();
        FeriasPeriodo::factory()->create(['colaborador_id' => $colaborador->id, 'aquisitivo_inicio' => '2025-03-02']);

        FeriasPeriodo::factory()->create(['colaborador_id' => $colaborador->id, 'aquisitivo_inicio' => '2026-03-02']);
        $this->assertSame(2, $colaborador->feriasPeriodos()->count());

        $this->expectException(UniqueConstraintViolationException::class);
        FeriasPeriodo::factory()->create(['colaborador_id' => $colaborador->id, 'aquisitivo_inicio' => '2025-03-02']);
    }

    public function test_solicitacao_liga_periodo_aprovacoes_e_nasce_pendente_no_nivel_1(): void
    {
        $solicitacao = FeriasSolicitacao::factory()->create();
        FeriasAprovacao::factory()->create(['ferias_solicitacao_id' => $solicitacao->id, 'nivel' => 1, 'escalada' => true, 'motivo_escalada' => 'sem gestor']);

        $solicitacao = FeriasSolicitacao::with(['periodo', 'aprovacoes', 'colaborador'])->find($solicitacao->id);

        $this->assertSame([StatusSolicitacaoFerias::Pendente, 1], [$solicitacao->status, $solicitacao->nivel_atual]);
        $this->assertCount(1, $solicitacao->aprovacoes);
        $this->assertTrue($solicitacao->aprovacoes->first()->escalada);
        $this->assertInstanceOf(FeriasPeriodo::class, $solicitacao->periodo);
    }

    public function test_nivel_aceita_gestor_da_equipe_ou_permissao(): void
    {
        $permissao = \App\Permission::firstOrCreate(['name' => 'ferias-aprovar-rh'], ['display_name' => 'x']);
        FeriasNivel::factory()->create(['ordem' => 1, 'tipo_aprovador' => TipoAprovadorFerias::GestorEquipe]);
        FeriasNivel::factory()->create(['ordem' => 2, 'tipo_aprovador' => TipoAprovadorFerias::Permissao, 'permission_id' => $permissao->id]);
        FeriasNivel::factory()->create(['ordem' => 3, 'ativo' => false]);

        $niveis = FeriasNivel::ativos()->with('permissao')->orderBy('ordem')->get();

        $this->assertSame([1, 2], $niveis->pluck('ordem')->all());
        $this->assertSame('ferias-aprovar-rh', $niveis[1]->permissao->name);
        $this->assertNull($niveis[0]->permissao);
    }

    public function test_feriado_pode_ser_de_uma_obra(): void
    {
        $obra = Obra::factory()->create();
        Feriado::factory()->create(['data' => '2026-10-24', 'descricao' => 'Aniversário da cidade', 'abrangencia' => AbrangenciaFeriado::Municipal, 'obra_id' => $obra->id]);

        $feriado = Feriado::with('obra')->firstOrFail();

        $this->assertSame([AbrangenciaFeriado::Municipal, '2026-10-24'], [$feriado->abrangencia, $feriado->data->toDateString()]);
        $this->assertTrue($feriado->obra->is($obra));
    }

    public function test_competencia_tem_ano_mes_unico_e_status_que_diz_se_e_imutavel(): void
    {
        Competencia::factory()->create(['ano_mes' => '2026-10']);

        $this->assertTrue(StatusCompetencia::Aprovada->imutavel());
        $this->assertTrue(StatusCompetencia::Fechada->imutavel());
        $this->assertFalse(StatusCompetencia::Aberta->imutavel());
        $this->assertFalse(StatusCompetencia::EmConferencia->imutavel());

        $this->expectException(UniqueConstraintViolationException::class);
        Competencia::factory()->create(['ano_mes' => '2026-10']);
    }

    public function test_fechamento_e_unico_por_competencia_e_colaborador_e_guarda_snapshot(): void
    {
        $competencia = Competencia::factory()->create();
        $colaborador = Colaborador::factory()->create();
        $fechamento = Fechamento::factory()->create(['competencia_id' => $competencia->id, 'colaborador_id' => $colaborador->id, 'snapshot' => ['remuneracao' => 500000, 'itens' => [1, 2]]]);

        $this->assertSame(['remuneracao' => 500000, 'itens' => [1, 2]], $fechamento->fresh()->snapshot);
        $this->assertSame(StatusFechamento::Calculado, $fechamento->fresh()->status);

        $this->expectException(UniqueConstraintViolationException::class);
        Fechamento::factory()->create(['competencia_id' => $competencia->id, 'colaborador_id' => $colaborador->id]);
    }

    public function test_item_do_fechamento_aponta_para_a_origem_do_valor(): void
    {
        $remuneracao = Remuneracao::factory()->create();
        $fechamento = Fechamento::factory()->create();
        $item = new FechamentoItem(['fechamento_id' => $fechamento->id, 'tipo' => TipoItemFechamento::Remuneracao, 'descricao' => 'Remuneração', 'quantidade' => 30, 'valor_unitario' => 166.67, 'valor_total' => 5000.10]);
        $item->origem()->associate($remuneracao);
        $item->save();

        $item = FechamentoItem::with('origem')->find($item->id);

        $this->assertSame('remuneracao', $item->origem_type);
        $this->assertTrue($item->origem->is($remuneracao));
        $this->assertSame('5000.10', $item->valor_total);
        $this->assertCount(1, Fechamento::with('itens')->find($fechamento->id)->itens);
    }

    public function test_ajuste_avulso_e_ocorrencia_ligam_colaborador_e_competencia(): void
    {
        $ajuste = AjusteAvulso::factory()->create(['tipo' => TipoAjuste::Debito, 'valor' => 50.5]);
        $ocorrencia = Ocorrencia::factory()->create(['abona' => true]);

        $this->assertSame([TipoAjuste::Debito, '50.50'], [$ajuste->fresh()->tipo, $ajuste->fresh()->valor]);
        $this->assertInstanceOf(Competencia::class, AjusteAvulso::with('competencia')->find($ajuste->id)->competencia);
        $this->assertTrue(Ocorrencia::with('colaborador')->find($ocorrencia->id)->abona);
    }
}
