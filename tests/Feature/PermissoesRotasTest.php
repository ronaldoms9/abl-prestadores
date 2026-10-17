<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\ImportacaoPessoa;
use App\Models\IntegracaoLog;
use App\Models\Obra;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\User;
use App\Role;
use App\Support\Auditoria;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

/**
 * Cada rota protegida recusa quem não tem a permissão e aceita quem tem.
 * O perfil do usuário tem nome aleatório (usuarioCom), o que prova que
 * nenhuma checagem depende do nome do perfil. Novas rotas: só acrescentar
 * linhas ao provider.
 */
class PermissoesRotasTest extends TestCase
{
    use RefreshDatabase;

    /**
     * [método, rota (nome ou URI), parâmetros, permissão, status com a permissão].
     * Parâmetros com ":" (':usuario', ':perfil', ':registro', ':importacao', ':integracao', ':pessoa-fisica'...) viram ids criados no teste.
     * Nas rotas de escrita sem dados válidos, basta não ser 403 (null).
     */
    public static function rotas(): array
    {
        return [
            'user.index' => ['GET', 'user.index', [], 'user-list', 200],
            'user.form (criar)' => ['GET', 'user.form', [], 'user-create', 200],
            'user.form (editar)' => ['GET', 'user.form', [':usuario'], 'user-edit', 200],
            'user.store (criar)' => ['POST', 'user.store', [], 'user-create', null],
            'user.store (editar)' => ['POST', 'user.store', [':usuario'], 'user-edit', null],
            'user.delete' => ['DELETE', 'user.delete', [':usuario'], 'user-delete', 201],

            'grupo.index' => ['GET', 'grupo.index', [], 'grupo-list', 200],
            'grupo.form (criar)' => ['GET', 'grupo.form', [], 'grupo-create', 200],
            'grupo.form (editar)' => ['GET', 'grupo.form', [':perfil'], 'grupo-edit', 200],
            'grupo.store (criar)' => ['POST', 'grupo.store', [], 'grupo-create', null],
            'grupo.store (editar)' => ['POST', 'grupo.store', [':perfil'], 'grupo-edit', null],
            'grupo.delete' => ['DELETE', 'grupo.delete', [':perfil'], 'grupo-delete', 201],

            'auditoria.index' => ['GET', 'auditoria.index', [], 'auditoria-list', 200],
            'auditoria.show' => ['GET', 'auditoria.show', [':registro'], 'auditoria-list', 200],

            'pessoa-fisica.index' => ['GET', 'pessoa-fisica.index', [], 'pessoa-list', 200],
            'pessoa-fisica.show' => ['GET', 'pessoa-fisica.show', [':pessoa-fisica'], 'pessoa-list', 200],
            'pessoa-juridica.index' => ['GET', 'pessoa-juridica.index', [], 'pessoa-list', 200],
            'pessoa-juridica.show' => ['GET', 'pessoa-juridica.show', [':pessoa-juridica'], 'pessoa-list', 200],
            'empresa.index' => ['GET', 'empresa.index', [], 'empresa-list', 200],
            'empresa.show' => ['GET', 'empresa.show', [':empresa'], 'empresa-list', 200],
            'obra.index' => ['GET', 'obra.index', [], 'obra-list', 200],
            'obra.show' => ['GET', 'obra.show', [':obra'], 'obra-list', 200],

            'importacao-pessoa.index' => ['GET', 'importacao-pessoa.index', [], 'pessoa-importar', 200],
            'importacao-pessoa.modelo' => ['GET', 'importacao-pessoa.modelo', [], 'pessoa-importar', 200],
            'importacao-pessoa.store' => ['POST', 'importacao-pessoa.store', [], 'pessoa-importar', null],
            'importacao-pessoa.show' => ['GET', 'importacao-pessoa.show', [':importacao'], 'pessoa-importar', 200],

            'integracao.index' => ['GET', 'integracao.index', [], 'integracao-list', 200],
            'integracao.show' => ['GET', 'integracao.show', [':integracao'], 'integracao-list', 200],
            'integracao.reprocessar' => ['POST', 'integracao.reprocessar', [':integracao'], 'integracao-reenviar', null],
            'integracao.sincronizar' => ['POST', 'integracao.sincronizar', [], 'integracao-sincronizar', null],

            'horizon' => ['GET', '/horizon', [], 'horizon-acessar', 200],
        ];
    }

    #[DataProvider('rotas')]
    public function test_sem_a_permissao_recebe_403(string $metodo, string $rota, array $parametros, string $permissao, ?int $status): void
    {
        $outras = array_values(array_diff(
            ['user-list', 'user-create', 'user-edit', 'user-delete', 'grupo-list', 'grupo-create', 'grupo-edit', 'grupo-delete', 'auditoria-list', 'horizon-acessar', 'pessoa-importar', 'integracao-list', 'integracao-reenviar', 'integracao-sincronizar', 'pessoa-list', 'empresa-list', 'obra-list'],
            $this->permissoesQueLiberam($rota, $permissao),
        ));

        $this->actingAs($this->usuarioCom(...$outras))
            ->json($metodo, $this->url($rota, $parametros))
            ->assertForbidden();
    }

    #[DataProvider('rotas')]
    public function test_com_a_permissao_acessa(string $metodo, string $rota, array $parametros, string $permissao, ?int $status): void
    {
        $resposta = $this->actingAs($this->usuarioCom($permissao))
            ->json($metodo, $this->url($rota, $parametros));

        $status === null
            ? $this->assertNotSame(403, $resposta->status())
            : $resposta->assertStatus($status);
    }

    /** Permissões alternativas que também liberam a rota (ex.: form aceita criar ou editar). */
    private function permissoesQueLiberam(string $rota, string $permissao): array
    {
        return match ($rota) {
            'user.form', 'user.store' => ['user-create', 'user-edit'],
            'grupo.form', 'grupo.store' => ['grupo-create', 'grupo-edit'],
            default => [$permissao],
        };
    }

    private function url(string $rota, array $parametros): string
    {
        $parametros = array_map(fn ($valor) => match ($valor) {
            ':usuario' => User::factory()->create()->id,
            ':perfil' => Role::create(['name' => 'alvo-'.uniqid(), 'display_name' => 'Alvo'])->id,
            ':registro' => $this->registroDeAuditoria(),
            ':importacao' => ImportacaoPessoa::factory()->create()->id,
            ':integracao' => IntegracaoLog::factory()->erro()->create()->id,
            ':pessoa-fisica' => PessoaFisica::factory()->create()->id,
            ':pessoa-juridica' => PessoaJuridica::factory()->create()->id,
            ':empresa' => Empresa::factory()->create()->id,
            ':obra' => Obra::factory()->create()->id,
            default => $valor,
        }, $parametros);

        return str_starts_with($rota, '/') ? $rota : route($rota, $parametros);
    }

    private function registroDeAuditoria(): int
    {
        Auditoria::registrar('login-falhou', dados: ['email' => 'alguem@example.com']);

        return Activity::latest('id')->value('id');
    }
}
