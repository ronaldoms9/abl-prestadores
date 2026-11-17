<?php

namespace App\Models;

use App\Enums\AbrangenciaFeriado;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Feriado extends Model
{
    use Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'feriados';

    protected $fillable = [
        'data',
        'descricao',
        'abrangencia',
        'obra_id',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'data' => 'date',
            'abrangencia' => AbrangenciaFeriado::class,
        ];
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    protected function prefixoAuditoria(): string
    {
        return 'feriado';
    }

    protected function camposAuditados(): array
    {
        return ['data', 'descricao', 'abrangencia', 'obra_id'];
    }
}
