<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use App\Models\Concerns\TemVigencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Parte de um colaborador em uma obra, com vigência: os percentuais vigentes de um colaborador somam 100 (E4, fatia 10). */
class ColaboradorObra extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes, TemVigencia;

    protected $table = 'colaborador_obras';

    protected $fillable = ['colaborador_id', 'obra_id', 'percentual', 'inicio', 'fim'];

    /** @return array<string, string|class-string> */
    protected function casts(): array
    {
        return ['percentual' => 'decimal:2', 'inicio' => 'date', 'fim' => 'date'];
    }

    /** Percentual em pontos-base (1% = 100), para somar e ratear sem decimal. */
    public function pontosBase(): int
    {
        return (int) round(((float) $this->percentual) * 100);
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function obra(): BelongsTo
    {
        return $this->belongsTo(Obra::class);
    }
}
