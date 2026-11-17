<?php

namespace App\Models;

use App\Enums\TipoItemFechamento;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Uma linha da memória de cálculo do fechamento. `origem` é o registro que originou o valor. */
class FechamentoItem extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'fechamento_itens';

    protected $fillable = [
        'fechamento_id',
        'tipo',
        'descricao',
        'quantidade',
        'valor_unitario',
        'valor_total',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoItemFechamento::class,
            'quantidade' => 'decimal:2',
            'valor_unitario' => 'decimal:2',
            'valor_total' => 'decimal:2',
        ];
    }

    public function fechamento(): BelongsTo
    {
        return $this->belongsTo(Fechamento::class);
    }

    public function origem(): MorphTo
    {
        return $this->morphTo();
    }
}
