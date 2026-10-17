<?php

namespace App\Integrations\Uau;

use App\Integrations\Uau\Data\EmpresaUau;
use App\Integrations\Uau\Data\ObraUau;
use App\Integrations\Uau\Data\PessoaUau;
use Illuminate\Database\Eloquent\Model;

/**
 * Tudo o que o sistema pede ao UAU. Duas implementações: UauClient (HTTP) e
 * FakeUauClient (dados de exemplo, UAU_FAKE=true).
 *
 * Toda chamada grava uma linha em integracao_logs. Falhas saem como
 * Exceptions\UauIndisponivel (tentar de novo) ou Exceptions\UauRejeitou (não tentar).
 */
interface UauGateway
{
    /** @return list<EmpresaUau> */
    public function empresasAtivas(): array;

    /** @return list<ObraUau> Obras ativas de todas as empresas. */
    public function obrasAtivas(): array;

    /** Pessoa ativa ou inativa pelo CPF/CNPJ (com ou sem máscara); null quando não existe. */
    public function pessoaPorDocumento(string $documento): ?PessoaUau;

    /** Versão do web service do UAU; usada pelo uau:ping para validar as credenciais. */
    public function versao(): string;

    /**
     * Cópia do cliente cujas chamadas ficam ligadas à entidade, à chave de
     * idempotência e à tentativa do job no log. O original não muda.
     */
    public function comContexto(?Model $entidade = null, ?string $chaveIdempotencia = null, int $tentativa = 1): static;
}
