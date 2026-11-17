<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use App\Models\Concerns\TemVigencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Remuneracao extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes, TemVigencia;

    protected $table = 'remuneracoes';

    protected $fillable = [
        'colaborador_id',
        'valor_mensal',
        'vigencia_inicio',
        'vigencia_fim',
        'reajuste_id',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'valor_mensal' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function reajuste(): BelongsTo
    {
        return $this->belongsTo(Reajuste::class);
    }

    protected function colunasVigencia(): array
    {
        return ['vigencia_inicio', 'vigencia_fim'];
    }
}
