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

    /** Apelido polimórfico => [rótulo, atributo que identifica]. */
    public const ENTIDADES = [
        'empresa' => ['Empresa', 'razao_social'],
        'obra' => ['Obra', 'descricao'],
        'pessoa-fisica' => ['Pessoa física', 'nome'],
        'pessoa-juridica' => ['Pessoa jurídica', 'razao_social'],
    ];

    protected $fillable = [
        'direcao',
        'entidade_type',
        'entidade_id',
        'operacao',
        'rota',
        'chave_idempotencia',
        'correlacao',
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

    /**
     * Falhas sem uma chamada posterior com sucesso da mesma operação e da mesma
     * chave (D8.3 da fatia 8). Chaves nulas contam como iguais (empresas, obras, autenticação).
     */
    public function scopeSemSolucao(Builder $query): Builder
    {
        return $query->falhas()->whereNotExists(fn ($sub) => self::sucessoPosterior($sub));
    }

    public function scopeResolvidas(Builder $query): Builder
    {
        return $query->falhas()->whereExists(fn ($sub) => self::sucessoPosterior($sub));
    }

    /** Acrescenta a coluna "resolvida" (1 ou null) sem uma consulta por linha. */
    public function scopeComResolucao(Builder $query): Builder
    {
        return $query->select('integracao_logs.*')
            ->selectSub(fn ($sub) => self::sucessoPosterior($sub)->selectRaw('1')->limit(1), 'resolvida');
    }

    public function resolvida(): bool
    {
        if ($this->status !== StatusIntegracao::Erro) {
            return false;
        }

        // Com comResolucao() a coluna já veio na consulta; sem ela, consulta agora.
        return array_key_exists('resolvida', $this->attributes)
            ? (bool) $this->attributes['resolvida']
            : $this->resolvidaPor() !== null;
    }

    /** A primeira chamada com sucesso que resolveu esta falha, se houver. */
    public function resolvidaPor(): ?self
    {
        if ($this->status !== StatusIntegracao::Erro) {
            return null;
        }

        return self::query()
            ->where('operacao', $this->operacao)
            ->where('status', StatusIntegracao::Sucesso)
            ->where('executado_em', '>', $this->executado_em)
            ->where(fn (Builder $q) => $this->chave_idempotencia === null
                ? $q->whereNull('chave_idempotencia')
                : $q->where('chave_idempotencia', $this->chave_idempotencia))
            ->oldest('executado_em')
            ->first();
    }

    /** "Empresa · Construtora X", "Pessoa física · Maria" ou null sem entidade. */
    public function descreverEntidade(): ?string
    {
        if ($this->entidade_type === null) {
            return null;
        }

        [$rotulo, $atributo] = self::ENTIDADES[$this->entidade_type] ?? [$this->entidade_type, null];
        $nome = $atributo ? $this->entidade?->{$atributo} : null;

        return $rotulo.' · '.($nome ?? '#'.$this->entidade_id);
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $sub
     */
    private static function sucessoPosterior($sub)
    {
        return $sub->from('integracao_logs as posterior')
            ->whereColumn('posterior.operacao', 'integracao_logs.operacao')
            ->where('posterior.status', StatusIntegracao::Sucesso->value)
            ->whereColumn('posterior.executado_em', '>', 'integracao_logs.executado_em')
            ->where(fn ($q) => $q
                ->whereColumn('posterior.chave_idempotencia', 'integracao_logs.chave_idempotencia')
                ->orWhere(fn ($q) => $q->whereNull('posterior.chave_idempotencia')->whereNull('integracao_logs.chave_idempotencia')));
    }
}
