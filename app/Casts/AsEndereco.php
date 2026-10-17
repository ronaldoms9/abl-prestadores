<?php

namespace App\Casts;

use App\Support\Endereco;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Database\Eloquent\ComparesCastableAttributes;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

class AsEndereco implements CastsAttributes, ComparesCastableAttributes
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
     * Aceita Endereco, array, JSON em texto ou null. Qualquer outra coisa é erro
     * de quem chamou: falha em vez de apagar o endereço em silêncio.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws InvalidArgumentException
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_string($value)) {
            $value = json_decode($value, true);

            if (! is_array($value)) {
                throw new InvalidArgumentException("O campo {$key} recebeu um texto que não é um JSON de endereço.");
            }
        }

        $endereco = match (true) {
            $value instanceof Endereco => $value,
            is_array($value) => Endereco::fromArray($value),
            default => throw new InvalidArgumentException("O campo {$key} espera Endereco, array ou JSON; recebeu ".get_debug_type($value).'.'),
        };

        return $endereco->vazio() ? null : json_encode($endereco->toArray(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Compara pelo conteúdo, não pelo texto: o MySQL devolve o JSON com as
     * chaves em outra ordem, e o mesmo endereço não pode contar como alterado.
     */
    public function compare(Model $model, string $key, mixed $firstValue, mixed $secondValue): bool
    {
        return $this->get($model, $key, $firstValue, [])?->toArray()
            === $this->get($model, $key, $secondValue, [])?->toArray();
    }
}
