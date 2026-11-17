<?php

namespace App\Integrations\ConsultaCnpj;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Consulta um CNPJ na BrasilAPI. Resultado e "não encontrado" ficam em cache
 * por 24 h; falha de rede ou da API não vai para o cache e vira
 * ConsultaCnpjIndisponivel, que a tela trata como "preencha à mão".
 */
class ConsultaCnpj
{
    /**
     * @throws ConsultaCnpjIndisponivel
     */
    public function consultar(string $cnpj): ?DadosCnpj
    {
        $chave = "consulta-cnpj:{$cnpj}";

        $guardado = Cache::get($chave);
        if ($guardado !== null) {
            return $guardado === 'nao-encontrado' ? null : DadosCnpj::daBrasilApi($guardado);
        }

        try {
            $resposta = Http::acceptJson()
                ->timeout(config('cadastros.consulta_cnpj.timeout'))
                ->get(rtrim(config('cadastros.consulta_cnpj.url'), '/').'/'.$cnpj);
        } catch (ConnectionException $e) {
            throw new ConsultaCnpjIndisponivel('Sem resposta da consulta de CNPJ.', previous: $e);
        }

        $validade = now()->addHours(config('cadastros.consulta_cnpj.cache_horas'));

        if ($resposta->status() === 404) {
            Cache::put($chave, 'nao-encontrado', $validade);

            return null;
        }

        if ($resposta->failed() || ! is_array($resposta->json())) {
            throw new ConsultaCnpjIndisponivel('A consulta de CNPJ respondeu com erro ('.$resposta->status().').');
        }

        Cache::put($chave, $resposta->json(), $validade);

        return DadosCnpj::daBrasilApi($resposta->json());
    }
}
