<?php

namespace Tests\Feature;

use App\Http\Middleware\PermissionRouteMiddleware;
use App\Models\User;
use App\Permission;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as Rota;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Revisão de permissões rota a rota (E6, fatia 4). Percorre TODAS as rotas
 * registradas, então rota nova sem proteção falha aqui sem ninguém lembrar de
 * acrescentá-la a uma lista:
 *
 * 1. cobertura: toda rota autenticada tem permissão, ou está na lista de exceções (perfil, login, painel...);
 * 2. recusa: quem tem todas as permissões do sistema, menos as da rota, recebe 403;
 * 3. acesso: quem tem uma das permissões da rota não é barrado pela permissão (pode receber 404 ou 422 por não haver dado).
 */
class MatrizDePermissoesTest extends TestCase
{
    use RefreshDatabase;

    /** Prefixos de URI que não usam o middleware de permissão, e por quê. */
    private const SEM_PERMISSAO = [
        '' => 'raiz: o painel inicial (cada bloco confere a própria permissão)',
        'home' => 'painel inicial',
        'dashboard' => 'painel inicial',
        'perfil' => 'cada usuário edita o próprio perfil',
        'ajuda' => 'cada manual confere as próprias permissões no controller',
        'login' => 'público',
        'logout' => 'sessão',
        'password' => 'recuperação de senha',
        'sanctum' => 'cookie CSRF',
        'api/user' => 'API autenticada por token',
        'horizon' => 'protegido pelo Gate do Horizon (horizon-acessar)',
        '_debugbar' => 'só fora de produção',
        '_ignition' => 'só fora de produção',
    ];

    private const PARAMETROS = [
        'apelido' => 'colaborador', 'slug' => 'custo-por-obra', 'formato' => 'xlsx', 'tipo' => 'pdf', 'arquivo' => 'x.zip',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Http::fake();
        Queue::fake();
    }

    /** @return list<Rota> rotas com o middleware de permissão */
    private function rotasProtegidas(): array
    {
        return array_values(array_filter(Route::getRoutes()->getRoutes(), fn (Rota $r) => $this->permissoes($r) !== null));
    }

    /** @return array{0: list<string>, 1: bool}|null nomes e se exige todas */
    private function permissoes(Rota $rota): ?array
    {
        foreach ($rota->gatherMiddleware() as $middleware) {
            // As rotas usam o apelido "permission:..." (ou a classe, quando resolvida).
            foreach (['permission:', PermissionRouteMiddleware::class.':'] as $prefixo) {
                if (is_string($middleware) && str_starts_with($middleware, $prefixo)) {
                    $partes = explode(':', substr($middleware, strlen($prefixo)));

                    return [explode('|', $partes[0]), isset($partes[1])];
                }
            }
        }

        return null;
    }

    private function url(Rota $rota): string
    {
        $uri = preg_replace('/\{(\w+)\?\}/', '', $rota->uri());
        $uri = preg_replace_callback('/\{(\w+)\}/', fn ($m) => self::PARAMETROS[$m[1]] ?? '1', $uri);

        return '/'.trim(preg_replace('#/+#', '/', $uri), '/');
    }

    private function metodo(Rota $rota): string
    {
        return collect($rota->methods())->first(fn ($m) => ! in_array($m, ['HEAD', 'OPTIONS'], true));
    }

    private function excecao(Rota $rota): bool
    {
        $uri = trim($rota->uri(), '/');
        foreach (array_keys(self::SEM_PERMISSAO) as $prefixo) {
            if ($uri === $prefixo || ($prefixo !== '' && str_starts_with($uri, $prefixo.'/'))) {
                return true;
            }
        }

        return false;
    }

    public function test_toda_rota_autenticada_tem_permissao_ou_esta_na_lista_de_excecoes(): void
    {
        $semProtecao = [];
        foreach (Route::getRoutes()->getRoutes() as $rota) {
            if ($this->permissoes($rota) === null && ! $this->excecao($rota)) {
                $semProtecao[] = implode('|', $rota->methods()).' '.$rota->uri();
            }
        }

        $this->assertSame([], $semProtecao, "Rotas sem permissão e fora da lista de exceções:\n".implode("\n", $semProtecao));
    }

