<?php

namespace Database\Seeders;

use App\Enums\AplicaDocumento;
use App\Models\DocumentoTipo;
use Illuminate\Database\Seeder;

/**
 * Conjunto inicial de tipos de documento, só quando a tabela está vazia: a
 * ABL PRIME edita a lista na tela (e a lista do RH substitui esta), então rodar
 * o seeder de novo nunca recria um tipo que ela apagou ou renomeou.
 */
class DocumentoTipoSeeder extends Seeder
{
    public function run(): void
    {
        if (DocumentoTipo::withTrashed()->exists()) {
            return;
        }

        foreach ([
            ['Documento de identidade (RG ou CNH)', AplicaDocumento::PessoaFisica, false],
            ['Comprovante de residência', AplicaDocumento::PessoaFisica, false],
            ['Atestado de Saúde Ocupacional (ASO)', AplicaDocumento::Colaborador, true],
            ['Contrato social ou requerimento de empresário', AplicaDocumento::PessoaJuridica, false],
            ['Cartão CNPJ', AplicaDocumento::PessoaJuridica, false],
            ['Certidão negativa de débitos', AplicaDocumento::PessoaJuridica, true],
            ['Alvará de funcionamento', AplicaDocumento::PessoaJuridica, true],
            ['Contrato assinado', AplicaDocumento::Colaborador, false],
            ['Outros', AplicaDocumento::Todos, false],
        ] as [$nome, $aplica, $validade]) {
            DocumentoTipo::create(['nome' => $nome, 'aplica_a' => $aplica, 'exige_validade' => $validade]);
        }
    }
}
