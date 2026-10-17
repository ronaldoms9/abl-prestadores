<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class VinculoSugerido extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'vinculos_sugeridos';

    protected $fillable = [
        'pessoa_fisica_id',
        'pessoa_juridica_id',
        'importacao_pessoa_id',
        'utilizado_em',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'utilizado_em' => 'datetime',
        ];
    }

    public function pessoaFisica(): BelongsTo
    {
        return $this->belongsTo(PessoaFisica::class, 'pessoa_fisica_id');
    }

    public function pessoaJuridica(): BelongsTo
    {
        return $this->belongsTo(PessoaJuridica::class, 'pessoa_juridica_id');
    }

    public function importacao(): BelongsTo
    {
        return $this->belongsTo(ImportacaoPessoa::class, 'importacao_pessoa_id');
    }
}
