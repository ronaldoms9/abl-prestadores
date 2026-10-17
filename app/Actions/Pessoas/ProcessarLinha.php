<?php

namespace App\Actions\Pessoas;

use App\Actions\Uau\ImportarPessoa;
use App\Enums\ResultadoImportacao;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use App\Models\ImportacaoPessoaLinha;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;
use App\Models\VinculoSugerido;

/**
 * Consulta no UAU o CPF e o CNPJ pendentes de uma linha da planilha e sugere o
 * vínculo entre as duas pessoas (D5b.5 a D5b.7 da fatia 5b).
 *
 * Cada documento grava o resultado assim que volta do UAU: se o UAU cair no
 * meio, a nova tentativa não consulta de novo o que já está pronto.
 *
 * @throws \App\Integrations\Uau\Exceptions\UauIndisponivel
 */
class ProcessarLinha
{
    public function __construct(private readonly ImportarPessoa $importar)
    {
    }

    public function executar(ImportacaoPessoaLinha $linha, int $tentativa = 1): void
    {
        $this->consultar($linha, 'cpf', 'resultado_pf', $tentativa);
        $this->consultar($linha, 'cnpj', 'resultado_pj', $tentativa);
        $this->sugerirVinculo($linha);
    }

    private function consultar(ImportacaoPessoaLinha $linha, string $campo, string $resultado, int $tentativa): void
    {
        if ($linha->{$campo} === null || $linha->{$resultado} !== null) {
            return;
        }

        try {
            $linha->{$resultado} = $this->importar->executar($linha->{$campo}, $tentativa)->resultado;
        } catch (UauRejeitou $e) {
            // Uma recusa fica na linha e não derruba a carga (D5b.6).
            $linha->{$resultado} = ResultadoImportacao::Recusada;
            $linha->mensagem = trim($linha->mensagem.' '.strtoupper($campo).': '.$e->getMessage());
        }

        $linha->save();
    }

    /**
     * Vínculo quando a linha traz CPF e CNPJ e as duas pessoas existem (D5b.7).
     * withTrashed: um vínculo descartado não é recriado por outra carga.
     */
    private function sugerirVinculo(ImportacaoPessoaLinha $linha): void
    {
        if (! $linha->resultado_pf?->temPessoa() || ! $linha->resultado_pj?->temPessoa()) {
            return;
        }

        $pessoaFisica = PessoaFisica::where('cpf', $linha->cpf)->value('id');
        $pessoaJuridica = PessoaJuridica::where('cnpj', $linha->cnpj)->value('id');

        if ($pessoaFisica === null || $pessoaJuridica === null) {
            return;
        }

        VinculoSugerido::withTrashed()->firstOrCreate(
            ['pessoa_fisica_id' => $pessoaFisica, 'pessoa_juridica_id' => $pessoaJuridica],
            ['importacao_pessoa_id' => $linha->importacao_pessoa_id],
        );
    }
}
