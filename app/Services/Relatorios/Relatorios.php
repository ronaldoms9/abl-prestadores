<?php

namespace App\Services\Relatorios;

use App\Models\User;
use Illuminate\Support\Collection;

/** Catálogo dos relatórios. Relatório novo = classe nova na lista. */
class Relatorios
{
    /** @var list<class-string<Relatorio>> */
    public const LISTA = [
        CustoPorObra::class,
        BeneficiosPorCompetencia::class,
        FeriasESaldos::class,
        QualificacoesVencendo::class,
        ContratosVencendo::class,
        NotasPendentes::class,
    ];

    /** @return Collection<string, Relatorio> slug => relatório que o usuário pode ver */
    public function visiveisPara(User $usuario): Collection
    {
        return collect(self::LISTA)
            ->map(fn (string $c) => app($c))
            ->filter(fn (Relatorio $r) => $usuario->can($r->permissao()))
            ->keyBy(fn (Relatorio $r) => $r->slug());
    }

    /** 404 para relatório que não existe; 403 para o que existe mas o usuário não pode ver. */
    public function achar(string $slug, User $usuario): Relatorio
    {
        $existente = collect(self::LISTA)->map(fn (string $c) => app($c))->first(fn (Relatorio $r) => $r->slug() === $slug);
        abort_if($existente === null, 404);
        abort_unless($usuario->can($existente->permissao()), 403);

        return $existente;
    }
}
