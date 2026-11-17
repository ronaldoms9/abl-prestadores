<?php

namespace App\Models;

use App\Enums\DecisaoFerias;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class FeriasAprovacao extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'ferias_aprovacoes';

    protected $fillable = [
        'ferias_solicitacao_id',
        'nivel',
        'aprovador_id',
        'decisao',
        'observacao',
        'decidido_em',
        'escalada',
        'motivo_escalada',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'nivel' => 'integer',
            'decisao' => DecisaoFerias::class,
            'decidido_em' => 'datetime',
            'escalada' => 'boolean',
        ];
    }

    public function solicitacao(): BelongsTo
    {
        return $this->belongsTo(FeriasSolicitacao::class, 'ferias_solicitacao_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovador_id');
    }
}
