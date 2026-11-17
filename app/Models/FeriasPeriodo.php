<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Período aquisitivo de férias. Saldo = direito − utilizados, nunca negativo. */
class FeriasPeriodo extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'ferias_periodos';

    protected $fillable = [
        'colaborador_id',
        'aquisitivo_inicio',
        'aquisitivo_fim',
        'dias_direito',
        'dias_utilizados',
        'limite_gozo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'aquisitivo_inicio' => 'date',
            'aquisitivo_fim' => 'date',
            'dias_direito' => 'integer',
            'dias_utilizados' => 'integer',
            'limite_gozo' => 'date',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function solicitacoes(): HasMany
    {
        return $this->hasMany(FeriasSolicitacao::class, 'ferias_periodo_id');
    }

    public function saldo(): int
    {
        return max(0, $this->dias_direito - $this->dias_utilizados);
    }
}
