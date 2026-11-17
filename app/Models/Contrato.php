<?php

namespace App\Models;

use App\Enums\MotivoAditivo;
use App\Enums\StatusContrato;
use App\Enums\TipoContrato;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Contrato, aditivo ou distrato de um colaborador. Gerado é imutável (E3, fatia 3). */
class Contrato extends Model
{
    use Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'contratos';

    protected $fillable = [
        'colaborador_id',
        'contrato_modelo_id',
        'contrato_pai_id',
        'tipo',
        'motivo',
        'numero',
        'ano',
        'sequencia',
        'conteudo_renderizado',
        'dados',
        'pdf_path',
        'assinado_path',
        'vigencia_inicio',
        'vigencia_fim',
        'status',
        'gerado_em',
        'assinado_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoContrato::class,
            'motivo' => MotivoAditivo::class,
            'status' => StatusContrato::class,
            'dados' => 'array',
            'vigencia_inicio' => 'date',
            'vigencia_fim' => 'date',
            'gerado_em' => 'datetime',
            'assinado_em' => 'datetime',
        ];
    }

    /** Campos que não mudam depois que o contrato sai do rascunho. Só arquivos, assinatura e encerramento mexem. */
    public const PROTEGIDOS = ['colaborador_id', 'contrato_modelo_id', 'contrato_pai_id', 'tipo', 'motivo', 'numero', 'ano', 'sequencia', 'conteudo_renderizado', 'dados', 'vigencia_inicio', 'vigencia_fim', 'gerado_em'];

    protected static function booted(): void
    {
        // Contrato gerado é imutável: correção é um novo aditivo (ou cancelar o rascunho antes de gerar).
        static::updating(function (Contrato $contrato) {
            if ($contrato->getRawOriginal('status') !== StatusContrato::Rascunho->value && $contrato->isDirty(self::PROTEGIDOS)) {
                throw new \LogicException('Contrato gerado não pode ser alterado. Faça um aditivo.');
            }
        });

        static::deleting(function (Contrato $contrato) {
            if ($contrato->getRawOriginal('status') !== StatusContrato::Rascunho->value) {
                throw new \LogicException('Contrato gerado não pode ser excluído. Só rascunho pode ser cancelado.');
            }
        });
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function modelo(): BelongsTo
    {
        return $this->belongsTo(ContratoModelo::class, 'contrato_modelo_id');
    }

    public function pai(): BelongsTo
    {
        return $this->belongsTo(self::class, 'contrato_pai_id');
    }

    /** Aditivos e distrato que apontam para este contrato. */
    public function filhos(): HasMany
    {
        return $this->hasMany(self::class, 'contrato_pai_id');
    }

    protected function prefixoAuditoria(): string
    {
        return 'contrato';
    }

    protected function camposAuditados(): array
    {
        return ['colaborador_id', 'contrato_modelo_id', 'contrato_pai_id', 'tipo', 'numero', 'vigencia_inicio', 'vigencia_fim', 'status'];
    }
}
