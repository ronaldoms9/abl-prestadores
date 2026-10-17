<?php

namespace Tests\Feature\Models;

use App\Models\Empresa;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CadastrosUauTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_factory_cria_um_registro_valido_e_created_by_vem_do_usuario_logado(): void
    {
        $usuario = User::factory()->create();
        $this->actingAs($usuario);

        $empresa = Empresa::factory()->create();
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'created_by' => $usuario->id]);

        $obra = Obra::factory()->create();
        $this->assertDatabaseHas('obras', ['id' => $obra->id, 'created_by' => $usuario->id]);

        $pf = PessoaFisica::factory()->create();
        $this->assertDatabaseHas('pessoas_fisicas', ['id' => $pf->id, 'created_by' => $usuario->id]);

        $pj = PessoaJuridica::factory()->create();
        $this->assertDatabaseHas('pessoas_juridicas', ['id' => $pj->id, 'created_by' => $usuario->id]);
    }

    public function test_cpf_e_cnpj_gravados_com_mascara_saem_so_com_digitos_inclusive_com_zero_a_esquerda(): void
    {
        $pf = PessoaFisica::factory()->create([
            'cpf' => '012.345.678-90',
        ]);
        $this->assertSame('01234567890', $pf->cpf);
        $this->assertSame('012.345.678-90', $pf->cpf_formatado);
        $this->assertDatabaseHas('pessoas_fisicas', ['id' => $pf->id, 'cpf' => '01234567890']);

        $pj = PessoaJuridica::factory()->create([
            'cnpj' => '01.234.567/0001-89',
        ]);
        $this->assertSame('01234567000189', $pj->cnpj);
        $this->assertSame('01.234.567/0001-89', $pj->cnpj_formatado);
        $this->assertDatabaseHas('pessoas_juridicas', ['id' => $pj->id, 'cnpj' => '01234567000189']);

        $empresa = Empresa::factory()->create([
            'cnpj' => '01.234.567/0001-89',
        ]);
        $this->assertSame('01234567000189', $empresa->cnpj);
        $this->assertSame('01.234.567/0001-89', $empresa->cnpj_formatado);
        $this->assertDatabaseHas('empresas', ['id' => $empresa->id, 'cnpj' => '01234567000189']);
    }

    public function test_cpf_repetido_falha_mesmo_com_outra_mascara(): void
    {
        PessoaFisica::factory()->create(['cpf' => '12345678901']);

        $this->expectException(UniqueConstraintViolationException::class);
        PessoaFisica::factory()->create(['cpf' => '123.456.789-01']);
    }

    public function test_cpf_repetido_falha_contra_pessoa_excluida_logicamente(): void
    {
        $pf = PessoaFisica::factory()->create(['cpf' => '12345678901']);
        $pf->delete();

        // O índice único vale para linhas excluídas logicamente
        $this->expectException(UniqueConstraintViolationException::class);
        PessoaFisica::factory()->create(['cpf' => '12345678901']);
    }

    public function test_mesmo_uau_codigo_de_obra_em_empresas_diferentes_e_aceito_na_mesma_empresa_falha(): void
    {
        $empresaA = Empresa::factory()->create();
        $empresaB = Empresa::factory()->create();

        $obraA = Obra::factory()->create(['empresa_id' => $empresaA->id, 'uau_codigo' => 'OBR-001']);
        $obraB = Obra::factory()->create(['empresa_id' => $empresaB->id, 'uau_codigo' => 'OBR-001']);

        $this->assertDatabaseHas('obras', ['id' => $obraA->id, 'empresa_id' => $empresaA->id, 'uau_codigo' => 'OBR-001']);
        $this->assertDatabaseHas('obras', ['id' => $obraB->id, 'empresa_id' => $empresaB->id, 'uau_codigo' => 'OBR-001']);

        $this->expectException(UniqueConstraintViolationException::class);
        Obra::factory()->create(['empresa_id' => $empresaA->id, 'uau_codigo' => 'OBR-001']);
    }

    public function test_duas_pessoas_sem_uau_codigo_coexistem(): void
    {
        $pf1 = PessoaFisica::factory()->semCodigoUau()->create();
        $pf2 = PessoaFisica::factory()->semCodigoUau()->create();

        $this->assertNull($pf1->uau_codigo);
        $this->assertNull($pf2->uau_codigo);
        $this->assertDatabaseCount('pessoas_fisicas', 2);

        $pj1 = PessoaJuridica::factory()->semCodigoUau()->create();
        $pj2 = PessoaJuridica::factory()->semCodigoUau()->create();

        $this->assertNull($pj1->uau_codigo);
        $this->assertNull($pj2->uau_codigo);
        $this->assertDatabaseCount('pessoas_juridicas', 2);
    }

    public function test_relacoes_empresa_obras_e_user_pessoafisica(): void
    {
        $empresa = Empresa::factory()->create();
        $obra = Obra::factory()->create(['empresa_id' => $empresa->id]);

        $this->assertTrue($empresa->obras->contains($obra));
        $this->assertTrue($obra->empresa->is($empresa));

        $pf = PessoaFisica::factory()->create();
        $user = User::factory()->create(['pessoa_fisica_id' => $pf->id]);

        $this->assertTrue($user->pessoaFisica->is($pf));
        $this->assertTrue($pf->usuario->is($user));
    }

    public function test_scopes_ativos_e_inativos(): void
    {
        Empresa::factory()->create(['ativo' => true]);
        Empresa::factory()->inativo()->create();

        $this->assertCount(1, Empresa::ativos()->get());
        $this->assertCount(1, Empresa::inativos()->get());

        Obra::factory()->create(['ativo' => true]);
        Obra::factory()->inativo()->create();

        $this->assertCount(1, Obra::ativos()->get());
        $this->assertCount(1, Obra::inativos()->get());

        PessoaFisica::factory()->create(['ativo' => true]);
        PessoaFisica::factory()->inativo()->create();

        $this->assertCount(1, PessoaFisica::ativos()->get());
        $this->assertCount(1, PessoaFisica::inativos()->get());

        PessoaJuridica::factory()->create(['ativo' => true]);
        PessoaJuridica::factory()->inativo()->create();

        $this->assertCount(1, PessoaJuridica::ativos()->get());
        $this->assertCount(1, PessoaJuridica::inativos()->get());

        User::factory()->create(['ativo' => true]);
        User::factory()->inativo()->create();

        $this->assertCount(1, User::ativos()->get());
        $this->assertCount(1, User::inativos()->get());
    }

    public function test_excluir_empresa_com_obra_falha_na_fk(): void
    {
        $empresa = Empresa::factory()->create();
        Obra::factory()->create(['empresa_id' => $empresa->id]);

        $this->expectException(QueryException::class);
        // Exclusão física (forceDelete) deve falhar pela restrição restrictOnDelete
        $empresa->forceDelete();
    }
}
