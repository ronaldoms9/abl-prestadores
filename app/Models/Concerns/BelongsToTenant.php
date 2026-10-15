<?php

namespace App\Models\Concerns;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Filtra e preenche tenant_id a partir do TenantContext.
 *
 * O escopo só é aplicado quando há tenant no contexto: sem contexto (console,
 * seeders, time interno) o modelo se comporta como antes.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder) {
            $tenantId = app(TenantContext::class)->id();

            if ($tenantId !== null) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
            }
        });

        static::creating(function (Model $model) {
            if ($model->getAttribute('tenant_id') === null) {
                $model->setAttribute('tenant_id', app(TenantContext::class)->id());
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }

    /** Escotilha consciente: relatórios internos que precisam cruzar tenants. */
    public function scopeSemEscopoDeTenant(Builder $query): Builder
    {
        return $query->withoutGlobalScope('tenant');
    }
}
