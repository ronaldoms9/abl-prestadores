<?php

namespace Tests\Feature\Integracoes;

use App\Actions\Uau\ImportarPessoa;
use App\Enums\DirecaoIntegracao;
use App\Integrations\Uau\OperacaoUau;
use App\Models\Empresa;
use App\Models\IntegracaoLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\UauSimulado;
use Tests\TestCase;

class IntegracaoTelaTest extends TestCase
{
    use RefreshDatabase, UauSimulado;

    private User $usuario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->usuario = $this->usuarioCom('integracao-list');
    }

    public function test_falha_seguida_de_sucesso_da_mesma_chave_fica_resolvida(): void
    {
        $resolvida = $this->log('erro', OperacaoUau::PessoaPorDocumento, 'pessoa:52998224725', now()->subHours(3));
        $this->log('sucesso', OperacaoUau::PessoaPorDocumento, 'pessoa:52998224725', now()->subHours(2));
        $outraChave = $this->log('erro', OperacaoUau::PessoaPorDocumento, 'pessoa:74620153826', now()->subHours(3));
        $empresas = $this->log('erro', OperacaoUau::EmpresasAtivas, null, now()->subHours(3));
        $this->log('sucesso', OperacaoUau::EmpresasAtivas, null, now()->subHours(1));
        $sucessoAnterior = $this->log('sucesso', OperacaoUau::ObrasAtivas, null, now()->subHours(5));
        $obras = $this->log('erro', OperacaoUau::ObrasAtivas, null, now()->subHours(4));

        $this->assertEqualsCanonicalizing([$outraChave->id, $obras->id], IntegracaoLog::semSolucao()->pluck('id')->all());
        $this->assertEqualsCanonicalizing([$resolvida->id, $empresas->id], IntegracaoLog::resolvidas()->pluck('id')->all());
        $this->assertTrue($resolvida->resolvida());
        $this->assertFalse($obras->resolvida());
        $this->assertFalse($sucessoAnterior->resolvida());
    }

    public function test_filtros_devolvem_so_as_linhas_certas(): void
    {
        $sucesso = $this->log('sucesso', OperacaoUau::EmpresasAtivas, null, now()->subHours(2));
        $semSolucao = $this->log('erro', OperacaoUau::PessoaPorDocumento, 'pessoa:1', now()->subHours(3));
        $resolvida = $this->log('erro', OperacaoUau::EmpresasAtivas, null, now()->subHours(3));
        $antiga = $this->log('erro', OperacaoUau::ObrasAtivas, null, now()->subDays(10));
        $saida = $this->log('sucesso', OperacaoUau::Versao, null, now()->subHour(), ['direcao' => DirecaoIntegracao::Saida]);

        $this->assertLinhas([$sucesso, $semSolucao, $resolvida, $saida], []);
        $this->assertLinhas([$sucesso, $saida], ['situacao' => 'sucesso']);
        $this->assertLinhas([$semSolucao], ['situacao' => 'sem-solucao']);
        $this->assertLinhas([$resolvida], ['situacao' => 'resolvida']);
        $this->assertLinhas([$sucesso, $resolvida], ['operacao' => 'empresas-ativas']);
        $this->assertLinhas([$saida], ['direcao' => 'saida']);
        $this->assertLinhas([$antiga], ['de' => now()->subDays(11)->toDateString(), 'ate' => now()->subDays(9)->toDateString()]);
        $this->assertLinhas([$sucesso, $semSolucao, $resolvida, $antiga, $saida], ['de' => '']);
    }

    public function test_lista_descreve_o_objeto_e_a_situacao(): void
    {
        $empresa = Empresa::factory()->create(['razao_social' => 'Construtora Exemplo']);
        IntegracaoLog::factory()->erro()->paraEntidade($empresa)->create(['operacao' => 'empresas-ativas']);

        $linha = $this->linhas([])[0];

        $this->assertSame('Empresa · Construtora Exemplo', $linha['objeto']);
        $this->assertSame('Empresas ativas', $linha['operacao']);
        $this->assertStringContainsString('Erro', $linha['situacao']);
        $this->assertStringContainsString('Detalhes', $linha['action']);
    }

    public function test_tela_abre_com_os_filtros_da_url(): void
    {
        $this->actingAs($this->usuario)
            ->get(route('integracao.index', ['situacao' => 'sem-solucao']))
            ->assertOk()
            ->assertSee('<option value="sem-solucao" selected', false);
    }

    public function test_detalhe_mostra_o_json_sem_segredos(): void
    {
        $this->simularUau();
        app(ImportarPessoa::class)->executar('03174682940');
        $log = IntegracaoLog::where('operacao', 'pessoa-por-documento')->sole();

        $this->actingAs($this->usuario)
            ->get(route('integracao.show', $log->id))
            ->assertOk()
            ->assertSee('Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus')
            ->assertSee('&quot;cpf_cnpj&quot;: &quot;03174682940&quot;', false)
            ->assertSee('[oculto]')
            ->assertDontSee('segredo-uau-web-123')
            ->assertDontSee('joaoweb')
            ->assertDontSee('56789-0');
    }

    public function test_detalhe_de_falha_resolvida_mostra_quando(): void
    {
        $falha = $this->log('erro', OperacaoUau::EmpresasAtivas, null, now()->subHours(3));
        $quando = now()->subHour()->startOfSecond();
        $this->log('sucesso', OperacaoUau::EmpresasAtivas, null, $quando);

        $this->actingAs($this->usuario)
            ->get(route('integracao.show', $falha->id))
            ->assertOk()
            ->assertSee('Erro resolvido')
            ->assertSee('pela chamada de '.$quando->format('d/m/Y H:i:s'));
    }

    private function log(string $status, OperacaoUau $operacao, ?string $chave, $quando, array $extra = []): IntegracaoLog
    {
        return IntegracaoLog::factory()->{$status}()->create([
            'operacao' => $operacao->value,
            'chave_idempotencia' => $chave,
            'executado_em' => $quando,
            ...$extra,
        ]);
    }

    private function assertLinhas(array $esperados, array $filtros): void
    {
        $this->assertEqualsCanonicalizing(
            array_map(fn (IntegracaoLog $log) => $log->executado_em->format('d/m/Y H:i:s.v'), $esperados),
            array_column($this->linhas($filtros), 'executado_em'),
            'Filtros: '.json_encode($filtros),
        );
    }

    private function linhas(array $filtros): array
    {
        return $this->actingAs($this->usuario)
            ->getJson(route('integracao.index', $filtros), ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()
            ->json('data');
    }
}
