<?php

namespace App\Models;

use App\Enums\ResultadoImportacao;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
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

    /** Linhas com CPF ou CNPJ ainda não consultado no UAU (D5b.5 da fatia 5b). */
    public function scopePendentes(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q
            ->where(fn (Builder $q) => $q->whereNotNull('cpf')->whereNull('resultado_pf'))
            ->orWhere(fn (Builder $q) => $q->whereNotNull('cnpj')->whereNull('resultado_pj')));
    }

    /** Linhas que pedem atenção: documento com problema, sem documento ou não processado. */
    public function scopeComProblema(Builder $query): void
    {
        $problemas = array_map(
            fn (ResultadoImportacao $resultado) => $resultado->value,
            array_filter(ResultadoImportacao::cases(), fn (ResultadoImportacao $resultado) => $resultado->comProblema()),
        );

        $query->where(fn (Builder $q) => $q
            ->whereIn('resultado_pf', $problemas)
            ->orWhereIn('resultado_pj', $problemas)
            ->orWhere(fn (Builder $q) => $q->whereNull('cpf')->whereNull('cnpj'))
            ->orWhere(fn (Builder $q) => $q->pendentes()));
    }

    public function importacao(): BelongsTo
    {
        return $this->belongsTo(ImportacaoPessoa::class, 'importacao_pessoa_id');
    }
}
