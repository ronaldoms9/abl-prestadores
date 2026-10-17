<?php

namespace App\Models;

use App\Casts\AsEndereco;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Obra extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'obras';

    protected $fillable = [
        'empresa_id',
        'uau_codigo',
        'descricao',
        'status_uau',
        'tipo_uau',
        'endereco',
        'data_inicio',
        'data_fim',
        'ativo',
        'sincronizado_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'endereco' => AsEndereco::class,
            'ativo' => 'boolean',
            'data_inicio' => 'date',
            'data_fim' => 'date',
            'sincronizado_em' => 'datetime',
            'status_uau' => 'integer',
        ];
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class, 'empresa_id');
    }
}
