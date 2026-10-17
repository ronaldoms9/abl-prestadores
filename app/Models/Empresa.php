<?php

namespace App\Models;

use App\Casts\AsEndereco;
use App\Casts\SomenteDigitos;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Empresa extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'empresas';

    protected $fillable = [
        'uau_codigo',
        'cnpj',
        'razao_social',
        'inscricao_estadual',
        'inscricao_municipal',
        'endereco',
        'telefone',
        'ativo',
        'sincronizado_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'cnpj' => SomenteDigitos::class,
            'endereco' => AsEndereco::class,
            'ativo' => 'boolean',
            'sincronizado_em' => 'datetime',
        ];
    }

    public function obras(): HasMany
    {
        return $this->hasMany(Obra::class, 'empresa_id');
    }

    public function getCnpjFormatadoAttribute(): ?string
    {
        return Documento::formatar($this->cnpj);
    }
}
