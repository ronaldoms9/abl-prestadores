<?php

namespace Tests\Unit\Contratos;

use App\Support\Contratos\ContratoVariaveis;
use App\Support\Contratos\RenderizadorContrato;
use App\Support\Contratos\SanitizadorHtml;
use Illuminate\Support\HtmlString;
use PHPUnit\Framework\TestCase;

class RenderizadorContratoTest extends TestCase
{
    private RenderizadorContrato $renderizador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderizador = new RenderizadorContrato;
    }

    public function test_cada_variavel_do_catalogo_e_substituida(): void
    {
        $exemplos = ContratoVariaveis::exemplos();
        $corpo = implode(' ', array_map(fn ($chave) => '{{'.$chave.'}}', ContratoVariaveis::chaves()));

        $resultado = $this->renderizador->renderizar($corpo, $exemplos);

        $this->assertTrue($resultado->completo());
        $this->assertStringNotContainsString('{{', $resultado->html);
        $this->assertStringContainsString('Maria da Silva', $resultado->html);
        $this->assertStringContainsString('cinco mil reais', $resultado->html);
    }

    public function test_aceita_espacos_dentro_das_chaves(): void
    {
        $resultado = $this->renderizador->renderizar('Olá, {{  colaborador.nome }}!', ['colaborador.nome' => 'Ana']);

        $this->assertSame('Olá, Ana!', $resultado->html);
    }

    public function test_valor_de_texto_e_escapado_mas_html_seguro_passa(): void
    {
        $resultado = $this->renderizador->renderizar('{{colaborador.nome}}|{{beneficios.lista}}', [
            'colaborador.nome' => '<script>alert(1)</script> & "aspas"',
            'beneficios.lista' => new HtmlString('<ul><li>VT</li></ul>'),
        ]);

        $this->assertStringNotContainsString('<script>', $resultado->html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;aspas&quot;', $resultado->html);
        $this->assertStringContainsString('<ul><li>VT</li></ul>', $resultado->html);
    }

    public function test_variavel_sem_valor_vira_marcador_e_e_listada(): void
    {
        $resultado = $this->renderizador->renderizar('{{colaborador.nome}} ganha {{remuneracao.valor}}', ['colaborador.nome' => 'Ana']);

        $this->assertFalse($resultado->completo());
        $this->assertSame(['remuneracao.valor'], $resultado->faltantes);
        $this->assertStringContainsString('<mark>[remuneracao.valor sem valor]</mark>', $resultado->html);
    }

    public function test_variavel_desconhecida_e_apontada(): void
    {
        $this->assertSame(['foo.bar', 'sem_ponto'], $this->renderizador->desconhecidas('{{colaborador.nome}} {{ foo.bar }} {{sem_ponto}} {{foo.bar}}'));
        $this->assertSame([], $this->renderizador->desconhecidas('Texto sem variáveis'));
    }

    public function test_variavel_com_formatacao_no_meio_e_desconhecida(): void
    {
        $achadas = $this->renderizador->desconhecidas('{{colaborador.<b>nome</b>}}');

        $this->assertCount(1, $achadas);
        $this->assertStringStartsWith('«', $achadas[0]);
    }

    public function test_conteudo_do_usuario_nunca_executa_codigo(): void
    {
        $corpo = '<p>@php echo 1; @endphp {{ phpinfo() }} {!! system("ls") !!} <?php echo 2; ?> {{colaborador.nome}}</p>';

        $resultado = $this->renderizador->renderizar($corpo, ['colaborador.nome' => 'Ana']);

        $this->assertStringContainsString('@php echo 1; @endphp', $resultado->html);
        $this->assertStringContainsString('{{ phpinfo() }}', $resultado->html);
        $this->assertStringContainsString('{!! system("ls") !!}', $resultado->html);
        $this->assertStringContainsString('Ana', $resultado->html);
    }

    public function test_valor_com_chaves_nao_e_reprocessado(): void
    {
        $resultado = $this->renderizador->renderizar('{{colaborador.nome}}', ['colaborador.nome' => '{{cargo.nome}}', 'cargo.nome' => 'Chefe']);

        $this->assertSame('{{cargo.nome}}', $resultado->html);
    }

    public function test_sanitizador_remove_script_eventos_e_enderecos_perigosos_e_mantem_formatacao(): void
    {
        $limpo = SanitizadorHtml::limpar(
            '<h3 style="text-align:center">Título</h3><p onclick="x()">Texto <b>forte</b> {{colaborador.nome}}</p>'
            .'<script>alert(1)</script><iframe src="http://x"></iframe><a href="javascript:alert(1)">link</a>'
            .'<img src="data:image/png;base64,AAAA" onerror="x()"><a href="data:text/html;base64,AAAA">d</a>'
            .'<table><tr><td>cel</td></tr></table><form><input name="a"></form>'
        );

        $this->assertStringContainsString('<h3 style="text-align:center">Título</h3>', $limpo);
        $this->assertStringContainsString('<b>forte</b>', $limpo);
        $this->assertStringContainsString('{{colaborador.nome}}', $limpo);
        $this->assertStringContainsString('<table>', $limpo);
        $this->assertStringContainsString('data:image/png;base64,AAAA', $limpo);
        foreach (['<script', 'alert(1)', 'onclick', 'onerror', '<iframe', 'javascript:', 'data:text/html', '<form', '<input'] as $proibido) {
            $this->assertStringNotContainsString($proibido, $limpo, $proibido);
        }
    }

    public function test_sanitizador_preserva_acentos_e_vazio(): void
    {
        $this->assertSame('<p>Cláusula primeira – dos serviços</p>', SanitizadorHtml::limpar('<p>Cláusula primeira – dos serviços</p>'));
        $this->assertSame('', SanitizadorHtml::limpar('   '));
    }
}
