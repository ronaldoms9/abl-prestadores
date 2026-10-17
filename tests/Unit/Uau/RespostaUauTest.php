<?php

namespace Tests\Unit\Uau;

use App\Integrations\Uau\RespostaUau;
use PHPUnit\Framework\TestCase;

class RespostaUauTest extends TestCase
{
    public function test_lista_simples_do_swagger(): void
    {
        $this->assertSame([['a' => 1], ['a' => 2]], RespostaUau::linhas([['a' => 1], ['a' => 2]]));
    }

    public function test_dataset_do_dotnet_com_linha_de_esquema(): void
    {
        $dataset = [['Pessoas' => [
            ['cod_pes' => 'System.Int32', 'nome_pes' => 'System.String'],
            ['cod_pes' => 10, 'nome_pes' => 'Ana'],
        ]]];

        $this->assertSame([['cod_pes' => 10, 'nome_pes' => 'Ana']], RespostaUau::linhas($dataset));
        $this->assertSame([['cod_pes' => 10, 'nome_pes' => 'Ana']], RespostaUau::linhas($dataset[0]));
    }

    public function test_objeto_unico(): void
    {
        $this->assertSame(['codigoPessoa' => 1, 'nomePessoa' => 'Ana'], RespostaUau::objeto(['codigoPessoa' => 1, 'nomePessoa' => 'Ana']));
        $this->assertNull(RespostaUau::objeto(null));
        $this->assertNull(RespostaUau::objeto([]));
        $this->assertNull(RespostaUau::objeto(''));
    }

    public function test_mensagem_e_detalhe_do_response_handler(): void
    {
        $erro = ['Mensagem' => ' Pessoa não encontrada. ', 'Descricao' => null, 'Detalhe' => 'at UAU.Pessoas...'];

        $this->assertSame('Pessoa não encontrada.', RespostaUau::mensagem($erro));
        $this->assertSame('at UAU.Pessoas...', RespostaUau::detalhe($erro));
        $this->assertSame('Falhou', RespostaUau::mensagem(['mensagem' => '', 'descricao' => 'Falhou']));
        $this->assertSame('erro em texto', RespostaUau::mensagem('erro em texto'));
        $this->assertNull(RespostaUau::mensagem([['Mensagem' => 'lista não é erro']]));
    }
}