    public function test_a_matriz_cobre_um_numero_sensato_de_rotas(): void
    {
        $this->assertGreaterThan(150, count($this->rotasProtegidas()), 'A matriz deveria enxergar todas as rotas do sistema');
    }

    public function test_quem_tem_todas_as_permissoes_menos_as_da_rota_recebe_403(): void
    {
        $todas = Permission::pluck('name')->all();
        $usuarios = [];
        $falhas = [];

        foreach ($this->rotasProtegidas() as $rota) {
            [$nomes, $exigeTodas] = $this->permissoes($rota);
            $chave = implode('|', $nomes).($exigeTodas ? ':todas' : '');
            // Para "exige todas", tirar uma basta; para "qualquer uma", tira todas.
            $retirar = $exigeTodas ? [$nomes[0]] : $nomes;
            $usuarios[$chave] ??= $this->usuarioCom(...array_values(array_diff($todas, $retirar)));

            $status = $this->actingAs($usuarios[$chave])->json($this->metodo($rota), $this->url($rota))->getStatusCode();
            if ($status !== 403) {
                $falhas[] = "{$this->metodo($rota)} {$rota->uri()} ({$chave}) devolveu {$status}";
            }
        }

        $this->assertSame([], $falhas, "Rotas que aceitaram usuário sem a permissão:\n".implode("\n", $falhas));
    }

    public function test_quem_tem_a_permissao_nao_e_barrado_por_ela(): void
    {
        $usuarios = [];
        $falhas = [];

        foreach ($this->rotasProtegidas() as $rota) {
            [$nomes, $exigeTodas] = $this->permissoes($rota);
            $dar = $exigeTodas ? $nomes : [$nomes[0]];
            $chave = implode('|', $dar);
            $usuarios[$chave] ??= $this->usuarioCom(...$dar);

            $resposta = $this->actingAs($usuarios[$chave])->json($this->metodo($rota), $this->url($rota));
            // 403 por regra de negócio (ex.: usuário sem colaborador ligado) tem outra mensagem.
            if ($resposta->getStatusCode() === 403 && str_contains((string) $resposta->getContent(), 'Você não tem permissão para acessar está funcionalidade')) {
                $falhas[] = "{$this->metodo($rota)} {$rota->uri()} barrou quem tem {$chave}";
            }
        }

        $this->assertSame([], $falhas, implode("\n", $falhas));
    }

    public function test_perfis_do_sistema_so_tem_o_que_a_matriz_de_acesso_preve(): void
    {
        $this->seed();
        $permissoes = fn (string $perfil) => \App\Role::where('name', $perfil)->firstOrFail()->permissions()->pluck('name')->all();

        // Gestor: só consulta e solicita; nunca remuneração, contrato, fechamento ou nota.
        foreach (['remuneracao-list', 'remuneracao-reajustar', 'contrato-list', 'contrato-gerar', 'fechamento-list', 'fechamento-aprovar', 'nota-list', 'nota-decidir', 'beneficio-list', 'user-list'] as $proibida) {
            $this->assertNotContains($proibida, $permissoes('gestor'), "gestor não deveria ter {$proibida}");
        }
        $this->assertContains('ferias-solicitar', $permissoes('gestor'));

        // Só o financeiro aprova fechamento e nota; só o administrador reabre.
        $this->assertContains('fechamento-aprovar', $permissoes('financeiro'));
        $this->assertContains('nota-decidir', $permissoes('financeiro'));
        foreach (['rh', 'gestor', 'colaborador'] as $perfil) {
            $this->assertNotContains('fechamento-aprovar', $permissoes($perfil));
            $this->assertNotContains('nota-decidir', $permissoes($perfil));
            $this->assertNotContains('fechamento-reabrir', $permissoes($perfil));
        }
        $this->assertContains('fechamento-reabrir', $permissoes('administrador'));

        // O colaborador só tem o portal.
        $this->assertSame(['portal-acessar'], $permissoes('colaborador'));
        $this->assertContains('portal-acessar', $permissoes('administrador'));
    }
}
