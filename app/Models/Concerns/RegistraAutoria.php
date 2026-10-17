<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Preenche created_by e updated_by com o usuário logado.
 *
 * A tabela precisa das colunas criadas por $table->auditoria(). Sem usuário
 * (jobs da integração, seeders), a criação grava null e a edição mantém o
 * último autor humano.
 */
trait RegistraAutoria
{
    public static function bootRegistraAutoria(): void
    {
        static::creating(function (Model $model) {
            $model->created_by ??= auth()->id();
            $model->updated_by ??= auth()->id();
        });

        static::updating(function (Model $model) {
            $model->updated_by = auth()->id() ?? $model->updated_by;
        });
    }

    public function criadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function atualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
