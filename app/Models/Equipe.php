<?php

namespace App\Models;

use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Equipe extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'equipes';

    protected $fillable = [
        'nome',
        'descricao',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'ativo' => 'boolean',
        ];
    }

    public function gestores(): HasMany
    {
        return $this->hasMany(EquipeGestor::class, 'equipe_id');
    }

    public function vinculosColaborador(): HasMany
    {
        return $this->hasMany(ColaboradorEquipe::class, 'equipe_id');
    }
}
