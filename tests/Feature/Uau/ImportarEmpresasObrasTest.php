<?php

namespace Tests\Feature\Uau;

use App\Actions\Uau\ImportacaoSuspensa;
use App\Actions\Uau\ImportarEmpresas;
use App\Actions\Uau\ImportarObras;
use App\Actions\Uau\ResumoImportacao;
use App\Integrations\Uau\Exceptions\UauIndisponivel;
use App\Integrations\Uau\OperacaoUau;
use App\Models\Empresa;
use App\Models\Obra;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Concerns\UauSimulado;
use Tests\TestCase;

class ImportarEmpresasObrasTest extends TestCase
{
    use RefreshDatabase, UauSimulado;

    public function test_primeira_carga(): void
    {
        $this->simularUau();
        Log::spy();

        $empresas = $this->importarEmpresas();
        $obras = $this->importarObras();

        $this->assertSame('3 criadas, 0 atualizadas, 0 inativadas', (string) $empresas);
        $this->assertSame('3 criadas, 0 atualizadas, 0 inativadas, 1 ignorada: 7/OB09', (string) $obras);
        $this->assertSame(3, Empresa::count());
        $this->assertSame(3, Obra::count());

        $this->assertSame('04252011000110', Empresa::where('uau_codigo', 2)->sole()->cnpj, 'CNPJ com zero à esquerda');
        $this->assertSame(
            ['Construtora Horizonte Ltda', 'Incorporadora Cerrado SPE Ltda'],
            Obra::where('uau_codigo', 'OB01')->with('empresa')->get()->pluck('empresa.razao_social')->sort()->values()->all(),
            'OB01 existe nas empresas 1 e 2',
        );
        $this->assertSame(0, Empresa::whereNull('sincronizado_em')->count() + Obra::whereNull('sincronizado_em')->count());
        $this->assertSame(0, Empresa::where('ativo', false)->count() + Obra::where('ativo', false)->count());

        $obra = Obra::where('uau_codigo', 'OB02')->sole();
        $this->assertSame('1', $obra->tipo_uau);
        $this->assertNull($obra->data_fim);
        $this->assertSame('Av. Perimetral Norte, 3000, Goiânia - GO', $obra->endereco->logradouro);

        Log::shouldHaveReceived('warning')->once()->withArgs(fn ($mensagem) => str_contains($mensagem, '7/OB09'));
    }

    public function test_repetir_nao_duplica(): void
    {
        $this->simularUau();
        $this->importarEmpresas();
        $this->importarObras();
        $this->travel(1)->minute();

        $empresas = $this->importarEmpresas();
        $obras = $this->importarObras();

        $this->assertSame('0 criadas, 0 atualizadas, 0 inativadas', (string) $empresas);
        $this->assertSame('0 criadas, 0 atualizadas, 0 inativadas, 1 ignorada: 7/OB09', (string) $obras);
        $this->assertSame(3, Empresa::count());
        $this->assertSame(3, Obra::count());
        $this->assertSame(3, Empresa::where('sincronizado_em', '>', now()->subSeconds(30))->count(), 'só sincronizado_em muda');
    }

    public function test_endereco_igual_com_json_em_outra_ordem_nao_conta_como_alterado(): void
    {
        $this->simularUau();
        $this->importarEmpresas();

        // O MySQL devolve o JSON com as chaves em outra ordem.
        $empresa = Empresa::where('uau_codigo', 1)->sole();
        $reordenado = collect($empresa->endereco->toArray())->sortKeys()->toJson(JSON_UNESCAPED_UNICODE);
        DB::table('empresas')->where('id', $empresa->id)->update(['endereco' => $reordenado]);

        $this->assertSame(0, $this->importarEmpresas()->atualizadas);
    }

