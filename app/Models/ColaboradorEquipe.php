<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use App\Models\Concerns\TemVigencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ColaboradorEquipe extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes, TemVigencia;

    protected $table = 'colaborador_equipes';

    protected $fillable = [
        'colaborador_id',
        'equipe_id',
        'inicio',
        'fim',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'inicio' => 'date',
            'fim' => 'date',
        ];
    }

    public function colaborador(): BelongsTo
    {
        return $this->belongsTo(Colaborador::class);
    }

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }
}
