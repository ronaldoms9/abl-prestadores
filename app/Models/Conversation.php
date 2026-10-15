<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Conversation extends Model
{
    use BelongsToTenant, HasFactory;

    public const ESTADO_BOT = 'bot';
    public const ESTADO_HUMANO = 'humano';
    public const ESTADO_PAUSADA = 'pausada';

    protected $fillable = [
        'tenant_id',
        'chat_id',
        'estado',
        'pausada_ate',
        'debounce_token',
        'ultima_mensagem_at',
        'expurgo_em',
    ];

    protected $casts = [
        'pausada_ate' => 'datetime',
        'ultima_mensagem_at' => 'datetime',
        'expurgo_em' => 'datetime',
    ];

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    /** Token novo a cada mensagem recebida: invalida a geração que estiver em curso. */
    public function novoDebounceToken(): string
    {
        $token = (string) Str::uuid();

        $this->forceFill(['debounce_token' => $token])->save();

        return $token;
    }

    public function tokenAindaVale(string $token): bool
    {
        return $this->debounce_token === $token;
    }

    public function botEstaNoComando(): bool
    {
        if ($this->estado === self::ESTADO_HUMANO) {
            return false;
        }

        if ($this->estado === self::ESTADO_PAUSADA) {
            return $this->pausada_ate === null || $this->pausada_ate->isPast();
        }

        return true;
    }

    public function passarParaHumano(int $silencioHoras): void
    {
        $this->forceFill([
            'estado' => self::ESTADO_HUMANO,
            'pausada_ate' => now()->addHours($silencioHoras),
        ])->save();
    }

    public function devolverAoBot(): void
    {
        $this->forceFill([
            'estado' => self::ESTADO_BOT,
            'pausada_ate' => null,
        ])->save();
    }
}
