<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait Ativavel
{
    /**
     * Scope para filtrar apenas registros ativos.
     */
    public function scopeAtivos(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('ativo'), true);
    }

    /**
     * Scope para filtrar apenas registros inativos.
     */
    public function scopeInativos(Builder $query): Builder
    {
        return $query->where($this->qualifyColumn('ativo'), false);
    }
}
