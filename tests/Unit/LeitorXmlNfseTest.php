<?php

namespace Tests\Unit;

use App\Services\NotaFiscal\LeitorXmlNfse;
use App\Services\NotaFiscal\XmlNaoLido;
use Tests\TestCase;

class LeitorXmlNfseTest extends TestCase
{
    private function fixture(string $nome): string
    {
        return file_get_contents(base_path("tests/Fixtures/nfse/{$nome}"));
    }

    public function test_le_o_padrao_abrasf_com_mascara_no_cnpj_e_iss_nao_retido(): void
    {
        $nota = app(LeitorXmlNfse::class)->ler($this->fixture('abrasf.xml'));

        $this->assertSame('abrasf', $nota->layout);
        $this->assertSame(['1234', 'A1', 'ABCD1234'], [$nota->numero, $nota->serie, $nota->chaveAcesso]);
        $this->assertSame('2026-10-02', $nota->emissao->toDateString());
        $this->assertSame(['12345678000195', '45723174000110'], [$nota->cnpjEmissor, $nota->cnpjTomador]);
        $this->assertSame([530000, 522050], [$nota->valorBruto, $nota->valorLiquido]);
        $this->assertSame(['IRRF' => 7950], $nota->retencoes, 'ISS não retido não é retenção e INSS zerado some');
        $this->assertSame(7950, $nota->totalRetencoes());
    }

    public function test_le_o_padrao_nacional_com_a_chave_do_id(): void
    {
        $nota = app(LeitorXmlNfse::class)->ler($this->fixture('nacional.xml'));

        $this->assertSame('nacional', $nota->layout);
        $this->assertSame(['12', '1'], [$nota->numero, $nota->serie]);
        $this->assertSame('35503082212345678000195000000000000125100000000001', $nota->chaveAcesso);
        $this->assertSame('2026-10-02', $nota->emissao->toDateString());
        $this->assertSame(['12345678000195', '45723174000110'], [$nota->cnpjEmissor, $nota->cnpjTomador]);
        $this->assertSame([530000, 520000], [$nota->valorBruto, $nota->valorLiquido]);
        $this->assertSame(['IRRF' => 10000], $nota->retencoes);
    }

    public function test_recusa_xml_com_entidade_externa_sem_ler_o_arquivo(): void
    {
        try {
            app(LeitorXmlNfse::class)->ler($this->fixture('xxe.xml'));
            $this->fail('Leu XML com ENTITY');
        } catch (XmlNaoLido $e) {
            $this->assertStringContainsString('não permitidas', $e->getMessage());
        }
    }

    public function test_layout_desconhecido_xml_quebrado_e_arquivo_enorme_viram_mensagem(): void
    {
        $leitor = app(LeitorXmlNfse::class);

        foreach ([
            [$this->fixture('desconhecido.xml'), 'reconhecer o layout'],
            ['isto não é xml <<<', 'não é um XML válido'],
            [str_repeat('a', LeitorXmlNfse::TAMANHO_MAXIMO + 1), 'grande demais'],
        ] as [$conteudo, $trecho]) {
            try {
                $leitor->ler($conteudo);
                $this->fail('Leu o que não devia');
            } catch (XmlNaoLido $e) {
                $this->assertStringContainsString($trecho, $e->getMessage());
            }
        }
    }
}
