<?php

namespace App\Models;

use App\Enums\TipoCalculoBeneficio;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Beneficio extends Model
{
    use Ativavel, Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'beneficios';

    protected $fillable = [
        'nome',
        'tipo_calculo',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo_calculo' => TipoCalculoBeneficio::class,
            'ativo' => 'boolean',
        ];
    }

    public function valores(): HasMany
    {
        return $this->hasMany(BeneficioValor::class, 'beneficio_id');
    }

    public function vinculos(): HasMany
    {
        return $this->hasMany(ColaboradorBeneficio::class, 'beneficio_id');
    }

    protected function prefixoAuditoria(): string
    {
        return 'beneficio';
    }

    protected function camposAuditados(): array
    {
        return ['nome', 'tipo_calculo', 'ativo'];
    }
}
