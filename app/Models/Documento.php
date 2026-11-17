<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Documento extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    public const SEM_VALIDADE = 'sem_validade';

    public const VALIDO = 'valido';

    public const A_VENCER = 'a_vencer';

    public const VENCIDO = 'vencido';

    protected $table = 'documentos';

    protected $fillable = [
        'documento_tipo_id',
        'tipo',
        'arquivo_path',
        'arquivo_nome',
        'arquivo_mime',
        'arquivo_tamanho',
        'validade',
        'observacao',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'validade' => 'date',
            'arquivo_tamanho' => 'integer',
        ];
    }

    public function documentavel(): MorphTo
    {
        return $this->morphTo();
    }

    public function tipoDocumento(): BelongsTo
    {
        return $this->belongsTo(DocumentoTipo::class, 'documento_tipo_id');
    }

    /**
     * Situação no dia: sem validade, válido, a vencer (até $dias dias, inclusive o último)
     * ou vencido (a validade é o último dia de uso: vence no dia seguinte).
     */
    public function situacaoEm(CarbonInterface $hoje, int $dias): string
    {
        if ($this->validade === null) {
            return self::SEM_VALIDADE;
        }

        $hoje = $hoje->copy()->startOfDay();

        return match (true) {
            $this->validade->lt($hoje) => self::VENCIDO,
            $this->validade->lte($hoje->copy()->addDays($dias)) => self::A_VENCER,
            default => self::VALIDO,
        };
    }
}
