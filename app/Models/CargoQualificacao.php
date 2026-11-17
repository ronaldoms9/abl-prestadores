<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CargoQualificacao extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'cargo_qualificacoes';

    protected $fillable = [
        'cargo_id',
        'qualificacao_tipo_id',
        'obrigatoria',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'obrigatoria' => 'boolean',
        ];
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function tipo(): BelongsTo
    {
        return $this->belongsTo(QualificacaoTipo::class, 'qualificacao_tipo_id');
    }
}
