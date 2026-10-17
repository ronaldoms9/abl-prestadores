<?php

namespace Tests\Unit;

use App\Casts\AsEndereco;
use App\Support\Endereco;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class EnderecoTest extends TestCase
{
    public function test_ida_e_volta_pelo_cast(): void
    {
        $cast = new AsEndereco();
        $model = new class extends Model {};

        $dados = [
            'logradouro' => 'Rua das Flores',
            'numero' => '123',
            'complemento' => 'Apto 45',
            'bairro' => 'Centro',
            'cidade' => 'Goiânia',
            'uf' => 'go',
            'cep' => '74.000-000',
        ];

        $json = $cast->set($model, 'endereco', $dados, []);
        $this->assertIsString($json);
        $this->assertStringContainsString('"uf":"GO"', $json);
        $this->assertStringContainsString('"cep":"74000000"', $json);

        /** @var Endereco $endereco */
        $endereco = $cast->get($model, 'endereco', $json, []);
        $this->assertInstanceOf(Endereco::class, $endereco);
        $this->assertSame('Rua das Flores', $endereco->logradouro);
        $this->assertSame('123', $endereco->numero);
        $this->assertSame('Apto 45', $endereco->complemento);
        $this->assertSame('Centro', $endereco->bairro);
        $this->assertSame('Goiânia', $endereco->cidade);
        $this->assertSame('GO', $endereco->uf);
        $this->assertSame('74000000', $endereco->cep);

        // Passando a própria instância de Endereco
        $jsonDeObjeto = $cast->set($model, 'endereco', $endereco, []);
        $this->assertSame($json, $jsonDeObjeto);
    }

    public function test_chave_desconhecida_descartada(): void
    {
        $endereco = Endereco::fromArray([
            'logradouro' => 'Av. Brasil',
            'campo_estranho' => 'valor_invalido',
            'outro' => 123,
        ]);

        $array = $endereco->toArray();
        $this->assertArrayNotHasKey('campo_estranho', $array);
        $this->assertArrayNotHasKey('outro', $array);
        $this->assertCount(7, $array);
    }

    public function test_endereco_vazio_gravado_como_null(): void
    {
        $cast = new AsEndereco();
        $model = new class extends Model {};

        $this->assertNull($cast->set($model, 'endereco', null, []));
        $this->assertNull($cast->set($model, 'endereco', [], []));
        $this->assertNull($cast->set($model, 'endereco', ['logradouro' => '', 'cep' => '  '], []));
        $this->assertNull($cast->set($model, 'endereco', new Endereco(), []));

        $this->assertNull($cast->get($model, 'endereco', null, []));
        $this->assertNull($cast->get($model, 'endereco', '', []));
        $this->assertNull($cast->get($model, 'endereco', '{}', []));
    }

    public function test_json_em_texto_e_aceito(): void
    {
        $cast = new AsEndereco();
        $model = new class extends Model {};

        $json = $cast->set($model, 'endereco', '{"cidade":"Goiânia","uf":"go","outra":"x"}', []);

        $this->assertSame('{"logradouro":null,"numero":null,"complemento":null,"bairro":null,"cidade":"Goiânia","uf":"GO","cep":null}', $json);
    }

    public function test_valor_invalido_falha_em_vez_de_apagar(): void
    {
        $cast = new AsEndereco();
        $model = new class extends Model {};

        foreach (['Rua X, 10', 123, new \stdClass()] as $invalido) {
            try {
                $cast->set($model, 'endereco', $invalido, []);
                $this->fail('Deveria recusar '.get_debug_type($invalido));
            } catch (InvalidArgumentException $e) {
                $this->assertStringContainsString('endereco', $e->getMessage());
            }
        }
    }

    public function test_formatado_sem_partes_vazias(): void
    {
        $completo = new Endereco(
            logradouro: 'Rua X',
            numero: '10',
            complemento: 'Bloco B',
            bairro: 'Centro',
            cidade: 'Goiânia',
            uf: 'GO',
            cep: '74000000'
        );

        $this->assertSame('Rua X, 10 – Bloco B – Centro – Goiânia/GO – 74000-000', $completo->formatado());

        $semNumeroEComplemento = new Endereco(
            logradouro: 'Rua X',
            bairro: 'Centro',
            cidade: 'Goiânia',
            uf: 'GO'
        );

        $this->assertSame('Rua X – Centro – Goiânia/GO', $semNumeroEComplemento->formatado());

        $soCidadeUf = new Endereco(
            cidade: 'Brasília',
            uf: 'DF'
        );

        $this->assertSame('Brasília/DF', $soCidadeUf->formatado());

        $vazio = new Endereco();
        $this->assertSame('', $vazio->formatado());
    }
}
