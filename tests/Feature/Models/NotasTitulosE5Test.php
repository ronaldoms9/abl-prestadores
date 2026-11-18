<?php

namespace Tests\Feature\Models;

use App\Enums\StatusNota;
use App\Enums\StatusTituloPagar;
use App\Enums\TributoNota;
use App\Models\Fechamento;
use App\Models\NotaFiscal;
use App\Models\NotaRetencao;
use App\Models\TituloPagar;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotasTitulosE5Test extends TestCase
{
    use RefreshDatabase;

    public function test_nota_liga_fechamento_colaborador_competencia_retencoes_e_titulo(): void
    {
        $nota = NotaFiscal::factory()->create(['valor_bruto' => 5300, 'valor_retencoes' => 100, 'valor_liquido' => 5200]);
        NotaRetencao::factory()->create(['nota_fiscal_id' => $nota->id, 'tributo' => TributoNota::Irrf, 'valor' => 100]);
        $titulo = TituloPagar::factory()->create(['nota_fiscal_id' => $nota->id]);

        $nota = $nota->fresh(['fechamento', 'colaborador', 'competencia', 'retencoes', 'titulo']);

        $this->assertSame($nota->fechamento->colaborador_id, $nota->colaborador_id);
        $this->assertSame($nota->fechamento->competencia_id, $nota->competencia_id);
        $this->assertSame(TributoNota::Irrf, $nota->retencoes->first()->tributo);
        $this->assertSame($titulo->id, $nota->titulo->id);
        $this->assertSame(StatusTituloPagar::Pendente, $titulo->status);
        $this->assertSame('5300.00', $nota->valor_bruto);
        $this->assertSame($nota->fechamento_id, $titulo->fechamento_id);
    }

    public function test_so_uma_nota_por_fechamento_e_chave_de_acesso_unica(): void
    {
        $fechamento = Fechamento::factory()->create();
        NotaFiscal::factory()->create(['fechamento_id' => $fechamento->id, 'chave_acesso' => str_repeat('1', 44)]);

        $this->expectException(QueryException::class);
        NotaFiscal::factory()->create(['fechamento_id' => $fechamento->id]);
    }

    public function test_chave_de_acesso_repetida_e_recusada_mas_varias_notas_sem_chave_valem(): void
    {
        NotaFiscal::factory()->count(2)->create(['chave_acesso' => null]);
        $this->assertSame(2, NotaFiscal::count());

        NotaFiscal::factory()->create(['chave_acesso' => 'ABC']);
        $this->expectException(QueryException::class);
        NotaFiscal::factory()->create(['chave_acesso' => 'ABC']);
    }

    public function test_estados_da_nota(): void
    {
        $this->assertTrue(StatusNota::Aguardando->aceitaEnvio());
        $this->assertTrue(StatusNota::Recusada->aceitaEnvio());
        $this->assertFalse(StatusNota::Aprovada->aceitaEnvio());
        $this->assertTrue(StatusNota::Divergente->aguardaDecisao());
        $this->assertFalse(StatusNota::Recusada->aguardaDecisao());
    }

    public function test_titulo_tem_chave_de_idempotencia_unica(): void
    {
        TituloPagar::factory()->create(['chave_idempotencia' => 'titulo:2026-09:1']);

        $this->expectException(QueryException::class);
        TituloPagar::factory()->create(['chave_idempotencia' => 'titulo:2026-09:1']);
    }
}
