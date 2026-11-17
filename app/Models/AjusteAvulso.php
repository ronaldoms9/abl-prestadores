<?php

namespace App\Models;

use App\Enums\TipoAjuste;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Crédito ou débito avulso no fechamento do mês, sempre com justificativa. */
class AjusteAvulso extends Model
{
    use Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'ajustes_avulsos';

    protected $fillable = [
        'colaborador_id',
        'competencia_id',
        'tipo',
        'valor',
        'justificativa',
        'aprovado_por',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoAjuste::class,
            'valor' => 'decimal:2',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    protected function prefixoAuditoria(): string
    {
        return 'ajuste-avulso';
    }

    /** Sem a justificativa: texto livre fica no registro, não na auditoria. */
    protected function camposAuditados(): array
    {
        return ['colaborador_id', 'competencia_id', 'tipo', 'valor'];
    }
}