    public function test_alteracao_no_uau_atualiza_a_empresa(): void
    {
        $this->simularUau();
        $this->importarEmpresas();

        $lista = $this->dadosUau('empresas-ativas.json');
        $lista[0]['Desc_emp'] = 'Construtora Horizonte S.A.';
        $lista[1]['IE_emp'] = '998877';
        $this->simularUau(['empresas-ativas.json' => $lista]);

        $resumo = $this->importarEmpresas();

        $this->assertSame(2, $resumo->atualizadas);
        $this->assertSame('Construtora Horizonte S.A.', Empresa::where('uau_codigo', 1)->sole()->razao_social);
        $this->assertSame('998877', Empresa::where('uau_codigo', 2)->sole()->inscricao_estadual);
        $this->assertSame(3, Empresa::count());
    }

    public function test_empresa_que_sumiu_vira_inativa_e_volta_ao_reaparecer(): void
    {
        $this->simularUau();
        $this->importarEmpresas();

        $lista = $this->dadosUau('empresas-ativas.json');
        $this->simularUau(['empresas-ativas.json' => array_slice($lista, 0, 2)]);
        $resumo = $this->importarEmpresas();

        $this->assertSame(1, $resumo->inativadas);
        $empresa3 = Empresa::where('uau_codigo', 3)->sole();
        $this->assertFalse($empresa3->ativo);
        $this->assertNotNull($empresa3->sincronizado_em);

        $this->simularUau();
        $resumo = $this->importarEmpresas();

        $this->assertSame(1, $resumo->reativadas);
        $this->assertTrue($empresa3->fresh()->ativo);
    }

    public function test_excluida_que_volta_e_restaurada_e_excluida_fora_da_lista_nao_e_tocada(): void
    {
        $this->simularUau();
        $this->importarEmpresas();
        Empresa::where('uau_codigo', 2)->sole()->delete();
        $excluidaForaDaLista = Empresa::factory()->create(['uau_codigo' => 50]);
        $excluidaForaDaLista->delete();

        $resumo = $this->importarEmpresas();

        $this->assertSame(1, $resumo->reativadas);
        $this->assertSame(0, $resumo->inativadas, 'excluída não se inativa');
        $this->assertFalse(Empresa::withTrashed()->where('uau_codigo', 2)->sole()->trashed());
        $this->assertSame(3, Empresa::count());

        $excluidaForaDaLista->refresh();
        $this->assertTrue($excluidaForaDaLista->trashed());
        $this->assertTrue($excluidaForaDaLista->ativo);
    }

    public function test_lista_vazia_com_empresas_ativas_suspende_sem_mudar_nada(): void
    {
        $this->simularUau();
        $this->importarEmpresas();
        $antes = $this->linhas('empresas');

        $this->simularUau(['empresas-ativas.json' => []]);

        try {
            $this->importarEmpresas();
            $this->fail('Deveria suspender a importação.');
        } catch (ImportacaoSuspensa $e) {
            $this->assertStringContainsString('lista vazia', $e->getMessage());
        }

        $this->assertSame($antes, $this->linhas('empresas'));
    }

    public function test_lista_vazia_sem_ativos_locais_nao_suspende(): void
    {
        $this->simularUau(['empresas-ativas.json' => [], 'obras-ativas.json' => []]);

        $this->assertSame('0 criadas, 0 atualizadas, 0 inativadas', (string) $this->importarEmpresas());
        $this->assertSame('0 criadas, 0 atualizadas, 0 inativadas', (string) $this->importarObras());
    }

    public function test_lista_que_desativaria_mais_da_metade_suspende(): void
    {
        $this->simularUau();
        $this->importarEmpresas();

        $lista = $this->dadosUau('empresas-ativas.json');
        $this->simularUau(['empresas-ativas.json' => [$lista[0]]]);

        try {
            $this->importarEmpresas();
            $this->fail('Deveria suspender a importação.');
        } catch (ImportacaoSuspensa $e) {
            $this->assertStringContainsString('desativaria 2 de 3', $e->getMessage());
        }

        $this->assertSame(3, Empresa::ativos()->count());

        config(['uau.limite_inativacao' => 1.0]);
        $this->assertSame(2, $this->importarEmpresas()->inativadas, 'com o limite liberado, a lista vale');
    }

