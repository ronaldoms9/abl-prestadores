<?php

namespace Tests\Feature;

use App\Actions\Admissao\ConcluirAdmissao;
use App\Actions\Contratos\CriarContrato;
use App\Actions\Contratos\GerarContrato;
use App\Actions\Fechamento\AbrirCompetencia;
use App\Actions\Fechamento\AprovarCompetencia;
use App\Actions\Ferias\SolicitarFerias;
use App\Actions\Beneficios\VincularBeneficio;
use App\Enums\StatusColaborador;
use App\Enums\StatusCompetencia;
use App\Enums\StatusNota;
use App\Enums\TipoContrato;
use App\Enums\TipoAprovadorFerias;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Cargo;
use App\Models\Colaborador;
use App\Models\ColaboradorEquipe;
use App\Models\ContratoModelo;
use App\Models\Empresa;
use App\Models\Equipe;
use App\Models\EquipeGestor;
use App\Models\Fechamento;
use App\Models\FeriasNivel;
use App\Models\FeriasPeriodo;
use App\Models\FeriasSolicitacao;
use App\Models\NotaFiscal;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Permission;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * O ciclo da proposta de ponta a ponta, sem o UAU (E6, fatia 4): admissão,
 * contrato, benefício, férias em dois níveis, fechamento do mês, nota enviada
 * pelo portal, aprovação e os relatórios que enxergam tudo isso.
 */
class CicloCompletoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
        Storage::fake('local');
        Notification::fake();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_da_admissao_ao_pagamento_aprovado(): void
    {
        // ---- Cadastro ---------------------------------------------------------
        $pj = PessoaJuridica::factory()->create(['cnpj' => '12345678000195']);
        $empresa = Empresa::factory()->create(['cnpj' => '45723174000110']);
        $colaborador = Colaborador::factory()->create([
            'status' => StatusColaborador::PreAdmissao, 'pessoa_juridica_id' => $pj->id, 'empresa_id' => $empresa->id,
            'cargo_id' => Cargo::factory()->create(['nome' => 'Engenheiro'])->id,
        ]);
        $gestor = $this->usuarioCom('ferias-list', 'ferias-solicitar', 'equipe-gerir', 'colaborador-list');
        $equipe = Equipe::factory()->create();
        EquipeGestor::factory()->create(['equipe_id' => $equipe->id, 'user_id' => $gestor->id, 'inicio' => '2025-01-01', 'fim' => null]);
        ColaboradorEquipe::factory()->create(['colaborador_id' => $colaborador->id, 'equipe_id' => $equipe->id, 'inicio' => '2025-01-02', 'fim' => null]);

        // ---- Admissão (sem itens manuais no checklist), com remuneração e benefício
        \App\Models\AdmissaoChecklistItem::factory()->create(['nome' => 'Ficha cadastral', 'ordem' => 1]);
        $linha = app(\App\Actions\Admissao\IniciarAdmissao::class)->executar($colaborador)->first();
        app(\App\Actions\Admissao\MarcarItemAdmissao::class)->executar($linha, true, $this->usuarioCom('admissao-gerir'));
        app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2025-01-02'), '3000.00');
        $colaborador->refresh();
        $this->assertSame(StatusColaborador::Ativo, $colaborador->status);
        $this->assertSame(1, $colaborador->feriasPeriodos()->count(), 'A admissão abre o primeiro período de férias');

        $vt = Beneficio::factory()->create(['nome' => 'Vale transporte']);
        BeneficioValor::factory()->create(['beneficio_id' => $vt->id, 'valor' => 300, 'vigencia_inicio' => '2020-01-01']);
        app(VincularBeneficio::class)->executar($colaborador, $vt, Carbon::parse('2025-01-02'));

        // ---- Contrato gerado -------------------------------------------------
        $modelo = ContratoModelo::factory()->create(['tipo' => TipoContrato::Contrato, 'corpo_html' => '<p>{{contrato.numero}} {{colaborador.nome}} {{remuneracao.valor}}</p>']);
        $contrato = app(GerarContrato::class)->executar(app(CriarContrato::class)->executar($colaborador, $modelo, Carbon::parse('2025-01-02'), null), Carbon::parse('2025-01-02'));
        $this->assertStringContainsString('R$ 3.000,00', $contrato->conteudo_renderizado);

        // ---- Férias: gestor aprova no nível 1, RH no nível 2 -------------------
        FeriasNivel::create(['ordem' => 1, 'tipo_aprovador' => TipoAprovadorFerias::GestorEquipe, 'ativo' => true]);
        FeriasNivel::create(['ordem' => 2, 'tipo_aprovador' => TipoAprovadorFerias::Permissao, 'permission_id' => Permission::firstOrCreate(['name' => 'ferias-aprovar-rh'], ['display_name' => 'RH'])->id, 'ativo' => true]);
        $rh = $this->usuarioCom('ferias-aprovar-rh', 'ferias-list');
        $periodo = FeriasPeriodo::where('colaborador_id', $colaborador->id)->firstOrFail();

        $this->actingAs($this->usuarioCom('ferias-solicitar', 'colaborador-list', 'ferias-list'))
            ->postJson(route('ferias.solicitar', $colaborador->id), ['periodo_id' => $periodo->id, 'inicio' => '2026-12-07', 'fim' => '2026-12-16'])->assertOk();
        $solicitacao = FeriasSolicitacao::firstOrFail();
        $this->actingAs($gestor)->postJson(route('ferias.decidir', $solicitacao->id), ['decisao' => 'aprovada'])->assertOk();
        $this->actingAs($rh)->postJson(route('ferias.decidir', $solicitacao->id), ['decisao' => 'aprovada'])->assertOk();
        $this->assertSame(10, $periodo->fresh()->dias_utilizados);

        // ---- Fechamento de outubro: férias em dezembro não afetam ---------------
        $competencia = app(AbrirCompetencia::class)->executar('2026-10');
        $this->assertSame(StatusCompetencia::EmConferencia, $competencia->fresh()->status);
        $fechamento = Fechamento::where('colaborador_id', $colaborador->id)->firstOrFail();
        $this->assertSame('3300.00', $fechamento->total_liquido, 'R$ 3.000,00 de remuneração e R$ 300,00 de vale transporte');

        $financeiro = $this->usuarioCom('fechamento-list', 'fechamento-aprovar', 'nota-list', 'nota-decidir');
        $this->actingAs($financeiro)->postJson(route('fechamento.aprovar', $competencia->id))->assertOk();
        $this->assertSame(StatusCompetencia::Aprovada, $competencia->fresh()->status);

        // Mês aprovado não aceita mais lançamento retroativo.
        $this->actingAs($this->usuarioCom('ocorrencia-gerir'))
            ->postJson(route('ocorrencia.store'), ['colaborador_id' => $colaborador->id, 'tipo' => 'falta', 'inicio' => '2026-10-10', 'fim' => '2026-10-10'])->assertStatus(422);

        // ---- Nota enviada pelo próprio colaborador no portal -------------------
        $login = $this->usuarioCom('portal-acessar');
        $login->update(['pessoa_fisica_id' => $colaborador->pessoa_fisica_id]);
        $this->actingAs($login)->get(route('home'))->assertRedirect(route('portal.index'));
        $this->get(route('portal.notas'))->assertOk()->assertSee('3.300,00');
        $this->postJson(route('portal.nota.enviar', $fechamento->id), [
            'pdf' => UploadedFile::fake()->create('nota.pdf', 30, 'application/pdf'),
            'numero' => '501', 'serie' => '1', 'data_emissao' => '2026-11-03', 'valor_bruto' => '3300',
            'cnpj_emissor' => '12.345.678/0001-95', 'cnpj_tomador' => '45.723.174/0001-10',
        ])->assertOk()->assertJsonPath('data.status', 'enviada');

        // ---- Financeiro aprova a nota -------------------------------------------
        $nota = NotaFiscal::firstOrFail();
        $this->actingAs($financeiro)->postJson(route('nota.decidir', $nota->id), ['decisao' => 'aprovar'])->assertOk();
        $this->assertSame(StatusNota::Aprovada, $nota->fresh()->status);

        // ---- Relatórios e painel enxergam o ciclo ---------------------------------
        $custo = $this->get(route('relatorio.show', 'custo-por-obra'))->assertOk()->viewData('resultado');
        $this->assertSame('R$ 3.300,00', $custo->total['liquido']);
        $this->assertCount(0, $this->get(route('relatorio.show', 'notas-pendentes'))->viewData('resultado')->linhas, 'Nota aprovada não é pendente');
        $this->get(route('home'))->assertOk()->assertSee('R$ 3.300,00');
    }
}
