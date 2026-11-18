<?php

namespace App\Services\Relatorios;

use App\Models\User;

/** Um relatório da proposta: slug, título, permissão, filtros e a consulta. */
interface Relatorio
{
    public function slug(): string;

    public function titulo(): string;

    public function descricao(): string;

    /** Permissão da tela de origem dos dados. */
    public function permissao(): string;

    /**
     * Filtros que a tela mostra: nome => [rótulo, tipo (mes|data|numero|obra|empresa|equipe|cargo|beneficio|status), padrão].
     *
     * @return array<string, array{0: string, 1: string, 2: mixed}>
     */
    public function filtros(): array;

    /** @param  array<string, mixed>  $filtros  já validados */
    public function executar(array $filtros, User $usuario): ResultadoRelatorio;
}
