<?php

namespace App\Tenancy;

use App\Models\Tenant;

/**
 * Tenant ativo do processo em curso.
 *
 * Existe como singleton explícito — e não como leitura de auth() — porque dentro
 * de um worker de fila não há usuário autenticado: um escopo baseado em auth()
 * silenciosamente não filtraria nada. Todo job recebe o tenant_id no construtor
 * e chama set() na primeira linha do handle().
 */
final class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function setById(?int $tenantId): void
    {
        $this->tenant = $tenantId === null ? null : Tenant::findOrFail($tenantId);
    }

    public function get(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): ?int
    {
        return $this->tenant?->id;
    }

    public function has(): bool
    {
        return $this->tenant !== null;
    }
}
