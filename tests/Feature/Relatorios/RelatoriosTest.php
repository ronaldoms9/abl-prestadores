<?php

namespace Tests\Feature\Relatorios;

use App\Enums\StatusColaborador;
use App\Enums\StatusFechamento;
use App\Enums\StatusNota;
use App\Exports\RelatorioExport;
use App\Models\Beneficio;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\Competencia;
use App\Models\Contrato;
use App\Models\Empresa;
use App\Models\Equipe;
use App\Models\Fechamento;
use App\Models\FechamentoItem;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\NotaFiscal;
use App\Models\Obra;
use App\Services\Relatorios\CustoPorObra;
use App\Support\Contratos\GeradorPdf;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

class RelatoriosTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function ativo(array $extra = []): Colaborador
    {
        return Colaborador::factory()->create($extra + ['status' => StatusColaborador::Ativo, 'data_admissao' => '2025-01-01']);
    }

    private function fechamento(Colaborador $c, string $anoMes, float $rem, float $ben, float $ajuste = 0, string $statusCompetencia = 'aprovada'): Fechamento
    {
        $competencia = Competencia::where('ano_mes', $anoMes)->first() ?? Competencia::factory()->create(['ano_mes' => $anoMes, 'status' => $statusCompetencia]);

        return Fechamento::factory()->create([
            'competencia_id' => $competencia->id, 'colaborador_id' => $c->id,
            'valor_remuneracao' => $rem, 'total_beneficios' => $ben, 'total_ajustes' => $ajuste, 'total_liquido' => $rem + $ben + $ajuste,
            'status' => StatusFechamento::AguardandoNota,
        ]);
    }

    private function linhas($resposta): array
    {
        return $resposta->viewData('resultado')->linhas;
    }

    public function test_custo_por_obra_soma_so_competencias_aprovadas_e_respeita_filtros(): void
    {
        $empresa = Empresa::factory()->create();
        $a = Obra::factory()->create(['descricao' => 'Alfa']);
        $b = Obra::factory()->create(['descricao' => 'Beta']);
        $this->fechamento($this->ativo(['obra_id' => $a->id, 'empresa_id' => $empresa->id]), '2026-08', 1000, 100, 50);
        $this->fechamento($this->ativo(['obra_id' => $a->id, 'empresa_id' => $empresa->id]), '2026-09', 2000, 200);
        $this->fechamento($this->ativo(['obra_id' => $b->id]), '2026-09', 500, 0);
        $this->fechamento($this->ativo(['obra_id' => $a->id]), '2026-10', 99999, 0, 0, 'em_conferencia');
        $this->actingAs($this->usuarioCom('fechamento-list'));

        $tudo = $this->linhas($this->get(route('relatorio.show', 'custo-por-obra')));
        $this->assertSame(['Alfa', 2, 'R$ 3.000,00', 'R$ 300,00', 'R$ 50,00', 'R$ 3.350,00'], [$tudo[0]['obra'], $tudo[0]['colaboradores'], $tudo[0]['remuneracao'], $tudo[0]['beneficios'], $tudo[0]['ajustes'], $tudo[0]['liquido']]);
        $this->assertSame(['Beta', 'R$ 500,00'], [$tudo[1]['obra'], $tudo[1]['liquido']]);
        $this->assertSame('R$ 3.850,00', $this->get(route('relatorio.show', 'custo-por-obra'))->viewData('resultado')->total['liquido']);

        $setembro = $this->linhas($this->get(route('relatorio.show', ['custo-por-obra', 'de' => '2026-09', 'ate' => '2026-09'])));
        $this->assertSame(['R$ 2.200,00', 'R$ 500,00'], array_column($setembro, 'liquido'));
        $this->assertSame(['Alfa'], array_column($this->linhas($this->get(route('relatorio.show', ['custo-por-obra', 'obra_id' => $a->id, 'empresa_id' => $empresa->id]))), 'obra'));
    }

    public function test_beneficios_agrupa_pelo_beneficio_da_origem_do_item(): void
    {
        $vt = Beneficio::factory()->create(['nome' => 'Vale transporte']);
        $va = Beneficio::factory()->create(['nome' => 'Vale alimentação']);
        $c1 = $this->ativo();
        $c2 = $this->ativo();
        foreach ([[$c1, $vt, 300, '2026-09'], [$c2, $vt, 300, '2026-09'], [$c1, $va, 700, '2026-09'], [$c1, $vt, 300, '2026-08']] as [$c, $b, $valor, $mes]) {
            $f = Fechamento::where('colaborador_id', $c->id)->whereHas('competencia', fn ($q) => $q->where('ano_mes', $mes))->first() ?? $this->fechamento($c, $mes, 1000, 0);
            $vinculo = ColaboradorBeneficio::factory()->create(['colaborador_id' => $c->id, 'beneficio_id' => $b->id]);
            FechamentoItem::factory()->create(['fechamento_id' => $f->id, 'tipo' => 'beneficio', 'valor_total' => $valor, 'origem_type' => 'colaborador-beneficio', 'origem_id' => $vinculo->id]);
        }
        $this->actingAs($this->usuarioCom('fechamento-list'));

        $r = $this->get(route('relatorio.show', ['beneficios', 'de' => '2026-09']))->viewData('resultado');

        $this->assertSame([['Vale alimentação', 1, 'R$ 700,00'], ['Vale transporte', 2, 'R$ 600,00']], array_map(fn ($l) => [$l['beneficio'], $l['colaboradores'], $l['total']], $r->linhas));
        $this->assertSame('R$ 1.300,00', $r->total['total']);
        $this->assertSame(['Vale transporte'], array_column($this->linhas($this->get(route('relatorio.show', ['beneficios', 'de' => '2026-09', 'beneficio_id' => $vt->id]))), 'beneficio'));
    }

    public function test_ferias_e_saldos_ordena_pelo_limite_e_filtra_por_saldo_e_equipe(): void
    {
        $perto = $this->ativo();
        $longe = $this->ativo();
        $zerado = $this->ativo();
        FeriasPeriodo::factory()->create(['colaborador_id' => $perto->id, 'dias_utilizados' => 10, 'limite_gozo' => '2026-12-01']);
        FeriasPeriodo::factory()->create(['colaborador_id' => $longe->id, 'dias_utilizados' => 0, 'limite_gozo' => '2027-06-01']);
        FeriasPeriodo::factory()->create(['colaborador_id' => $zerado->id, 'dias_utilizados' => 30, 'limite_gozo' => '2027-01-01']);
        FeriasSolicitacao::factory()->create(['colaborador_id' => $perto->id, 'ferias_periodo_id' => $perto->feriasPeriodos()->first()->id, 'inicio' => '2026-11-10', 'fim' => '2026-11-14', 'dias' => 5, 'status' => 'aprovada']);
        $equipe = Equipe::factory()->create();
        \App\Models\ColaboradorEquipe::factory()->create(['colaborador_id' => $longe->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-01-01', 'fim' => null]);
        $this->actingAs($this->usuarioCom('ferias-list'));

        $r = $this->linhas($this->get(route('relatorio.show', 'ferias-e-saldos')));
        $this->assertSame([20, 30, 0], array_column($r, 'saldo'));
        $this->assertSame(['01/12/2026', 5], [$r[0]['limite'], $r[0]['a_gozar']]);

        $this->assertSame([30], array_column($this->linhas($this->get(route('relatorio.show', ['ferias-e-saldos', 'equipe_id' => $equipe->id]))), 'saldo'));
        $this->assertSame([20, 30], array_column($this->linhas($this->get(route('relatorio.show', ['ferias-e-saldos', 'saldo_minimo' => 20]))), 'saldo'));
    }

    public function test_contratos_vencendo_e_notas_pendentes(): void
    {
        $c = $this->ativo();
        Contrato::factory()->create(['colaborador_id' => $c->id, 'status' => 'gerado', 'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2026-10-25', 'numero' => '2026/0001']);
        Contrato::factory()->create(['colaborador_id' => $this->ativo()->id, 'status' => 'gerado', 'vigencia_inicio' => '2026-01-01', 'vigencia_fim' => '2027-10-25']);
        $this->actingAs($this->usuarioCom('contrato-list'));

        $r = $this->linhas($this->get(route('relatorio.show', 'contratos-vencendo')));
        $this->assertSame([['2026/0001', 'Vence em 10 dia(s)']], array_map(fn ($l) => [$l['contrato'], $l['situacao']], $r));

        $sem = $this->fechamento($this->ativo(), '2026-09', 1000, 0);
        $ok = $this->fechamento($this->ativo(), '2026-09', 2000, 0);
        NotaFiscal::factory()->create(['fechamento_id' => $ok->id, 'status' => StatusNota::Aprovada]);
        $enviada = $this->fechamento($this->ativo(), '2026-09', 3000, 0);
        NotaFiscal::factory()->create(['fechamento_id' => $enviada->id, 'status' => StatusNota::Enviada]);
        $this->actingAs($this->usuarioCom('nota-list'));

        $this->assertEqualsCanonicalizing(['Aguardando nota', 'Enviada'], array_column($this->linhas($this->get(route('relatorio.show', 'notas-pendentes'))), 'situacao'));
        $this->assertCount(3, $this->linhas($this->get(route('relatorio.show', ['notas-pendentes', 'status' => 'todas']))));
        $this->assertSame(['Enviada'], array_column($this->linhas($this->get(route('relatorio.show', ['notas-pendentes', 'status' => 'enviada']))), 'situacao'));
        $this->assertCount(0, $this->linhas($this->get(route('relatorio.show', ['notas-pendentes', 'competencia' => '2026-08']))));
        $this->assertNotNull($sem);
    }

    public function test_qualificacoes_vencendo_usa_a_matriz_do_cargo(): void
    {
        $cargo = \App\Models\Cargo::factory()->create();
        $tipo = \App\Models\QualificacaoTipo::factory()->create(['nome' => 'NR-35']);
        \App\Models\CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $tipo->id, 'obrigatoria' => true]);
        $ausente = $this->ativo(['cargo_id' => $cargo->id]);
        $this->actingAs($this->usuarioCom('qualificacao-list'));

        $r = $this->linhas($this->get(route('relatorio.show', 'qualificacoes-vencendo')));

        $this->assertSame([[$ausente->pessoaFisica->nome, 'NR-35', 'Ausente']], array_map(fn ($l) => [$l['colaborador'], $l['qualificacao'], $l['situacao']], $r));
        $this->assertCount(0, $this->linhas($this->get(route('relatorio.show', ['qualificacoes-vencendo', 'cargo_id' => \App\Models\Cargo::factory()->create()->id]))));
    }

    public function test_cada_relatorio_so_aparece_e_abre_para_quem_tem_a_permissao_da_origem(): void
    {
        $this->actingAs($this->usuarioCom('fechamento-list'));
        $this->get(route('relatorio.index'))->assertOk()->assertSee('Custo por obra')->assertSee('Benefícios')->assertDontSee('Férias e saldos');
        $this->get(route('relatorio.show', 'ferias-e-saldos'))->assertForbidden();
        $this->get(route('relatorio.show', 'nao-existe'))->assertNotFound();

        $this->actingAs($this->usuarioCom('colaborador-list'));
        $this->get(route('relatorio.index'))->assertForbidden();
    }

    public function test_filtros_invalidos_sao_recusados(): void
    {
        $this->actingAs($this->usuarioCom('fechamento-list'));

        $this->getJson(route('relatorio.show', ['custo-por-obra', 'de' => '09/2026']))->assertStatus(422);
        $this->getJson(route('relatorio.show', ['custo-por-obra', 'obra_id' => 'abc']))->assertStatus(422);
    }

    public function test_excel_tem_as_mesmas_linhas_da_tela_mais_o_total(): void
    {
        $this->fechamento($this->ativo(['obra_id' => Obra::factory()->create(['descricao' => 'Alfa'])->id]), '2026-09', 1000, 100);
        $this->actingAs($this->usuarioCom('fechamento-list'));
        $resultado = app(CustoPorObra::class)->executar([], auth()->user());

        $export = new RelatorioExport('Custo por obra', $resultado);

        $this->assertSame(['Obra', 'Colaboradores', 'Remuneração', 'Benefícios', 'Ajustes', 'Total líquido'], $export->headings());
        $this->assertSame([['Alfa', 1, 'R$ 1.000,00', 'R$ 100,00', 'R$ 0,00', 'R$ 1.100,00'], ['Total', '', 'R$ 1.000,00', 'R$ 100,00', 'R$ 0,00', 'R$ 1.100,00']], $export->array());
        $this->assertSame('Custo por obra', $export->title());

        Excel::fake();
        $this->get(route('relatorio.exportar', ['custo-por-obra', 'xlsx']))->assertOk();
        Excel::assertDownloaded('custo-por-obra-2026-10-15.xlsx');
    }

    public function test_pdf_traz_cabecalho_filtros_e_data_e_sem_biblioteca_avisa(): void
    {
        $this->fechamento($this->ativo(), '2026-09', 1000, 100);
        $this->actingAs($this->usuarioCom('fechamento-list'));
        $falso = new class implements GeradorPdf
        {
            public ?string $html = null;

            public function gerar(string $html): string
            {
                $this->html = $html;

                return '%PDF-fake';
            }
        };
        $this->app->bind(GeradorPdf::class, fn () => $falso);

        $resposta = $this->get(route('relatorio.exportar', ['custo-por-obra', 'pdf', 'de' => '2026-09']));

        $resposta->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-fake', $resposta->getContent());
        $this->assertStringContainsString('Custo por obra', $falso->html);
        $this->assertStringContainsString('Competência de: 2026-09', $falso->html);
        $this->assertStringContainsString('Emitido em 15/10/2026 10:00', $falso->html);

        $this->app->bind(GeradorPdf::class, \App\Support\Contratos\PdfIndisponivel::class);
        $this->get(route('relatorio.exportar', ['custo-por-obra', 'pdf']))->assertStatus(503)->assertJsonFragment(['success' => false]);
        $this->get(route('relatorio.exportar', ['custo-por-obra', 'odt']))->assertNotFound();
    }
}
