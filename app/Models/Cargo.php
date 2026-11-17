<?php

namespace App\Models;

use App\Models\Concerns\Ativavel;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use App\Support\Dinheiro;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cargo extends Model
{
    use Ativavel, Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'cargos';

    protected $fillable = [
        'nome',
        'descricao',
        'faixa_min',
        'faixa_max',
        'ativo',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'faixa_min' => 'decimal:2',
            'faixa_max' => 'decimal:2',
            'ativo' => 'boolean',
        ];
    }

    public function colaboradores(): HasMany
    {
        return $this->hasMany(Colaborador::class, 'cargo_id');
    }

    public function exigencias(): HasMany
    {
        return $this->hasMany(CargoQualificacao::class, 'cargo_id');
    }

    protected function prefixoAuditoria(): string
    {
        return 'cargo';
    }

    protected function camposAuditados(): array
    {
        return ['nome', 'descricao', 'faixa_min', 'faixa_max', 'ativo'];
    }

    /** Aviso (nunca bloqueio) quando o valor sai da faixa do cargo; null quando está dentro ou não há faixa. */
    public function avisoDeFaixa(int $centavos): ?string
    {
        $min = $this->faixa_min !== null ? Dinheiro::centavos($this->faixa_min) : null;
        $max = $this->faixa_max !== null ? Dinheiro::centavos($this->faixa_max) : null;

        return match (true) {
            $min !== null && $centavos < $min => 'abaixo da faixa do cargo ('.Dinheiro::formatar($min).' a '.($max !== null ? Dinheiro::formatar($max) : 'sem teto').')',
            $max !== null && $centavos > $max => 'acima da faixa do cargo ('.($min !== null ? Dinheiro::formatar($min) : 'sem piso').' a '.Dinheiro::formatar($max).')',
            default => null,
        };
    }
}
