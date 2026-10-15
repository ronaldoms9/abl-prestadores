<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'slug',
        'ativo_ate',
        'waha_instance',
        'waha_session',
        'teto_mensagens_mes',
        'mensagens_mes',
    ];

    protected $casts = [
        'ativo_ate' => 'date',
        'teto_mensagens_mes' => 'integer',
        'mensagens_mes' => 'integer',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function botConfig(): HasOne
    {
        return $this->hasOne(BotConfig::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    /** Assinatura vencida = bot parado. */
    public function estaAtivo(): bool
    {
        return $this->ativo_ate !== null && ! $this->ativo_ate->isBefore(today());
    }

    /** Teto rígido de mensagens do mês, sem excedente. */
    public function atingiuTeto(): bool
    {
        return $this->mensagens_mes >= $this->teto_mensagens_mes;
    }
}
