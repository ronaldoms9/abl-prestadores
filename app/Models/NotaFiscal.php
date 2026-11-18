<?php

namespace App\Models;

use App\Enums\OrigemDadosNota;
use App\Enums\StatusNota;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Nota fiscal (NFS-e) de um colaborador PJ para um fechamento. Valores em reais; CNPJs só com dígitos. */
class NotaFiscal extends Model
{
    use Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'notas_fiscais';

    protected $fillable = [
        'colaborador_id',
        'competencia_id',
        'fechamento_id',
        'numero',
        'serie',
        'chave_acesso',
        'data_emissao',
        'cnpj_emissor',
        'cnpj_tomador',
        'valor_bruto',
        'valor_retencoes',
        'valor_liquido',
        'xml_path',
        'pdf_path',
        'status',
        'origem_dados',
        'motivo_recusa',
        'divergencias',
        'justificativa_aprovacao',
        'enviada_por',
        'decidido_por',
        'decidido_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'data_emissao' => 'date',
            'valor_bruto' => 'decimal:2',
            'valor_retencoes' => 'decimal:2',
            'valor_liquido' => 'decimal:2',
            'status' => StatusNota::class,
            'origem_dados' => OrigemDadosNota::class,
            'divergencias' => 'array',
            'decidido_em' => 'datetime',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function competencia(): BelongsTo
    {
        return $this->belongsTo(Competencia::class);
    }

    public function fechamento(): BelongsTo
    {
        return $this->belongsTo(Fechamento::class);
    }

    public function retencoes(): HasMany
    {
        return $this->hasMany(NotaRetencao::class, 'nota_fiscal_id');
    }

    public function titulo(): HasOne
    {
        return $this->hasOne(TituloPagar::class, 'nota_fiscal_id');
    }

    public function enviadaPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enviada_por');
    }

    public function decididoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decidido_por');
    }

    protected function prefixoAuditoria(): string
    {
        return 'nota-fiscal';
    }

    protected function camposAuditados(): array
    {
        return ['colaborador_id', 'competencia_id', 'fechamento_id', 'numero', 'serie', 'chave_acesso', 'valor_bruto', 'status'];
    }
}
