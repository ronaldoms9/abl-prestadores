<?php

namespace App\Models;

use App\Enums\MotivoAditivo;
use App\Enums\TipoContrato;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ContratoModelo extends Model
{
    use Ativavel, Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'contrato_modelos';

    protected $fillable = [
        'nome',
        'tipo',
        'motivo',
        'corpo_html',
        'versao',
        'modelo_anterior_id',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoContrato::class,
            'motivo' => MotivoAditivo::class,
            'versao' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Modelo ativo de aditivo para um motivo: o que declara o motivo, ou, na falta,
     * o genérico (sem motivo). Null quando não há nenhum.
     */
    public static function aditivoPara(MotivoAditivo $motivo): ?self
    {
        $ativos = static::query()->ativos()->where('tipo', TipoContrato::Aditivo->value)->orderBy('id')->get();

        return $ativos->firstWhere('motivo', $motivo) ?? $ativos->firstWhere('motivo', null);
    }

    public function anterior(): BelongsTo
    {
        return $this->belongsTo(self::class, 'modelo_anterior_id');
    }

    public function contratos(): HasMany
    {
        return $this->hasMany(Contrato::class, 'contrato_modelo_id');
    }

    protected function prefixoAuditoria(): string
    {
        return 'contrato-modelo';
    }

    protected function camposAuditados(): array
    {
        return ['nome', 'tipo', 'motivo', 'versao', 'ativo'];
    }
}
