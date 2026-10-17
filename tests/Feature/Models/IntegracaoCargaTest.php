<?php

namespace Tests\Feature\Models;

use App\Enums\DirecaoIntegracao;
use App\Enums\ResultadoImportacao;
use App\Enums\StatusImportacao;
use App\Enums\StatusIntegracao;
use App\Models\Empresa;
use App\Models\ImportacaoPessoa;
use App\Models\ImportacaoPessoaLinha;
use App\Models\IntegracaoLog;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\UauMapeamento;
use App\Models\VinculoSugerido;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class IntegracaoCargaTest extends TestCase
{
    use RefreshDatabase;

    public function test_cada_factory_cria_um_registro_valido(): void
    {
        $mapeamento = UauMapeamento::factory()->create();
        $this->assertDatabaseHas('uau_mapeamentos', ['id' => $mapeamento->id]);

        $log = IntegracaoLog::factory()->create();
        $this->assertDatabaseHas('integracao_logs', ['id' => $log->id]);

        $importacao = ImportacaoPessoa::factory()->create();
        $this->assertDatabaseHas('importacoes_pessoas', ['id' => $importacao->id]);

        $linha = ImportacaoPessoaLinha::factory()->create();
        $this->assertDatabaseHas('importacao_pessoa_linhas', ['id' => $linha->id]);

        $vinculo = VinculoSugerido::factory()->create();
        $this->assertDatabaseHas('vinculos_sugeridos', ['id' => $vinculo->id]);
    }

    public function test_integracao_log_enums_json_e_morph_map(): void
    {
        $empresa = Empresa::factory()->create();

        $log = IntegracaoLog::factory()
            ->paraEntidade($empresa)
            ->create([
                'direcao' => DirecaoIntegracao::Entrada,
                'status' => StatusIntegracao::Sucesso,
                'payload' => ['teste' => 123],
                'resposta' => ['ok' => true],
            ]);

        $this->assertInstanceOf(DirecaoIntegracao::class, $log->direcao);
        $this->assertSame(DirecaoIntegracao::Entrada, $log->direcao);

        $this->assertInstanceOf(StatusIntegracao::class, $log->status);
        $this->assertSame(StatusIntegracao::Sucesso, $log->status);

        $this->assertSame(['teste' => 123], $log->payload);
        $this->assertSame(['ok' => true], $log->resposta);

        // Morphed type deve ser o apelido definido no Relation::enforceMorphMap ('empresa')
        $this->assertSame('empresa', $log->entidade_type);
        $this->assertDatabaseHas('integracao_logs', [
            'id' => $log->id,
            'entidade_type' => 'empresa',
            'entidade_id' => $empresa->id,
        ]);
        $this->assertTrue($log->entidade->is($empresa));
    }

    public function test_integracao_log_guarda_milissegundos_em_executado_em(): void
    {
        $log = IntegracaoLog::factory()->create(['executado_em' => Carbon::parse('2026-10-17 10:00:00.123')]);

        $this->assertSame('10:00:00.123', $log->fresh()->executado_em->format('H:i:s.v'));
    }

    public function test_integracao_log_escopos_falhas_e_no_periodo(): void
    {
        IntegracaoLog::factory()->sucesso()->create(['executado_em' => now()->subDays(2)]);
        $falha1 = IntegracaoLog::factory()->erro()->create(['executado_em' => now()->subDay()]);
        $falha2 = IntegracaoLog::factory()->erro()->create(['executado_em' => now()]);

        $this->assertCount(2, IntegracaoLog::falhas()->get());

        $noPeriodo = IntegracaoLog::noPeriodo(now()->subDays(3), now()->subHours(12))->get();
        $this->assertCount(2, $noPeriodo);
    }

    public function test_excluir_importacao_apaga_linhas_e_anula_vinculo(): void
    {
        $importacao = ImportacaoPessoa::factory()->create();
        $linha = ImportacaoPessoaLinha::factory()->create(['importacao_pessoa_id' => $importacao->id]);
        $vinculo = VinculoSugerido::factory()->create(['importacao_pessoa_id' => $importacao->id]);

        $this->assertDatabaseHas('importacao_pessoa_linhas', ['id' => $linha->id]);
        $this->assertDatabaseHas('vinculos_sugeridos', ['id' => $vinculo->id, 'importacao_pessoa_id' => $importacao->id]);

        // Exclusão física da importação
        $importacao->forceDelete();

        // Linha apagada em cascata
        $this->assertDatabaseMissing('importacao_pessoa_linhas', ['id' => $linha->id]);

        // Vínculo preservado, com importacao_pessoa_id = null (nullOnDelete)
        $this->assertDatabaseHas('vinculos_sugeridos', ['id' => $vinculo->id, 'importacao_pessoa_id' => null]);
    }

    public function test_mesmo_par_pf_e_pj_nao_se_repete_no_vinculo(): void
    {
        $pf = PessoaFisica::factory()->create();
        $pj = PessoaJuridica::factory()->create();

        VinculoSugerido::factory()->create([
            'pessoa_fisica_id' => $pf->id,
            'pessoa_juridica_id' => $pj->id,
        ]);

        $this->expectException(UniqueConstraintViolationException::class);
        VinculoSugerido::factory()->create([
            'pessoa_fisica_id' => $pf->id,
            'pessoa_juridica_id' => $pj->id,
        ]);
    }

    public function test_pessoafisica_pessoas_juridicas_sugeridas_traz_a_pj_da_carga(): void
    {
        $pf = PessoaFisica::factory()->create();
        $pj = PessoaJuridica::factory()->create();
        $importacao = ImportacaoPessoa::factory()->create();

        VinculoSugerido::factory()->create([
            'pessoa_fisica_id' => $pf->id,
            'pessoa_juridica_id' => $pj->id,
            'importacao_pessoa_id' => $importacao->id,
        ]);

        $this->assertTrue($pf->pessoasJuridicasSugeridas->contains($pj));
        $this->assertTrue($pj->pessoasFisicasSugeridas->contains($pf));

        $pivot = $pf->pessoasJuridicasSugeridas->first()->pivot;
        $this->assertNotNull($pivot);
        $this->assertSame($importacao->id, $pivot->importacao_pessoa_id);
    }
}
