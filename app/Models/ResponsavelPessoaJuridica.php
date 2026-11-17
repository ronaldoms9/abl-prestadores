<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Vínculo local entre uma pessoa jurídica e a pessoa física responsável por ela. */
class ResponsavelPessoaJuridica extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'pessoa_juridica_responsaveis';

    protected $fillable = ['pessoa_juridica_id', 'pessoa_fisica_id', 'inicio', 'fim'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereNull('fim');
    }

    public function pessoaJuridica(): BelongsTo
    {
        return $this->belongsTo(PessoaJuridica::class, 'pessoa_juridica_id');
    }

    public function pessoaFisica(): BelongsTo
    {
        return $this->belongsTo(PessoaFisica::class, 'pessoa_fisica_id');
    }
}
