<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class MetricDaily extends Model
{
    use BelongsToTenant, HasFactory;

    protected $table = 'metrics_daily';

    protected $fillable = [
        'tenant_id',
        'data',
        'conversas',
        'resolvidas',
        'fora_horario',
        'escaladas',
        'tokens_entrada',
        'tokens_saida',
        'custo_usd',
    ];

    protected $casts = [
        // Com formato explícito: sem ele a coluna é gravada como datetime e a
        // busca por 'Y-m-d' deixa de encontrar a linha do dia.
        'data' => 'date:Y-m-d',
        'custo_usd' => 'float',
    ];

    private const COLUNAS = [
        'conversas',
        'resolvidas',
        'fora_horario',
        'escaladas',
        'tokens_entrada',
        'tokens_saida',
        'custo_usd',
    ];

    /**
     * Incrementa contadores do dia sem depender de a linha já existir.
     *
     * O incremento é feito em SQL porque dois workers podem processar mensagens
     * do mesmo tenant ao mesmo tempo: ler-somar-gravar em PHP perderia contagem.
     */
    public static function registrar(int $tenantId, array $incrementos): void
    {
        $data = today()->toDateString();

        try {
            static::query()->firstOrCreate(['tenant_id' => $tenantId, 'data' => $data]);
        } catch (QueryException $e) {
            // Outro worker criou a linha no meio do caminho: seguir em frente.
        }

        $expressoes = [];

        foreach ($incrementos as $coluna => $valor) {
            if (! in_array($coluna, self::COLUNAS, true) || ! is_numeric($valor)) {
                continue;
            }

            $expressoes[$coluna] = DB::raw(sprintf('%s + %s', $coluna, (float) $valor));
        }

        if ($expressoes === []) {
            return;
        }

        static::query()
            ->where('tenant_id', $tenantId)
            ->where('data', $data)
            ->update($expressoes);
    }
}
