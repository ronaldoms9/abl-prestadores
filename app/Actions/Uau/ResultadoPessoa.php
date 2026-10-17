<?php

namespace App\Actions\Uau;

use App\Enums\ResultadoImportacao;
use App\Models\PessoaFisica;
use App\Models\PessoaJuridica;

/** Resultado de ImportarPessoa (D5.7): o que aconteceu e a pessoa local, se houver. */
final readonly class ResultadoPessoa
{
    public function __construct(
        public ResultadoImportacao $resultado,
        public PessoaFisica|PessoaJuridica|null $pessoa = null,
    ) {
    }
}
