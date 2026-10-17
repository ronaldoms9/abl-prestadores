<?php

namespace App\Models;

use App\Enums\StatusImportacao;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImportacaoPessoa extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'importacoes_pessoas';

    protected $fillable = [
        'arquivo_path',
        'nome_original',
        'status',
        'total_linhas',
        'resumo',
        'erro',
        'iniciada_em',
        'concluida_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusImportacao::class,
            'total_linhas' => 'integer',
            'resumo' => 'array',
            'iniciada_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    public function linhas(): HasMany
    {
        return $this->hasMany(ImportacaoPessoaLinha::class, 'importacao_pessoa_id');
    }

    public function vinculosSugeridos(): HasMany
    {
        return $this->hasMany(VinculoSugerido::class, 'importacao_pessoa_id');
    }
}
