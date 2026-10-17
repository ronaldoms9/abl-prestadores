<?php

namespace Tests\Feature\Uau;

use App\Actions\Uau\ImportarPessoa;
use App\Actions\Uau\ResultadoPessoa;
use App\Enums\ResultadoImportacao;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use App\Models\IntegracaoLog;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\UauSimulado;
use Tests\TestCase;

class ImportarPessoaTest extends TestCase
{
    use RefreshDatabase, UauSimulado;

    public function test_importada_e_depois_ja_existia(): void
    {
        $this->simularUau();

        $primeira = $this->importar('529.982.247-25');

        $this->assertSame(ResultadoImportacao::Importada, $primeira->resultado);
        $this->assertInstanceOf(PessoaFisica::class, $primeira->pessoa);
        $this->assertTrue($primeira->pessoa->ativo);
        $this->assertNotNull($primeira->pessoa->sincronizado_em);

        $segunda = $this->importar('52998224725');

        $this->assertSame(ResultadoImportacao::JaExistia, $segunda->resultado);
        $this->assertTrue($segunda->pessoa->is($primeira->pessoa));
        $this->assertSame(1, PessoaFisica::count());
    }

    public function test_inativa_no_uau_grava_com_ativo_false(): void
    {
        $this->simularUau();

        $resultado = $this->importar('74620153826');

        $this->assertSame(ResultadoImportacao::Inativa, $resultado->resultado);
        $this->assertFalse($resultado->pessoa->ativo);
        $this->assertDatabaseHas('pessoas_fisicas', ['cpf' => '74620153826', 'ativo' => false]);

        $existente = $this->importar('74620153826');
        $this->assertSame(ResultadoImportacao::Inativa, $existente->resultado, 'inativa vale também para quem já existia');
        $this->assertSame(1, PessoaFisica::count());
    }

    public function test_nao_encontrada(): void
    {
        $this->simularUau();

        $resultado = $this->importar('11144477735');

        $this->assertSame(ResultadoImportacao::NaoEncontrada, $resultado->resultado);
        $this->assertNull($resultado->pessoa);
        $this->assertSame(0, PessoaFisica::count());
    }

    public function test_invalida_sem_chamar_o_uau(): void
    {
        $uau = $this->simularUau();

        foreach (['123', '', 'abc', '5299822472', '275431980001060'] as $documento) {
            $resultado = $this->importar($documento);
            $this->assertSame(ResultadoImportacao::Invalida, $resultado->resultado, "documento '{$documento}'");
            $this->assertNull($resultado->pessoa);
        }

        $this->assertSame([], $uau->chamadas());
        $this->assertSame(0, IntegracaoLog::count());
    }

    public function test_endereco_principal_e_telefone_celular(): void
    {
        $this->simularUau();

        $pessoa = $this->importar('52998224725')->pessoa->fresh();

        $this->assertSame('Mariana Alves Ribeiro', $pessoa->nome);
        $this->assertSame('1988-05-17', $pessoa->nascimento->toDateString());
        $this->assertSame('Rua T-37', $pessoa->endereco->logradouro);
        $this->assertSame('74230020', $pessoa->endereco->cep);
        $this->assertSame('62991234567', $pessoa->telefone);
    }

    public function test_cpf_mascarado_no_uau_e_gravado_so_com_digitos(): void
    {
        $this->simularUau();

        $resultado = $this->importar('03174682940');

        $this->assertSame(ResultadoImportacao::Importada, $resultado->resultado);
        $this->assertDatabaseHas('pessoas_fisicas', ['cpf' => '03174682940', 'uau_codigo' => 1003]);
    }

    public function test_pj_vai_para_pessoas_juridicas(): void
    {
        $this->simularUau();

        $resultado = $this->importar('27.543.198/0001-06');

        $this->assertSame(ResultadoImportacao::Importada, $resultado->resultado);
        $this->assertInstanceOf(PessoaJuridica::class, $resultado->pessoa);
        $pj = $resultado->pessoa->fresh();
        $this->assertSame('27543198000106', $pj->cnpj);
        $this->assertSame('M A Ribeiro Serviços de Engenharia Ltda', $pj->razao_social);
        $this->assertSame('MAR Engenharia', $pj->nome_fantasia);
        $this->assertSame('ISENTO', $pj->inscricao_estadual);
        $this->assertSame('Sala 304', $pj->endereco->complemento);
        $this->assertSame(0, PessoaFisica::count());
    }

