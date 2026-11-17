<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** Benefício padrão de um cargo: aplicado ao colaborador na conclusão da admissão (E3, fatia 8). */
class CargoBeneficio extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'cargo_beneficios';

    protected $fillable = [
        'cargo_id',
        'beneficio_id',
    ];

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class);
    }

    public function beneficio(): BelongsTo
    {
        return $this->belongsTo(Beneficio::class);
    }
}
