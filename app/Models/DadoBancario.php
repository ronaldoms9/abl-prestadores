<?php

namespace App\Models;

use App\Enums\TipoChavePix;
use App\Enums\TipoContaBancaria;
use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class DadoBancario extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'dados_bancarios';

    protected $fillable = [
        'banco',
        'agencia',
        'conta',
        'tipo',
        'tipo_chave_pix',
        'chave_pix',
        'principal',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoContaBancaria::class,
            'tipo_chave_pix' => TipoChavePix::class,
            'principal' => 'boolean',
        ];
    }

    public function pessoa(): MorphTo
    {
        return $this->morphTo();
    }
}
