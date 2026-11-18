<?php

namespace App\Models;

use App\Enums\StatusTituloPagar;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Espelho do título a pagar lançado no UAU (fatia 6 da E5). */
class TituloPagar extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'titulos_pagar';

    protected $fillable = ['fechamento_id', 'nota_fiscal_id', 'valor', 'vencimento', 'uau_processo', 'status', 'chave_idempotencia'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'vencimento' => 'date',
            'status' => StatusTituloPagar::class,
        ];
    }

    public function fechamento(): BelongsTo
    {
        return $this->belongsTo(Fechamento::class);
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_id');
    }
}