    public function test_pj_nao_toca_o_regime_tributario(): void
    {
        $this->simularUau();
        $pj = PessoaJuridica::factory()->create(['cnpj' => '27543198000106', 'regime_tributario' => 'simples_nacional']);

        $this->importar('27543198000106');

        $this->assertSame('simples_nacional', $pj->fresh()->regime_tributario);
    }

    public function test_documento_corrigido_no_uau_atualiza_pelo_codigo_sem_duplicar(): void
    {
        $this->simularUau();
        $antiga = PessoaFisica::factory()->create(['uau_codigo' => 1001, 'cpf' => '11144477735']);

        $resultado = $this->importar('52998224725');

        $this->assertSame(ResultadoImportacao::JaExistia, $resultado->resultado);
        $this->assertTrue($resultado->pessoa->is($antiga));
        $this->assertSame('52998224725', $antiga->fresh()->cpf);
        $this->assertSame(1, PessoaFisica::count());
    }

    public function test_pessoa_que_sumiu_do_uau_vira_inativa(): void
    {
        $this->simularUau();
        $this->importar('52998224725');

        $this->simularUau(['pessoas/52998224725.json' => null]);
        $resultado = $this->importar('52998224725');

        $this->assertSame(ResultadoImportacao::NaoEncontrada, $resultado->resultado);
        $this->assertFalse($resultado->pessoa->ativo);
        $this->assertFalse(PessoaFisica::sole()->ativo);
    }

    public function test_excluida_que_o_uau_nao_encontra_fica_como_esta(): void
    {
        $this->simularUau();
        $pessoa = PessoaFisica::factory()->create(['cpf' => '11144477735']);
        $pessoa->delete();

        $this->importar('11144477735');

        $this->assertTrue($pessoa->fresh()->ativo);
        $this->assertTrue($pessoa->fresh()->trashed());
    }

    public function test_excluida_encontrada_no_uau_e_restaurada(): void
    {
        $this->simularUau();
        $pessoa = PessoaFisica::factory()->create(['cpf' => '52998224725']);
        $pessoa->delete();

        $resultado = $this->importar('52998224725');

        $this->assertSame(ResultadoImportacao::JaExistia, $resultado->resultado);
        $this->assertFalse($pessoa->fresh()->trashed());
        $this->assertSame('Mariana Alves Ribeiro', $pessoa->fresh()->nome);
    }

    public function test_resposta_de_outra_pessoa_e_rejeitada_sem_gravar(): void
    {
        $this->simularUau(['pessoas/52998224725.json' => $this->dadosUau('pessoas/74620153826.json')]);

        try {
            $this->importar('52998224725');
            $this->fail('Deveria rejeitar a resposta.');
        } catch (UauRejeitou $e) {
            $this->assertStringContainsString('outra pessoa', $e->getMessage());
        }

        $this->assertSame(0, PessoaFisica::count());
    }

    public function test_corrida_na_criacao_busca_de_novo_e_grava_uma_vez(): void
    {
        $this->simularUau();
        $corrida = true;

        // Simula outro processo gravando o mesmo CPF entre a busca e a gravação.
        PessoaFisica::creating(function () use (&$corrida) {
            if ($corrida) {
                $corrida = false;
                DB::table('pessoas_fisicas')->insert(['cpf' => '52998224725', 'nome' => 'Outro processo', 'ativo' => true]);
            }
        });

        $resultado = $this->importar('52998224725');

        $this->assertFalse($corrida, 'a corrida aconteceu');
        $this->assertNotSame(ResultadoImportacao::Invalida, $resultado->resultado);
        $this->assertSame(1, PessoaFisica::count());
        $this->assertSame('Mariana Alves Ribeiro', PessoaFisica::sole()->nome);
    }

    public function test_log_aponta_para_a_pessoa_local_com_a_chave(): void
    {
        $this->simularUau();
        $pessoa = PessoaFisica::factory()->create(['cpf' => '52998224725']);

        $this->importar('52998224725', tentativa: 2);

        $log = IntegracaoLog::sole();
        $this->assertSame('pessoa-fisica', $log->entidade_type);
        $this->assertSame($pessoa->id, (int) $log->entidade_id);
        $this->assertSame('pessoa:52998224725', $log->chave_idempotencia);
        $this->assertSame(2, $log->tentativas);
    }

    private function importar(string $documento, int $tentativa = 1): ResultadoPessoa
    {
        return app(ImportarPessoa::class)->executar($documento, $tentativa);
    }
}
