<?php

namespace App\Models;

use App\Enums\StatusCompetencia;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/** O mês de fechamento. `ano_mes` é "AAAA-MM" e é único. */
class Competencia extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'competencias';

    protected $fillable = [
        'ano_mes',
        'status',
        'aprovada_por',
        'aprovada_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'status' => StatusCompetencia::class,
            'aprovada_em' => 'datetime',
        ];
    }

    public function fechamentos(): HasMany
    {
        return $this->hasMany(Fechamento::class, 'competencia_id');
    }

    public function aprovador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprovada_por');
    }
}
