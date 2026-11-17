<?php

namespace App\Models;

use App\Enums\FormaReajuste;
use App\Enums\TipoReajuste;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Reajuste extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'reajustes';

    protected $fillable = [
        'tipo',
        'criterio',
        'forma',
        'valor',
        'vigencia_inicio',
        'aprovado_por',
        'aprovado_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoReajuste::class,
            'forma' => FormaReajuste::class,
            'criterio' => 'array',
            'valor' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'aprovado_em' => 'datetime',
        ];
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovado_por');
    }

    public function remuneracoes(): HasMany
    {
        return $this->hasMany(Remuneracao::class, 'reajuste_id');
    }
}
