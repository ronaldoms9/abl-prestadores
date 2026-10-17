<?php

namespace App\Casts;

use App\Support\Endereco;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class AsEndereco implements CastsAttributes
{
    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Endereco
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dados = is_string($value) ? json_decode($value, true) : $value;

        if (! is_array($dados)) {
            return null;
        }

        $endereco = Endereco::fromArray($dados);

        return $endereco->vazio() ? null : $endereco;
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof Endereco) {
            return $value->vazio() ? null : json_encode($value->toArray(), JSON_UNESCAPED_UNICODE);
        }

        if (is_array($value)) {
            $endereco = Endereco::fromArray($value);
            return $endereco->vazio() ? null : json_encode($endereco->toArray(), JSON_UNESCAPED_UNICODE);
        }

        return null;
    }
}
