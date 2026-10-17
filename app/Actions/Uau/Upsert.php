<?php

namespace App\Actions\Uau;

use Illuminate\Database\Eloquent\Model;

/**
 * Gravação comum das importações do UAU (D5.3 da fatia 5): preenche, marca
 * ativo e sincronizado_em, restaura a excluída e diz o que aconteceu.
 *
 * @internal Usado só pelas Actions de App\Actions\Uau.
 */
final class Upsert
{
    public const CRIADA = 'criada';

    public const ATUALIZADA = 'atualizada';

    public const REATIVADA = 'reativada';

    public const SEM_MUDANCA = 'sem_mudanca';

    /**
     * @param  Model  $registro  Encontrado com withTrashed(), ou um model novo.
     * @param  array<string, mixed>  $atributos  Saída do mapper. Se trouxer 'ativo', ele vale.
     */
    public static function gravar(Model $registro, array $atributos): string
    {
        $existia = $registro->exists;
        $estavaFora = $existia && ($registro->trashed() || ! $registro->ativo);
        $ativo = $atributos['ativo'] ?? true;

        $registro->fill($atributos);
        $mudou = $registro->isDirty();

        $registro->ativo = $ativo;
        $registro->sincronizado_em = now();

        if ($registro->trashed()) {
            $registro->{$registro->getDeletedAtColumn()} = null;
        }

        $registro->save();

        return match (true) {
            ! $existia => self::CRIADA,
            $estavaFora && $ativo => self::REATIVADA,
            $mudou => self::ATUALIZADA,
            default => self::SEM_MUDANCA,
        };
    }

    /**
     * Marca os registros como inativos, sem apagar. Devolve quantos mudaram.
     *
     * @param  class-string<Model>  $model
     * @param  list<int>  $ids
     */
    public static function inativar(string $model, array $ids): int
    {
        if ($ids === []) {
            return 0;
        }

        return $model::query()->whereKey($ids)->update(['ativo' => false, 'sincronizado_em' => now()]);
    }
}
