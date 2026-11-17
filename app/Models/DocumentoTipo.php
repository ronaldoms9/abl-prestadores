<?php

namespace App\Models;

use App\Enums\AplicaDocumento;
use App\Models\Concerns\Ativavel;
use App\Models\Concerns\Auditavel;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DocumentoTipo extends Model
{
    use Ativavel, Auditavel, HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'documento_tipos';

    protected $fillable = ['nome', 'aplica_a', 'exige_validade', 'obrigatorio_na_admissao', 'ativo'];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'aplica_a' => AplicaDocumento::class,
            'exige_validade' => 'boolean',
            'obrigatorio_na_admissao' => 'boolean',
            'ativo' => 'boolean',
        ];
    }

    public function documentos(): HasMany
    {
        return $this->hasMany(Documento::class, 'documento_tipo_id');
    }

    /** Tipos ativos que servem ao dono (apelido de morph: pessoa-fisica, pessoa-juridica, colaborador). */
    public function scopeParaDono(Builder $query, string $apelido): Builder
    {
        $aceitos = collect(AplicaDocumento::cases())->filter(fn (AplicaDocumento $a) => $a->aceita($apelido))->map->value->all();

        return $query->ativos()->whereIn('aplica_a', $aceitos);
    }

    protected function prefixoAuditoria(): string
    {
        return 'documento-tipo';
    }

    protected function camposAuditados(): array
    {
        return ['nome', 'aplica_a', 'exige_validade', 'obrigatorio_na_admissao', 'ativo'];
    }
}