    public function test_lista_vazia_de_obras_suspende(): void
    {
        $this->simularUau();
        $this->importarEmpresas();
        $this->importarObras();

        $this->simularUau(['obras-ativas.json' => []]);

        $this->expectException(ImportacaoSuspensa::class);

        try {
            $this->importarObras();
        } finally {
            $this->assertSame(3, Obra::ativos()->count());
        }
    }

    public function test_obra_sem_empresa_nao_cria_empresa_e_nao_impede_as_outras(): void
    {
        $this->simularUau();
        $this->importarEmpresas();

        $resumo = $this->importarObras();

        $this->assertSame(['7/OB09'], $resumo->ignoradas);
        $this->assertNull(Empresa::withTrashed()->where('uau_codigo', 7)->first());
        $this->assertSame(3, Obra::count());
    }

    public function test_inativa_so_as_obras_das_empresas_que_vieram_na_lista(): void
    {
        $this->simularUau();
        $this->importarEmpresas();
        $this->importarObras();
        $empresa3 = Empresa::where('uau_codigo', 3)->sole();
        $obraDaEmpresa3 = Obra::factory()->for($empresa3)->create(['uau_codigo' => 'OB30']);

        // OB02 (empresa 1) some; a empresa 3 não tem obra na lista.
        $obras = array_values(array_filter(
            $this->dadosUau('obras-ativas.json'),
            fn ($obra) => $obra['Cod_obr'] !== 'OB02',
        ));
        $this->simularUau(['obras-ativas.json' => $obras]);

        $resumo = $this->importarObras();

        $this->assertSame(1, $resumo->inativadas);
        $this->assertFalse(Obra::where('uau_codigo', 'OB02')->sole()->ativo);
        $this->assertTrue($obraDaEmpresa3->fresh()->ativo, 'empresa fora da lista de obras não perde obras');
    }

    public function test_obra_inativa_volta_e_excluida_e_restaurada(): void
    {
        $this->simularUau();
        $this->importarEmpresas();
        $this->importarObras();
        Obra::where('uau_codigo', 'OB02')->update(['ativo' => false]);
        Obra::where('uau_codigo', 'OB01')->whereHas('empresa', fn ($q) => $q->where('uau_codigo', 2))->sole()->delete();

        $resumo = $this->importarObras();

        $this->assertSame(2, $resumo->reativadas);
        $this->assertSame(3, Obra::ativos()->count());
    }

    public function test_falha_do_uau_sobe_e_nao_altera_o_banco(): void
    {
        $this->simularUau();
        $this->importarEmpresas();
        $antes = $this->linhas('empresas');

        $this->simularUau(['empresas-ativas.json' => []])
            ->falharEm(OperacaoUau::EmpresasAtivas, new UauIndisponivel('UAU fora do ar.', 503));

        try {
            $this->importarEmpresas();
            $this->fail('A falha do UAU deveria subir.');
        } catch (UauIndisponivel) {
        }

        $this->assertSame($antes, $this->linhas('empresas'));
        $this->assertDatabaseHas('integracao_logs', ['operacao' => 'empresas-ativas', 'status' => 'erro', 'http_status' => 503]);
    }

    public function test_tentativa_vai_para_o_log(): void
    {
        $this->simularUau();

        app(ImportarEmpresas::class)->executar(tentativa: 3);

        $this->assertDatabaseHas('integracao_logs', ['operacao' => 'empresas-ativas', 'tentativas' => 3]);
    }

    /** Linhas cruas da tabela, para comparar antes e depois. */
    private function linhas(string $tabela): array
    {
        return DB::table($tabela)->orderBy('id')->get()->map(fn ($linha) => (array) $linha)->all();
    }

    private function importarEmpresas(): ResumoImportacao
    {
        return app(ImportarEmpresas::class)->executar();
    }

    private function importarObras(): ResumoImportacao
    {
        return app(ImportarObras::class)->executar();
    }
}
