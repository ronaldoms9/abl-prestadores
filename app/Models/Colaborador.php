<?php

namespace App\Models;

use App\Enums\StatusColaborador;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Colaborador extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'colaboradores';

    protected $fillable = [
        'pessoa_fisica_id',
        'pessoa_juridica_id',
        'cargo_id',
        'empresa_id',
        'obra_id',
        'status',
        'data_admissao',
        'data_desligamento',
        'motivo_desligamento',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusColaborador::class,
            'data_admissao' => 'date',
            'data_desligamento' => 'date',
        ];
    }

    public function pessoaFisica(): BelongsTo
    {
        return $this->belongsTo(PessoaFisica::class);
    }

    public function pessoaJuridica(): BelongsTo
    {
        return $this->belongsTo(PessoaJuridica::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function empresa(): BelongsTo
    {
        return $this->belongsTo(Empresa::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }

    public function remuneracoes(): HasMany
    {
        return $this->hasMany(Remuneracao::class, 'colaborador_id');
    }

    public function vinculosEquipe(): HasMany
    {
        return $this->hasMany(ColaboradorEquipe::class, 'colaborador_id');
    }

    public function qualificacoes(): HasMany
    {
        return $this->hasMany(ColaboradorQualificacao::class, 'colaborador_id');
    }
}
