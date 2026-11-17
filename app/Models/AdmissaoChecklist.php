<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Estado de um item do checklist para um colaborador. `item` é cópia do nome configurado. */
class AdmissaoChecklist extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'admissao_checklist';

    protected $fillable = [
        'colaborador_id',
        'admissao_checklist_item_id',
        'item',
        'chave',
        'obrigatorio',
        'ordem',
        'concluido',
        'concluido_por',
        'concluido_em',
        'observacao',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'obrigatorio' => 'boolean',
            'ordem' => 'integer',
            'concluido' => 'boolean',
            'concluido_em' => 'datetime',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function configuracao(): BelongsTo
    {
        return $this->belongsTo(AdmissaoChecklistItem::class, 'admissao_checklist_item_id');
    }

    public function concluidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'concluido_por');
    }
}
