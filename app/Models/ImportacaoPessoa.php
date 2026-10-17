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

    public function emAndamento(): bool
    {
        return in_array($this->status, [StatusImportacao::Pendente, StatusImportacao::Processando], true);
    }

    /**
     * Contagens da carga (D5b.10): resultados de PF e PJ, vínculos sugeridos por
     * esta carga, linhas sem documento e linhas ainda não processadas.
     *
     * @return array{linhas: int, pf: array<string, int>, pj: array<string, int>, vinculos: int, sem_documento: int, nao_processadas: int}
     */
    public function calcularResumo(): array
    {
        $contar = fn (string $coluna) => $this->linhas()
            ->whereNotNull($coluna)
            ->groupBy($coluna)
            ->selectRaw("{$coluna} as resultado, count(*) as total")
            ->pluck('total', 'resultado')
            ->map(fn ($total) => (int) $total)
            ->all();

        return [
            'linhas' => $this->linhas()->count(),
            'pf' => $contar('resultado_pf'),
            'pj' => $contar('resultado_pj'),
            'vinculos' => $this->vinculosSugeridos()->count(),
            'sem_documento' => $this->linhas()->whereNull('cpf')->whereNull('cnpj')->count(),
            'nao_processadas' => $this->linhas()->pendentes()->count(),
        ];
    }

    /** O resumo gravado ao fim da carga; em andamento, calculado na hora. */
    public function resumoAtual(): array
    {
        return $this->emAndamento() || $this->resumo === null ? $this->calcularResumo() : $this->resumo;
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
