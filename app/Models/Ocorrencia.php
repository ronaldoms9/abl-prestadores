<?php

namespace App\Models;

use App\Enums\TipoOcorrencia;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Falta ou afastamento de um colaborador, com período (datas inclusive) e se foi abonado. */
class Ocorrencia extends Model
{
    use Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'ocorrencias';

    protected $fillable = [
        'colaborador_id',
        'tipo',
        'inicio',
        'fim',
        'abona',
        'observacao',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoOcorrencia::class,
            'inicio' => 'date',
            'fim' => 'date',
            'abona' => 'boolean',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    protected function prefixoAuditoria(): string
    {
        return 'ocorrencia';
    }

    protected function camposAuditados(): array
    {
        return ['colaborador_id', 'tipo', 'inicio', 'fim', 'abona'];
    }
}
