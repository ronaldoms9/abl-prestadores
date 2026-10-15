<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    use BelongsToTenant, HasFactory;

    public const ENTRADA = 'entrada';
    public const SAIDA = 'saida';

    protected $fillable = [
        'conversation_id',
        'tenant_id',
        'direcao',
        'tipo',
        'waha_message_id',
        'conteudo',
        'transcricao',
        'tokens_entrada',
        'tokens_saida',
        'custo_usd',
        'modelo',
    ];

    protected $casts = [
        'tokens_entrada' => 'integer',
        'tokens_saida' => 'integer',
        'custo_usd' => 'float',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /** O que o modelo lê: transcrição quando houver, senão o conteúdo. */
    public function textoUtil(): ?string
    {
        return $this->transcricao ?: $this->conteudo;
    }
}
