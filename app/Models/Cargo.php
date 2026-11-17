<?php

namespace App\Models;

use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cargo extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'cargos';

    protected $fillable = [
        'nome',
        'descricao',
        'faixa_min',
        'faixa_max',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'faixa_min' => 'decimal:2',
            'faixa_max' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'cargo_id');
    }

    public function exigencias(): HasMany
    {
        return $this->hasMany(CargoQualificacao::class, 'cargo_id');
    }
}
