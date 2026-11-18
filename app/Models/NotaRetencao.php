<?php

namespace App\Models;

use App\Enums\TributoNota;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Retenção de tributo declarada na nota (lida do XML ou digitada). */
class NotaRetencao extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'nota_retencoes';

    protected $fillable = ['nota_fiscal_id', 'tributo', 'valor'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tributo' => TributoNota::class,
            'valor' => 'decimal:2',
        ];
    }

    public function nota(): BelongsTo
    {
        return $this->belongsTo(NotaFiscal::class, 'nota_fiscal_id');
    }
}
