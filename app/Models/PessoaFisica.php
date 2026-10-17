<?php

namespace App\Models;

use App\Casts\AsEndereco;
use App\Casts\SomenteDigitos;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use App\Support\Documento;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PessoaFisica extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'pessoas_fisicas';

    protected $fillable = [
        'uau_codigo',
        'cpf',
        'nome',
        'nascimento',
        'email',
        'telefone',
        'endereco',
        'ativo',
        'sincronizado_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'cpf' => SomenteDigitos::class,
            'nascimento' => 'date',
            'endereco' => AsEndereco::class,
            'ativo' => 'boolean',
            'sincronizado_em' => 'datetime',
        ];
    }

    public function usuario(): HasOne
    {
        return $this->hasOne(User::class, 'pessoa_fisica_id');
    }

    public function vinculosSugeridos(): HasMany
    {
        return $this->hasMany(VinculoSugerido::class, 'pessoa_fisica_id');
    }

    public function pessoasJuridicasSugeridas(): BelongsToMany
    {
        return $this->belongsToMany(PessoaJuridica::class, 'vinculos_sugeridos', 'pessoa_fisica_id', 'pessoa_juridica_id')
            ->withPivot('utilizado_em', 'importacao_pessoa_id')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function getCpfFormatadoAttribute(): ?string
    {
        return Documento::formatar($this->cpf);
    }
}
