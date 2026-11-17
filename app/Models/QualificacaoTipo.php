<?php

namespace App\Models;

use App\Enums\CategoriaQualificacao;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class QualificacaoTipo extends Model
{
    use Ativavel, Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'qualificacao_tipos';

    protected $fillable = [
        'nome',
        'categoria',
        'validade_meses',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'categoria' => CategoriaQualificacao::class,
            'validade_meses' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function exigencias(): HasMany
    {
        return $this->hasMany(CargoQualificacao::class, 'qualificacao_tipo_id');
    }

    public function qualificacoes(): HasMany
    {
        return $this->hasMany(ColaboradorQualificacao::class, 'qualificacao_tipo_id');
    }

    protected function prefixoAuditoria(): string
    {
        return 'qualificacao-tipo';
    }

    protected function camposAuditados(): array
    {
        return ['nome', 'categoria', 'validade_meses', 'ativo'];
    }
}
