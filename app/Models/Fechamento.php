<?php

namespace App\Models;

use App\Enums\StatusFechamento;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Fechamento de um colaborador em uma competência (único por par). Valores em reais com 2 casas. */
class Fechamento extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'fechamentos';

    protected $fillable = [
        'competencia_id',
        'colaborador_id',
        'dias_periodo',
        'dias_trabalhados',
        'valor_remuneracao',
        'total_beneficios',
        'total_ajustes',
        'total_liquido',
        'status',
        'snapshot',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'dias_periodo' => 'integer',
            'dias_trabalhados' => 'decimal:2',
            'valor_remuneracao' => 'decimal:2',
            'total_beneficios' => 'decimal:2',
            'total_ajustes' => 'decimal:2',
            'total_liquido' => 'decimal:2',
            'status' => StatusFechamento::class,
            'snapshot' => 'array',
        ];
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function nota(): HasOne
    {
        return $this->hasOne(NotaFiscal::class, 'fechamento_id');
    }

    public function itens(): HasMany
    {
        return $this->hasMany(FechamentoItem::class, 'fechamento_id');
    }
}
