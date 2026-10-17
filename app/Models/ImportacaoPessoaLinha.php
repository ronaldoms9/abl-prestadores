<?php

namespace App\Models;

use App\Enums\ResultadoImportacao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportacaoPessoaLinha extends Model
{
    use HasFactory;

    protected $table = 'importacao_pessoa_linhas';

    protected $fillable = [
        'importacao_pessoa_id',
        'linha',
        'cpf',
        'cnpj',
        'resultado_pf',
        'resultado_pj',
        'mensagem',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'linha' => 'integer',
            'resultado_pf' => ResultadoImportacao::class,
            'resultado_pj' => ResultadoImportacao::class,
        ];
    }

    public function importacao(): BelongsTo
    {
        return $this->belongsTo(ImportacaoPessoa::class, 'importacao_pessoa_id');
    }
}
