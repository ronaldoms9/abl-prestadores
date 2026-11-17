<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use App\Models\Concerns\TemVigencia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class EquipeGestor extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes, TemVigencia;

    protected $table = 'equipe_gestores';

    protected $fillable = [
        'equipe_id',
        'user_id',
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

    public function equipe(): BelongsTo
    {
        return $this->belongsTo(Equipe::class);
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
