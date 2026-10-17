<?php

namespace App\Models;

use App\Enums\OrigemSincronizacao;
use App\Enums\StatusSincronizacao;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Uma execução da cadeia de conciliação com o UAU (D8.1 da fatia 8): quando,
 * por quê (agendada ou manual), quem pediu e como terminou.
 */
class Sincronizacao extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    /** Cache do indicador do topo (fatia 8), limpo quando uma sincronização termina. */
    public const CACHE_INDICADOR = 'integracao:indicador';

    protected $table = 'sincronizacoes';

    protected $fillable = [
        'origem',
        'status',
        'iniciada_em',
        'concluida_em',
        'erro',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'origem' => OrigemSincronizacao::class,
            'status' => StatusSincronizacao::class,
            'iniciada_em' => 'datetime',
            'concluida_em' => 'datetime',
        ];
    }

    public function scopeEmAndamento(Builder $query): void
    {
        $query->where('status', StatusSincronizacao::EmAndamento);
    }

    public function scopeConcluidas(Builder $query): void
    {
        $query->where('status', StatusSincronizacao::Concluida);
    }

    public static function ultimaConcluida(): ?self
    {
        return self::concluidas()->latest('concluida_em')->first();
    }

    public function concluir(): void
    {
        $this->update(['status' => StatusSincronizacao::Concluida, 'concluida_em' => now()]);
        Cache::forget(self::CACHE_INDICADOR);
    }

    public function falhar(string $erro): void
    {
        $this->update([
            'status' => StatusSincronizacao::Falhou,
            'concluida_em' => now(),
            'erro' => Str::limit($erro, 2000, ''),
        ]);
        Cache::forget(self::CACHE_INDICADOR);
    }
}
