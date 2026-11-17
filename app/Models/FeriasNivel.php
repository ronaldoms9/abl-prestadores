<?php

namespace App\Models;

use App\Enums\TipoAprovadorFerias;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Nível do fluxo de aprovação de férias, configurável pelo admin (README, D7). */
class FeriasNivel extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'ferias_niveis';

    protected $fillable = [
        'ordem',
        'tipo_aprovador',
        'permission_id',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'ordem' => 'integer',
            'tipo_aprovador' => TipoAprovadorFerias::class,
            'ativo' => 'boolean',
        ];
    }

    public function permissao(): BelongsTo
    {
        return $this->belongsTo(\App\Permission::class, 'permission_id');
    }
}
