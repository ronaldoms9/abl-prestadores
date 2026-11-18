<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Manuais de uso por perfil e guia de operação (E6, fatia 6), lidos de
 * docs/manual/*.md e mostrados no próprio sistema. Cada manual aparece para
 * quem tem alguma das permissões ligadas a ele.
 */
class AjudaController extends Controller
{
    /** slug => [título, arquivo, permissões que liberam]. */
    private const MANUAIS = [
        'rh' => ['Manual do RH', 'rh.md', ['colaborador-edit', 'colaborador-create', 'admissao-gerir', 'contrato-gerar', 'ferias-aprovar-rh']],
        'gestor' => ['Manual do gestor', 'gestor.md', ['equipe-gerir']],
        'financeiro' => ['Manual do financeiro', 'financeiro.md', ['fechamento-aprovar', 'nota-decidir']],
        'colaborador' => ['Manual do colaborador (portal)', 'colaborador.md', ['portal-acessar']],
        'operacao' => ['Guia de operação', 'operacao.md', ['grupo-edit', 'fechamento-reabrir', 'horizon-acessar']],
    ];

    public function index(Request $request)
    {
        return view('ajuda.index', ['manuais' => $this->visiveis($request)]);
    }

    public function show(Request $request, string $slug)
    {
        abort_unless(isset(self::MANUAIS[$slug]), 404);
        abort_unless($request->user()->can(self::MANUAIS[$slug][2]), 403);

        [$titulo, $arquivo] = self::MANUAIS[$slug];
        $caminho = base_path('docs/manual/'.$arquivo);
        abort_unless(is_file($caminho), 404);

        return view('ajuda.show', [
            'titulo' => $titulo,
            'html' => Str::markdown((string) file_get_contents($caminho), ['html_input' => 'strip', 'allow_unsafe_links' => false]),
            'manuais' => $this->visiveis($request),
            'atual' => $slug,
        ]);
    }

    /** @return array<string, string> slug => título */
    private function visiveis(Request $request): array
    {
        $lista = [];
        foreach (self::MANUAIS as $slug => [$titulo, , $permissoes]) {
            if ($request->user()->can($permissoes)) {
                $lista[$slug] = $titulo;
            }
        }

        return $lista;
    }
}
