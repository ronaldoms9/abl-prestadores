<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Parte do fechamento de um colaborador que cabe a uma obra (E4, fatia 10). Sem obra = "Sem obra". */
class FechamentoRateio extends Model
{
    protected $table = 'fechamento_rateios';

    protected $fillable = ['fechamento_id', 'obra_id', 'percentual', 'valor_remuneracao', 'total_beneficios', 'total_ajustes', 'total_liquido'];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return [
            'percentual' => 'decimal:4',
            'valor_remuneracao' => 'decimal:2',
            'total_beneficios' => 'decimal:2',
            'total_ajustes' => 'decimal:2',
            'total_liquido' => 'decimal:2',
        ];
    }

    public function fechamento(): BelongsTo
    {
        return $this->belongsTo(Fechamento::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }
}
