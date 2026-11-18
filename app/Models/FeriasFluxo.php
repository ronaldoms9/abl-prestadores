<?php

namespace App\Models;

use App\Enums\EscopoFluxoFerias;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Fluxo de aprovação de férias de um colaborador, equipe ou função, com seus níveis (E4, fatia 9). */
class FeriasFluxo extends Model
{
    use Ativavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'ferias_fluxos';

    protected $fillable = ['nome', 'escopo', 'equipe_id', 'cargo_id', 'colaborador_id', 'ativo'];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return ['escopo' => EscopoFluxoFerias::class, 'ativo' => 'boolean'];
    }

    /** O fluxo padrão: existe sempre, e só um. */
    public static function padrao(): self
    {
        return static::query()->where('escopo', EscopoFluxoFerias::Padrao->value)->first()
            ?? static::create(['nome' => 'Fluxo padrão', 'escopo' => EscopoFluxoFerias::Padrao, 'ativo' => true]);
    }

    public function niveis(): HasMany
    {
        return $this->hasMany(FeriasNivel::class, 'fluxo_id')->orderBy('ordem');
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function ePadrao(): bool
    {
        return $this->escopo === EscopoFluxoFerias::Padrao;
    }
}
