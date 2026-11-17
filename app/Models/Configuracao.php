<?php

namespace App\Models;

use App\Models\Concerns\RegistraAutoria;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Configuracao extends Model
{
    use HasFactory, RegistraAutoria, SoftDeletes;

    protected $table = 'configuracoes';

    protected $fillable = [
        'chave',
        'valor',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'valor' => 'array',
        ];
    }

    /** Valor de um parâmetro, ou o padrão quando a chave não existe. */
    public static function obter(string $chave, mixed $padrao = null): mixed
    {
        $registro = static::where('chave', $chave)->first();

        return $registro === null ? $padrao : ($registro->valor ?? $padrao);
    }

    public static function definir(string $chave, mixed $valor): void
    {
        static::updateOrCreate(['chave' => $chave], ['valor' => $valor]);
    }

    public static function esquecer(string $chave): void
    {
        static::withTrashed()->where('chave', $chave)->forceDelete();
    }
}
