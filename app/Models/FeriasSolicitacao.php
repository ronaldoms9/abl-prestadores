<?php

namespace App\Models;

use App\Enums\StatusSolicitacaoFerias;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeriasSolicitacao extends Model
{
    use Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'ferias_solicitacoes';

    protected $fillable = [
        'colaborador_id',
        'ferias_periodo_id',
        'inicio',
        'fim',
        'dias',
        'status',
        'solicitado_por',
        'nivel_atual',
        'observacao',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
            'dias' => 'integer',
            'status' => StatusSolicitacaoFerias::class,
            'nivel_atual' => 'integer',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function periodo(): BelongsTo
    {
        return $this->belongsTo(FeriasPeriodo::class, 'ferias_periodo_id');
    }

    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function aprovacoes(): HasMany
    {
        return $this->hasMany(FeriasAprovacao::class, 'ferias_solicitacao_id');
    }

    protected function prefixoAuditoria(): string
    {
        return 'ferias-solicitacao';
    }

    protected function camposAuditados(): array
    {
        return ['colaborador_id', 'ferias_periodo_id', 'inicio', 'fim', 'dias', 'status', 'nivel_atual'];
    }
}
