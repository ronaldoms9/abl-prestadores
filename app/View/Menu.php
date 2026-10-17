<?php

namespace App\View;

use App\Models\User;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;

/**
 * Filtra o menu lateral (config/menu.php) pelo que existe e pelo que o
 * usuário pode ver. O Blade só percorre o resultado.
 */
class Menu
{
    /**
     * @param  array<int, array<string, mixed>>  $itens
     */
    public function __construct(
        private readonly array $itens,
        private readonly Router $router,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(config('menu', []), app('router'));
    }

    /**
     * Itens e grupos visíveis para o usuário, já com 'url' e 'ativo'.
     *
     * Grupo: ['titulo', 'icone', 'ativo', 'itens' => [...]].
     * Item:  ['titulo', 'icone', 'url', 'ativo'].
     *
     * @return array<int, array<string, mixed>>
     */
    public function itensVisiveis(User $user): array
    {
        $visiveis = [];

        foreach ($this->itens as $entrada) {
            if (isset($entrada['itens'])) {
                $filhos = $this->filtrar($entrada['itens'], $user, 'bi bi-circle');

                if ($filhos) {
                    $visiveis[] = [
                        'titulo' => $entrada['titulo'],
                        'icone' => $entrada['icone'] ?? 'bi bi-circle',
                        'ativo' => in_array(true, array_column($filhos, 'ativo'), true),
                        'itens' => $filhos,
                    ];
                }

                continue;
            }

            array_push($visiveis, ...$this->filtrar([$entrada], $user, 'bi bi-circle'));
        }

        return $visiveis;
    }

    /**
     * @param  array<int, array<string, mixed>>  $itens
     * @return array<int, array<string, mixed>>
     */
    private function filtrar(array $itens, User $user, string $iconePadrao): array
    {
        $visiveis = [];

        foreach ($itens as $item) {
            if (! $this->router->has($item['rota'])) {
                continue;
            }

            if (isset($item['permissao']) && ! $user->can($item['permissao'])) {
                continue;
            }

            $visiveis[] = [
                'titulo' => $item['titulo'],
                'icone' => $item['icone'] ?? $iconePadrao,
                'url' => route($item['rota']),
                'ativo' => $this->router->currentRouteNamed(...$this->padroesAtivos($item)),
            ];
        }

        return $visiveis;
    }

    /**
     * @return array<int, string>
     */
    private function padroesAtivos(array $item): array
    {
        if (isset($item['ativo'])) {
            return (array) $item['ativo'];
        }

        $prefixo = Str::contains($item['rota'], '.') ? Str::beforeLast($item['rota'], '.') : $item['rota'];

        return [$prefixo, $prefixo.'.*'];
    }
}
