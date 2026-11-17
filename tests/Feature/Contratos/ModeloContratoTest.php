<?php

namespace Tests\Feature\Contratos;

use App\Actions\Contratos\SalvarContratoModelo;
use App\Enums\TipoContrato;
use App\Models\Contrato;
use App\Models\ContratoModelo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ModeloContratoTest extends TestCase
{
    use RefreshDatabase;

    private function dados(array $extra = []): array
    {
        return $extra + ['nome' => 'Contrato padrão', 'tipo' => 'contrato', 'corpo_html' => '<p>{{pj.razao_social}} e {{colaborador.nome}}</p>'];
    }

    public function test_cria_modelo_na_versao_1_com_corpo_limpo(): void
    {
        $modelo = app(SalvarContratoModelo::class)->executar(null, $this->dados(['corpo_html' => '<p onclick="x()">{{colaborador.nome}}</p><script>x</script>']));

        $this->assertSame(1, $modelo->versao);
        $this->assertSame('<p>{{colaborador.nome}}</p>', $modelo->corpo_html);
        $this->assertTrue($modelo->ativo);
        $this->assertTrue(Activity::where('event', 'contrato-modelo-criado')->exists());
    }

    public function test_variavel_desconhecida_bloqueia_o_salvamento_e_diz_qual(): void
    {
        try {
            app(SalvarContratoModelo::class)->executar(null, $this->dados(['corpo_html' => '<p>{{colaborador.nome}} {{pj.cnpj_errado}}</p>']));
            $this->fail('Salvou com variável desconhecida');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('{{pj.cnpj_errado}}', $e->validator->errors()->first('corpo_html'));
        }

        $this->assertSame(0, ContratoModelo::count());
    }

    public function test_corpo_vazio_e_recusado(): void
    {
        $this->expectException(ValidationException::class);
        app(SalvarContratoModelo::class)->executar(null, $this->dados(['corpo_html' => '<script>x</script>']));
    }

    public function test_modelo_sem_uso_e_alterado_no_lugar(): void
    {
        $modelo = ContratoModelo::factory()->create();

        $salvo = app(SalvarContratoModelo::class)->executar($modelo, $this->dados(['corpo_html' => '<p>Novo {{colaborador.nome}}</p>']));

        $this->assertTrue($salvo->is($modelo));
        $this->assertSame(1, $salvo->versao);
        $this->assertSame(1, ContratoModelo::count());
        $this->assertStringContainsString('Novo', $modelo->fresh()->corpo_html);
    }

    public function test_modelo_em_uso_gera_nova_versao_e_o_contrato_antigo_continua_na_versao_usada(): void
    {
        $v1 = ContratoModelo::factory()->create(['nome' => 'Contrato padrão']);
        $contrato = Contrato::factory()->create(['contrato_modelo_id' => $v1->id]);

        $v2 = app(SalvarContratoModelo::class)->executar($v1, $this->dados(['corpo_html' => '<p>Texto novo {{colaborador.nome}}</p>']));

        $this->assertFalse($v2->is($v1));
        $this->assertSame(2, $v2->versao);
        $this->assertSame($v1->id, $v2->modelo_anterior_id);
        $this->assertFalse($v1->fresh()->ativo);
        $this->assertTrue($v2->fresh()->ativo);
        $this->assertSame($v1->id, $contrato->fresh()->contrato_modelo_id);
        $this->assertStringContainsString('{{pj.razao_social}}', $v1->fresh()->corpo_html, 'a versão antiga não muda');
        $this->assertTrue(Activity::where('event', 'contrato-modelo-versionado')->exists());

        // A versão 2 ainda não gerou contrato: editar de novo mexe nela mesma.
        $v2b = app(SalvarContratoModelo::class)->executar($v2, $this->dados(['corpo_html' => '<p>Ajuste {{colaborador.nome}}</p>']));
        $this->assertTrue($v2b->is($v2));
        $this->assertSame(2, ContratoModelo::count());
    }

    public function test_so_a_versao_nova_fica_ativa_e_a_versao_sobe_de_um_em_um(): void
    {
        $v1 = ContratoModelo::factory()->create(['nome' => 'Padrão']);
        Contrato::factory()->create(['contrato_modelo_id' => $v1->id]);
        $v2 = app(SalvarContratoModelo::class)->executar($v1, $this->dados(['nome' => 'Padrão', 'corpo_html' => '<p>V2 {{colaborador.nome}}</p>']));
        Contrato::factory()->create(['contrato_modelo_id' => $v2->id]);
        $v3 = app(SalvarContratoModelo::class)->executar($v2, $this->dados(['nome' => 'Padrão', 'corpo_html' => '<p>V3 {{colaborador.nome}}</p>']));

        $this->assertSame(2, $v2->versao);
        $this->assertSame(3, $v3->versao);
        $this->assertSame([$v3->id], ContratoModelo::ativos()->pluck('id')->all());
    }

    public function test_alternar_ativo_de_modelo_em_uso_nao_cria_versao(): void
    {
        $modelo = ContratoModelo::factory()->create(['nome' => 'Padrão', 'corpo_html' => '<p>{{colaborador.nome}}</p>']);
        Contrato::factory()->create(['contrato_modelo_id' => $modelo->id]);

        $salvo = app(SalvarContratoModelo::class)->executar($modelo, ['nome' => 'Padrão', 'tipo' => 'contrato', 'corpo_html' => '<p>{{colaborador.nome}}</p>', 'ativo' => false]);

        $this->assertTrue($salvo->is($modelo));
        $this->assertFalse($modelo->fresh()->ativo);
        $this->assertSame(1, ContratoModelo::count());
    }

    public function test_tipo_de_modelo_em_uso_nao_muda(): void
    {
        $modelo = ContratoModelo::factory()->create();
        Contrato::factory()->create(['contrato_modelo_id' => $modelo->id]);

        $this->expectException(ValidationException::class);
        app(SalvarContratoModelo::class)->executar($modelo, $this->dados(['tipo' => 'aditivo']));
    }

    public function test_nome_ativo_repetido_no_mesmo_tipo_e_recusado_mas_vale_em_outro_tipo(): void
    {
        ContratoModelo::factory()->create(['nome' => 'Padrão', 'tipo' => TipoContrato::Contrato]);

        app(SalvarContratoModelo::class)->executar(null, $this->dados(['nome' => 'Padrão', 'tipo' => 'aditivo']));
        $this->assertSame(2, ContratoModelo::count());

        $this->expectException(ValidationException::class);
        app(SalvarContratoModelo::class)->executar(null, $this->dados(['nome' => 'Padrão', 'tipo' => 'contrato']));
    }

    public function test_telas_lista_edita_previsualiza_e_exclui(): void
    {
        $this->actingAs($this->usuarioCom('contrato-modelo-gerir'));

        $resposta = $this->postJson(route('contrato-modelo.store'), $this->dados())->assertOk();
        $modelo = ContratoModelo::firstOrFail();
        $resposta->assertJsonPath('data.id', $modelo->id);

        $linha = $this->getJson(route('contrato-modelo.index'), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()->json('data.0');
        $this->assertSame(['Contrato padrão', 'Contrato', 1, 0], [$linha['nome'], $linha['tipo'], $linha['versao'], $linha['contratos']]);

        $this->get(route('contrato-modelo.form', $modelo->id))->assertOk()->assertSee('Contrato padrão')->assertSee('Razão social');

        $previa = $this->postJson(route('contrato-modelo.previa'), ['corpo_html' => '<p>{{colaborador.nome}} {{nada.disso}}<script>x</script></p>'])->assertOk()->json('data');
        $this->assertStringContainsString('Maria da Silva', $previa['html']);
        $this->assertStringNotContainsString('<script', $previa['html']);
        $this->assertSame(['nada.disso'], $previa['desconhecidas']);

        $this->postJson(route('contrato-modelo.store', $modelo->id), $this->dados(['corpo_html' => '<p>{{nada.disso}}</p>']))->assertStatus(422);

        $this->deleteJson(route('contrato-modelo.delete', $modelo->id))->assertStatus(201);
        $this->assertSoftDeleted($modelo);
    }

    public function test_nao_exclui_modelo_que_gerou_contratos(): void
    {
        $modelo = ContratoModelo::factory()->create();
        Contrato::factory()->create(['contrato_modelo_id' => $modelo->id]);

        $this->actingAs($this->usuarioCom('contrato-modelo-gerir'))->deleteJson(route('contrato-modelo.delete', $modelo->id))->assertStatus(400);
        $this->assertNotSoftDeleted($modelo);
    }
}
