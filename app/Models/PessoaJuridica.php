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
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class PessoaJuridica extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'pessoas_juridicas';

    protected $fillable = [
        'uau_codigo',
        'cnpj',
        'razao_social',
        'nome_fantasia',
        'regime_tributario',
        'inscricao_municipal',
        'inscricao_estadual',
        'email',
        'telefone',
        'endereco',
        'ativo',
        'sincronizado_em',
        'uau_alterado_em',
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
            'uau_alterado_em' => 'datetime',
        ];
    }

    public function vinculosSugeridos(): HasMany
    {
        return $this->hasMany(VinculoSugerido::class, 'pessoa_juridica_id');
    }

    public function pessoasFisicasSugeridas(): BelongsToMany
    {
        return $this->belongsToMany(PessoaFisica::class, 'vinculos_sugeridos', 'pessoa_juridica_id', 'pessoa_fisica_id')
            ->withPivot('utilizado_em', 'importacao_pessoa_id')
            ->withTimestamps()
            ->wherePivotNull('deleted_at');
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'pessoa_juridica_id');
    }

    public function dadosBancarios(): MorphMany
    {
        return $this->morphMany(DadoBancario::class, 'pessoa');
    }

    public function documentos(): MorphMany
    {
        return $this->morphMany(\App\Models\Documento::class, 'documentavel');
    }

    /** Histórico de responsáveis, do mais recente ao mais antigo. */
    public function responsaveis(): HasMany
    {
        return $this->hasMany(ResponsavelPessoaJuridica::class, 'pessoa_juridica_id')->orderByDesc('inicio')->orderByDesc('id');
    }

    /** Responsável vigente (vínculo sem data de fim). */
    public function responsavelAtual(): HasOne
    {
        return $this->hasOne(ResponsavelPessoaJuridica::class, 'pessoa_juridica_id')->whereNull('fim')->latestOfMany();
    }

    public function getCnpjFormatadoAttribute(): ?string
    {
        return Documento::formatar($this->cnpj);
    }
}
