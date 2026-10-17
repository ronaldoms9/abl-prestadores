<?php

namespace App\Models;

use App\Enums\DirecaoIntegracao;
use App\Enums\StatusIntegracao;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class IntegracaoLog extends Model
{
    use HasFactory;

    protected $table = 'integracao_logs';

    /**
     * Com milissegundos: executado_em é timestamp(3) e ordena as chamadas de
     * uma mesma cadeia. Sem isso, o Eloquent grava só até o segundo.
     */
    protected $dateFormat = 'Y-m-d H:i:s.v';

    protected $fillable = [
        'direcao',
        'entidade_type',
        'entidade_id',
        'operacao',
        'rota',
        'chave_idempotencia',
        'payload',
        'resposta',
        'http_status',
        'status',
        'tentativas',
        'erro',
        'duracao_ms',
        'executado_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'direcao' => DirecaoIntegracao::class,
            'status' => StatusIntegracao::class,
            'payload' => 'array',
            'resposta' => 'array',
            'http_status' => 'integer',
            'tentativas' => 'integer',
            'duracao_ms' => 'integer',
            'executado_em' => 'datetime',
        ];
    }

    public function entidade(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeFalhas(Builder $query): Builder
    {
        return $query->where('status', StatusIntegracao::Erro);
    }

    public function scopeNoPeriodo(Builder $query, mixed $de, mixed $ate): Builder
    {
        return $query->whereBetween('executado_em', [$de, $ate]);
    }
}
