<?php

namespace Tests\Feature\Admissao;

use App\Actions\Admissao\ConcluirAdmissao;
use App\Actions\Admissao\IniciarAdmissao;
use App\Actions\Admissao\MarcarItemAdmissao;
use App\Actions\Admissao\SincronizarItensAutomaticos;
use App\Enums\AplicaDocumento;
use App\Enums\StatusColaborador;
use App\Enums\StatusContrato;
use App\Models\AdmissaoChecklist;
use App\Models\AdmissaoChecklistItem;
use App\Models\Beneficio;
use App\Models\BeneficioValor;
use App\Models\Cargo;
use App\Models\CargoBeneficio;
use App\Models\CargoQualificacao;
use App\Models\Colaborador;
use App\Models\ColaboradorBeneficio;
use App\Models\ColaboradorQualificacao;
use App\Models\Contrato;
use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\PessoaJuridica;
use App\Models\QualificacaoTipo;
use App\Models\Remuneracao;
use App\Support\Admissao\ItensAutomaticos;
use Carbon\Carbon;
use Database\Seeders\AdmissaoChecklistItemSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class AdmissaoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-18 10:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function colaborador(array $extra = []): Colaborador
    {
        return Colaborador::factory()->create($extra + ['status' => StatusColaborador::PreAdmissao, 'cargo_id' => Cargo::factory()->create()->id]);
    }

    /** Só os itens manuais pedidos, sem os automáticos do seeder. */
    private function itens(string ...$nomes): void
    {
        foreach ($nomes as $i => $nome) {
            AdmissaoChecklistItem::factory()->create(['nome' => $nome, 'ordem' => $i + 1]);
        }
    }

    private function automatico(string $chave, bool $obrigatorio = true): AdmissaoChecklistItem
    {
        [$nome, $ordem] = ItensAutomaticos::PADRAO[$chave];

        return AdmissaoChecklistItem::factory()->create(['nome' => $nome, 'chave' => $chave, 'ordem' => $ordem, 'obrigatorio' => $obrigatorio]);
    }

    private function avaliar(Colaborador $c): array
    {
        return app(ItensAutomaticos::class)->avaliar($c, now());
    }

    public function test_seeder_cria_os_itens_automaticos_sem_sobrescrever_o_que_o_admin_mudou(): void
    {
        $this->seed(AdmissaoChecklistItemSeeder::class);
        $this->assertSame(5, AdmissaoChecklistItem::count());

        AdmissaoChecklistItem::where('chave', ItensAutomaticos::PJ_NO_UAU)->update(['obrigatorio' => false, 'nome' => 'PJ no UAU (opcional)']);
        AdmissaoChecklistItem::where('chave', ItensAutomaticos::CONTRATO_ASSINADO)->delete();
        $this->seed(AdmissaoChecklistItemSeeder::class);

        $this->assertSame(5, AdmissaoChecklistItem::withTrashed()->count());
        $item = AdmissaoChecklistItem::where('chave', ItensAutomaticos::PJ_NO_UAU)->first();
        $this->assertSame(['PJ no UAU (opcional)', false], [$item->nome, $item->obrigatorio]);
        $this->assertSame(4, AdmissaoChecklistItem::count(), 'o item excluído pelo admin não volta');
    }

    public function test_inicia_copiando_so_os_itens_ativos_em_ordem_e_nao_duplica(): void
    {
        $this->itens('Ficha cadastral', 'Foto 3x4');
        AdmissaoChecklistItem::factory()->create(['nome' => 'Inativo', 'ativo' => false]);
        AdmissaoChecklistItem::factory()->create(['nome' => 'Opcional', 'obrigatorio' => false, 'ordem' => 9]);
        $colaborador = $this->colaborador();

        $linhas = app(IniciarAdmissao::class)->executar($colaborador);

        $this->assertSame(['Ficha cadastral', 'Foto 3x4', 'Opcional'], $linhas->pluck('item')->all());
        $this->assertSame([true, true, false], $linhas->pluck('obrigatorio')->all());
        $this->assertTrue(Activity::where('event', 'admissao-iniciada')->exists());

        app(IniciarAdmissao::class)->executar($colaborador);
        $this->assertSame(3, AdmissaoChecklist::where('colaborador_id', $colaborador->id)->count());
        $this->assertSame(1, Activity::where('event', 'admissao-iniciada')->count());
    }

    public function test_reiniciar_acrescenta_item_novo_sem_apagar_nem_desmarcar_o_que_existe(): void
    {
        $this->itens('Ficha cadastral');
        $colaborador = $this->colaborador();
        $linha = app(IniciarAdmissao::class)->executar($colaborador)->first();
        app(MarcarItemAdmissao::class)->executar($linha, true, $this->usuarioCom());
        AdmissaoChecklistItem::factory()->create(['nome' => 'Item novo', 'ordem' => 5]);
        AdmissaoChecklistItem::where('nome', 'Ficha cadastral')->update(['nome' => 'Ficha renomeada']);

        $linhas = app(IniciarAdmissao::class)->executar($colaborador);

        $this->assertSame(['Ficha cadastral', 'Item novo'], $linhas->pluck('item')->all(), 'a cópia mantém o nome de quando foi iniciada');
        $this->assertTrue($linhas->first()->concluido);
    }

    public function test_inicia_so_em_pre_admissao_e_com_itens_configurados(): void
    {
        $iniciar = app(IniciarAdmissao::class);

        try {
            $iniciar->executar($this->colaborador());
            $this->fail('Iniciou sem itens');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Configure', $e->validator->errors()->first('colaborador'));
        }

        $this->itens('Ficha');
        $this->expectException(ValidationException::class);
        $iniciar->executar($this->colaborador(['status' => StatusColaborador::Ativo]));
    }

    public function test_item_automatico_pj_no_uau_e_remuneracao(): void
    {
        $semUau = $this->colaborador(['pessoa_juridica_id' => PessoaJuridica::factory()->create(['uau_codigo' => null])->id]);
        $comUau = $this->colaborador();

        $this->assertFalse($this->avaliar($semUau)[ItensAutomaticos::PJ_NO_UAU]['ok']);
        $this->assertStringContainsString('UAU', $this->avaliar($semUau)[ItensAutomaticos::PJ_NO_UAU]['detalhe']);
        $this->assertTrue($this->avaliar($comUau)[ItensAutomaticos::PJ_NO_UAU]['ok']);

        $this->assertFalse($this->avaliar($comUau)[ItensAutomaticos::REMUNERACAO_DEFINIDA]['ok']);
        Remuneracao::factory()->create(['colaborador_id' => $comUau->id]);
        $this->assertTrue($this->avaliar($comUau)[ItensAutomaticos::REMUNERACAO_DEFINIDA]['ok']);
    }

    public function test_item_automatico_contrato_assinado_so_conta_o_assinado(): void
    {
        $colaborador = $this->colaborador();
        $contrato = Contrato::factory()->gerado('2026/0001')->create(['colaborador_id' => $colaborador->id]);

        $this->assertFalse($this->avaliar($colaborador)[ItensAutomaticos::CONTRATO_ASSINADO]['ok']);

        $contrato->update(['status' => StatusContrato::Assinado]);
        $this->assertTrue($this->avaliar($colaborador)[ItensAutomaticos::CONTRATO_ASSINADO]['ok']);
    }

    public function test_item_automatico_documentos_obrigatorios_por_dono_e_validade(): void
    {
        $colaborador = $this->colaborador();
        $this->assertTrue($this->avaliar($colaborador)[ItensAutomaticos::DOCUMENTOS_OBRIGATORIOS]['ok'], 'sem tipo obrigatório, nada a exigir');

        $rg = DocumentoTipo::factory()->obrigatorioNaAdmissao()->create(['nome' => 'RG', 'aplica_a' => AplicaDocumento::PessoaFisica]);
        $aso = DocumentoTipo::factory()->obrigatorioNaAdmissao()->comValidade()->create(['nome' => 'ASO', 'aplica_a' => AplicaDocumento::Colaborador]);
        DocumentoTipo::factory()->create(['nome' => 'Foto', 'aplica_a' => AplicaDocumento::Todos]);

        $r = $this->avaliar($colaborador)[ItensAutomaticos::DOCUMENTOS_OBRIGATORIOS];
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('RG', $r['detalhe']);
        $this->assertStringContainsString('ASO', $r['detalhe']);
        $this->assertStringNotContainsString('Foto', $r['detalhe']);

        Documento::factory()->create(['documentavel_type' => 'pessoa-fisica', 'documentavel_id' => $colaborador->pessoa_fisica_id, 'documento_tipo_id' => $rg->id, 'tipo' => 'RG']);
        $this->assertStringNotContainsString('RG', $this->avaliar($colaborador)[ItensAutomaticos::DOCUMENTOS_OBRIGATORIOS]['detalhe']);

        $vencido = Documento::factory()->create(['documentavel_type' => 'colaborador', 'documentavel_id' => $colaborador->id, 'documento_tipo_id' => $aso->id, 'tipo' => 'ASO', 'validade' => '2026-11-17']);
        $this->assertFalse($this->avaliar($colaborador)[ItensAutomaticos::DOCUMENTOS_OBRIGATORIOS]['ok'], 'vencido não vale');

        $vencido->update(['validade' => '2026-11-18']);
        $this->assertTrue($this->avaliar($colaborador)[ItensAutomaticos::DOCUMENTOS_OBRIGATORIOS]['ok'], 'a validade é o último dia de uso');
    }

    public function test_documento_do_dono_errado_nao_serve_para_tipo_de_outro_dono(): void
    {
        $colaborador = $this->colaborador();
        $tipo = DocumentoTipo::factory()->obrigatorioNaAdmissao()->create(['aplica_a' => AplicaDocumento::Colaborador]);
        Documento::factory()->create(['documentavel_type' => 'pessoa-fisica', 'documentavel_id' => $colaborador->pessoa_fisica_id, 'documento_tipo_id' => $tipo->id]);

        $this->assertFalse($this->avaliar($colaborador)[ItensAutomaticos::DOCUMENTOS_OBRIGATORIOS]['ok']);
    }

    public function test_item_automatico_qualificacoes_do_cargo(): void
    {
        $cargo = Cargo::factory()->create();
        $nr35 = QualificacaoTipo::factory()->create(['nome' => 'NR-35']);
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => $nr35->id, 'obrigatoria' => true]);
        CargoQualificacao::factory()->create(['cargo_id' => $cargo->id, 'qualificacao_tipo_id' => QualificacaoTipo::factory()->create(['nome' => 'Opcional'])->id, 'obrigatoria' => false]);
        $colaborador = $this->colaborador(['cargo_id' => $cargo->id]);

        $r = $this->avaliar($colaborador)[ItensAutomaticos::QUALIFICACOES_EM_DIA];
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('NR-35', $r['detalhe']);
        $this->assertStringNotContainsString('Opcional', $r['detalhe']);

        $q = ColaboradorQualificacao::factory()->create(['colaborador_id' => $colaborador->id, 'qualificacao_tipo_id' => $nr35->id, 'data_validade' => '2026-11-17']);
        $this->assertStringContainsString('vencida', $this->avaliar($colaborador)[ItensAutomaticos::QUALIFICACOES_EM_DIA]['detalhe']);

        $q->update(['data_validade' => '2027-11-17']);
        $this->assertTrue($this->avaliar($colaborador)[ItensAutomaticos::QUALIFICACOES_EM_DIA]['ok']);
        $this->assertTrue($this->avaliar($this->colaborador(['cargo_id' => null]))[ItensAutomaticos::QUALIFICACOES_EM_DIA]['ok'], 'sem cargo, sem exigência');
    }

    public function test_sincronizar_marca_e_desmarca_automaticos_sem_autor_e_nao_mexe_nos_manuais(): void
    {
        $this->automatico(ItensAutomaticos::REMUNERACAO_DEFINIDA);
        $this->itens('Ficha');
        $colaborador = $this->colaborador();
        $linhas = app(IniciarAdmissao::class)->executar($colaborador);
        $manual = $linhas->firstWhere('chave', null);
        app(MarcarItemAdmissao::class)->executar($manual, true, $this->usuarioCom());

        $auto = fn () => AdmissaoChecklist::where('colaborador_id', $colaborador->id)->where('chave', ItensAutomaticos::REMUNERACAO_DEFINIDA)->first();
        $this->assertFalse($auto()->concluido);

        $remuneracao = Remuneracao::factory()->create(['colaborador_id' => $colaborador->id]);
        app(SincronizarItensAutomaticos::class)->executar($colaborador);
        $this->assertTrue($auto()->concluido);
        $this->assertNull($auto()->concluido_por);
        $this->assertNotNull($auto()->concluido_em);

        $remuneracao->forceDelete();
        app(SincronizarItensAutomaticos::class)->executar($colaborador);
        $this->assertFalse($auto()->concluido);
        $this->assertNull($auto()->concluido_em);
        $this->assertTrue($manual->fresh()->concluido);
    }

    public function test_marca_item_manual_com_autor_e_data_e_recusa_automatico_e_admissao_concluida(): void
    {
        $this->automatico(ItensAutomaticos::PJ_NO_UAU);
        $this->itens('Ficha');
        $colaborador = $this->colaborador();
        $linhas = app(IniciarAdmissao::class)->executar($colaborador);
        $manual = $linhas->firstWhere('chave', null);
        $auto = $linhas->firstWhere('chave', ItensAutomaticos::PJ_NO_UAU);
        $usuario = $this->usuarioCom();
        $marcar = app(MarcarItemAdmissao::class);

        $marcado = $marcar->executar($manual, true, $usuario, '  conferida  ');
        $this->assertSame([true, $usuario->id, 'conferida'], [$marcado->concluido, $marcado->concluido_por, $marcado->observacao]);
        $this->assertSame('2026-11-18 10:00:00', $marcado->concluido_em->toDateTimeString());
        $this->assertTrue(Activity::where('event', 'admissao-item-concluido')->exists());

        $desmarcado = $marcar->executar($manual, false, $usuario);
        $this->assertSame([false, null, null], [$desmarcado->concluido, $desmarcado->concluido_por, $desmarcado->concluido_em]);

        try {
            $marcar->executar($auto, true, $usuario);
            $this->fail('Marcou item automático');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $colaborador->update(['status' => StatusColaborador::Ativo]);
        $this->expectException(ValidationException::class);
        $marcar->executar($manual->fresh(), true, $usuario);
    }

    private function admissaoPronta(?string $remuneracao = '5000'): Colaborador
    {
        $this->itens('Ficha');
        $colaborador = $this->colaborador();
        if ($remuneracao !== null) {
            Remuneracao::factory()->create(['colaborador_id' => $colaborador->id, 'valor_mensal' => $remuneracao, 'vigencia_inicio' => '2026-11-01']);
        }
        $linhas = app(IniciarAdmissao::class)->executar($colaborador);
        app(MarcarItemAdmissao::class)->executar($linhas->first(), true, $this->usuarioCom());

        return $colaborador;
    }

    public function test_conclui_a_admissao_ativa_o_colaborador_e_aplica_os_beneficios_padrao(): void
    {
        $colaborador = $this->admissaoPronta();
        $beneficio = Beneficio::factory()->create();
        BeneficioValor::factory()->create(['beneficio_id' => $beneficio->id, 'valor' => 300, 'vigencia_inicio' => '2026-01-01']);
        CargoBeneficio::factory()->create(['cargo_id' => $colaborador->cargo_id, 'beneficio_id' => $beneficio->id]);

        $concluido = app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'));

        $this->assertSame(StatusColaborador::Ativo, $concluido->status);
        $this->assertSame('2026-11-16', $concluido->data_admissao->toDateString());
        $vinculo = ColaboradorBeneficio::where('colaborador_id', $colaborador->id)->sole();
        $this->assertSame([$beneficio->id, '2026-11-16'], [$vinculo->beneficio_id, $vinculo->inicio->toDateString()]);
        $this->assertTrue(Activity::where('event', 'admissao-concluida')->exists());
    }

    public function test_item_obrigatorio_pendente_barra_a_conclusao_e_lista_quais(): void
    {
        $this->itens('Ficha', 'Foto');
        AdmissaoChecklistItem::factory()->create(['nome' => 'Facultativo', 'obrigatorio' => false, 'ordem' => 9]);
        $colaborador = $this->colaborador();
        Remuneracao::factory()->create(['colaborador_id' => $colaborador->id]);
        $linhas = app(IniciarAdmissao::class)->executar($colaborador);
        app(MarcarItemAdmissao::class)->executar($linhas->firstWhere('item', 'Ficha'), true, $this->usuarioCom());

        try {
            app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'));
            $this->fail('Concluiu com item pendente');
        } catch (ValidationException $e) {
            $mensagem = $e->validator->errors()->first('checklist');
            $this->assertStringContainsString('Foto', $mensagem);
            $this->assertStringNotContainsString('Ficha', $mensagem);
            $this->assertStringNotContainsString('Facultativo', $mensagem);
        }

        $this->assertSame(StatusColaborador::PreAdmissao, $colaborador->fresh()->status);
        $this->assertNull($colaborador->fresh()->data_admissao);
    }

    public function test_item_automatico_pendente_barra_e_ao_resolver_a_conclusao_passa(): void
    {
        $this->automatico(ItensAutomaticos::CONTRATO_ASSINADO);
        $colaborador = $this->colaborador();
        Remuneracao::factory()->create(['colaborador_id' => $colaborador->id]);
        app(IniciarAdmissao::class)->executar($colaborador);

        try {
            app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'));
            $this->fail('Concluiu sem contrato assinado');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Contrato assinado anexado', $e->validator->errors()->first('checklist'));
        }

        Contrato::factory()->gerado('2026/0001')->create(['colaborador_id' => $colaborador->id, 'status' => StatusContrato::Assinado]);

        $this->assertSame(StatusColaborador::Ativo, app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'))->status);
    }

    public function test_remuneracao_inicial_informada_na_conclusao_e_gravada_na_data_da_admissao(): void
    {
        $colaborador = $this->admissaoPronta(remuneracao: null);

        app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'), '4500,00');

        $remuneracao = Remuneracao::where('colaborador_id', $colaborador->id)->sole();
        $this->assertSame(['4500.00', '2026-11-16'], [$remuneracao->valor_mensal, $remuneracao->vigencia_inicio->toDateString()]);
    }

    public function test_sem_remuneracao_nem_valor_informado_a_conclusao_e_recusada_mesmo_com_o_item_opcional(): void
    {
        $this->itens('Ficha');
        $this->automatico(ItensAutomaticos::REMUNERACAO_DEFINIDA, obrigatorio: false);
        $colaborador = $this->colaborador();
        $linhas = app(IniciarAdmissao::class)->executar($colaborador);
        app(MarcarItemAdmissao::class)->executar($linhas->firstWhere('chave', null), true, $this->usuarioCom());

        try {
            app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'));
            $this->fail('Concluiu sem remuneração');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('remuneracao_inicial', $e->errors());
        }

        $this->assertSame(StatusColaborador::PreAdmissao, $colaborador->fresh()->status);
    }

    public function test_item_pendente_desfaz_ate_a_remuneracao_gravada_na_mesma_tentativa(): void
    {
        $this->itens('Ficha');
        $colaborador = $this->colaborador();
        app(IniciarAdmissao::class)->executar($colaborador);

        try {
            app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'), '4500');
            $this->fail('Concluiu com item pendente');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, Remuneracao::where('colaborador_id', $colaborador->id)->count());
    }

    public function test_nao_conclui_sem_iniciar_nem_duas_vezes(): void
    {
        $semInicio = $this->colaborador();
        try {
            app(ConcluirAdmissao::class)->executar($semInicio, Carbon::parse('2026-11-16'));
            $this->fail('Concluiu sem iniciar');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('não foi iniciada', $e->validator->errors()->first('colaborador'));
        }

        $colaborador = $this->admissaoPronta();
        app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-16'));

        $this->expectException(ValidationException::class);
        app(ConcluirAdmissao::class)->executar($colaborador, Carbon::parse('2026-11-17'));
    }

    public function test_fluxo_pela_tela(): void
    {
        $this->itens('Ficha');
        $colaborador = $this->colaborador();
        $this->actingAs($this->usuarioCom('colaborador-list', 'admissao-gerir'));

        $this->get(route('colaborador.show', $colaborador->id))->assertOk()->assertSee('Iniciar admissão');
        $this->postJson(route('admissao.iniciar', $colaborador->id))->assertOk();

        $this->get(route('colaborador.show', $colaborador->id))->assertOk()->assertSee('Ficha')->assertSee('Concluir admissão')->assertSee('Remuneração inicial');

        $this->postJson(route('admissao.concluir', $colaborador->id), ['data_admissao' => '2026-11-16', 'remuneracao_inicial' => '4000'])
            ->assertStatus(422)->assertJsonPath('success', false);

        $linha = AdmissaoChecklist::where('colaborador_id', $colaborador->id)->sole();
        $this->postJson(route('admissao.marcar', $linha->id), ['concluido' => 1])->assertOk();
        $this->postJson(route('admissao.concluir', $colaborador->id), ['data_admissao' => '2026-11-16', 'remuneracao_inicial' => '4000'])->assertOk();

        $this->assertSame(StatusColaborador::Ativo, $colaborador->fresh()->status);
        $this->get(route('colaborador.show', $colaborador->id))->assertOk()->assertSee('Ficha')->assertDontSee('Concluir admissão');
        $this->postJson(route('admissao.concluir', $colaborador->id), [])->assertStatus(422);
    }

    public function test_quem_nao_gere_admissao_nao_ve_a_secao_e_gestor_so_age_na_propria_equipe(): void
    {
        $this->itens('Ficha');
        $colaborador = $this->colaborador();
        app(IniciarAdmissao::class)->executar($colaborador);

        $this->actingAs($this->usuarioCom('colaborador-list'))->get(route('colaborador.show', $colaborador->id))->assertOk()->assertDontSee('Concluir admissão');

        $gestor = $this->usuarioCom('admissao-gerir', 'equipe-gerir');
        $linha = AdmissaoChecklist::where('colaborador_id', $colaborador->id)->sole();
        $this->actingAs($gestor)->postJson(route('admissao.iniciar', $colaborador->id))->assertNotFound();
        $this->postJson(route('admissao.marcar', $linha->id), ['concluido' => 1])->assertNotFound();
        $this->postJson(route('admissao.concluir', $colaborador->id), ['data_admissao' => '2026-11-16'])->assertNotFound();
    }

    public function test_configuracao_dos_itens_pela_tela(): void
    {
        $this->actingAs($this->usuarioCom('admissao-gerir'));
        $automatico = $this->automatico(ItensAutomaticos::PJ_NO_UAU);

        $this->postJson(route('admissao-item.store'), ['nome' => 'Assinar ficha', 'ordem' => 5, 'obrigatorio' => 1])->assertOk();
        $manual = AdmissaoChecklistItem::where('nome', 'Assinar ficha')->sole();
        $this->assertSame([null, true, 5, true], [$manual->chave, $manual->obrigatorio, $manual->ordem, $manual->ativo]);

        $this->postJson(route('admissao-item.store', $automatico->id), ['nome' => 'PJ no UAU', 'ordem' => 1, 'obrigatorio' => 0, 'ativo' => 1])->assertOk();
        $this->assertSame([ItensAutomaticos::PJ_NO_UAU, false, 'PJ no UAU'], [$automatico->fresh()->chave, $automatico->fresh()->obrigatorio, $automatico->fresh()->nome]);

        $this->postJson(route('admissao-item.store'), ['nome' => '', 'ordem' => -1])->assertStatus(422);

        $linha = $this->getJson(route('admissao-item.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data');
        $this->assertSame(['PJ no UAU', 'Assinar ficha'], array_column($linha, 'nome'));
        $this->assertSame(['Sistema', 'RH'], array_column($linha, 'origem'));

        $this->deleteJson(route('admissao-item.delete', $automatico->id))->assertStatus(400);
        $this->deleteJson(route('admissao-item.delete', $manual->id))->assertStatus(201);
        $this->assertSoftDeleted($manual);
    }

    public function test_tipo_de_documento_pode_ser_marcado_como_exigido_na_admissao(): void
    {
        $this->actingAs($this->usuarioCom('documento-tipo-gerir'));

        $this->postJson(route('documento-tipo.store'), ['nome' => 'RG', 'aplica_a' => 'pessoa_fisica', 'exige_validade' => 0, 'obrigatorio_na_admissao' => 1])->assertOk();

        $this->assertTrue(DocumentoTipo::where('nome', 'RG')->firstOrFail()->obrigatorio_na_admissao);
        $this->get(route('documento-tipo.form', DocumentoTipo::where('nome', 'RG')->first()->id))->assertOk()->assertSee('Exigido na admissão');
    }
}
