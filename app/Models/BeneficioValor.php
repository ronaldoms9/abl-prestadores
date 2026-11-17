<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use App\Models\Concerns\TemVigencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Valor de um benefício em um período. Nunca editado: reajuste encerra a linha e cria outra. */
class BeneficioValor extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes, TemVigencia;

    protected $table = 'beneficio_valores';

    protected $fillable = [
        'beneficio_id',
        'valor',
        'vigencia_inicio',
        'vigencia_fim',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
        ];
    }

    public function beneficio(): BelongsTo
    {
        return $this->belongsTo(Beneficio::class);
    }

    protected function colunasVigencia(): array
    {
        return ['vigencia_inicio', 'vigencia_fim'];
    }
}
