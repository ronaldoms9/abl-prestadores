<?php

namespace Tests\Feature\Uau;

use App\Enums\StatusIntegracao;
use App\Integrations\Uau\Data\PessoaUau;
use App\Integrations\Uau\Exceptions\UauConfiguracaoInvalida;
use App\Integrations\Uau\Exceptions\UauIndisponivel;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use App\Integrations\Uau\UauClient;
use App\Models\IntegracaoLog;
use App\Models\PessoaFisica;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UauClientTest extends TestCase
{
    use RefreshDatabase;

    private const BASE = 'https://uau.test/uauAPI/api/v1.0';

    private const SENHA = 'senha-secreta-1';

    private const INTEGRACAO = 'int-secreto-2';

    private const TOKEN = 'tok-secreto-3';

    protected function setUp(): void
    {
        parent::setUp();

        config(['uau' => array_merge(config('uau'), [
            'fake' => false,
            'base_url' => self::BASE,
            'login' => 'ablapi',
            'senha' => self::SENHA,
            'token_integracao' => self::INTEGRACAO,
            'timeout' => 5,
            'token_ttl' => 1800,
            'authorization_prefixo' => '',
            'exigir_https' => true,
        ])]);

        Http::preventStrayRequests();
    }

    /** "Pronto quando" 4: nenhuma linha gerada pelos testes guarda segredo. */
    protected function assertPostConditions(): void
    {
        $logs = IntegracaoLog::all()->toJson();

        foreach ([self::SENHA, self::INTEGRACAO, self::TOKEN, 'tok-novo-4', 'segredo-uau-web-123', 'joaoweb', '56789-0'] as $segredo) {
            $this->assertStringNotContainsString($segredo, $logs, "integracao_logs contém o segredo {$segredo}");
        }
    }

    public function test_autenticacao_manda_login_senha_e_so_o_header_de_integracao(): void
    {
        $this->fakeUau(['Empresa/ObterEmpresasAtivas' => Http::response($this->exemplo('empresas-ativas.json'))]);

        $this->cliente()->empresasAtivas();

        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/Autenticador/AutenticarUsuario'
            && $r->method() === 'POST'
            && $r['Login'] === 'ablapi' && $r['Senha'] === self::SENHA
            && $r->header('X-INTEGRATION-Authorization') === [self::INTEGRACAO]
            && ! $r->hasHeader('Authorization'));
    }

    public function test_demais_chamadas_mandam_os_dois_headers_e_token_sem_bearer(): void
    {
        $this->fakeUau(['Empresa/ObterEmpresasAtivas' => Http::response($this->exemplo('empresas-ativas.json'))]);

        $empresas = $this->cliente()->empresasAtivas();

        $this->assertCount(3, $empresas);
        Http::assertSent(fn (Request $r) => $r->url() === self::BASE.'/Empresa/ObterEmpresasAtivas'
            && $r->method() === 'POST'
            && $r->body() === ''
            && $r->header('Authorization') === [self::TOKEN]
            && $r->header('X-INTEGRATION-Authorization') === [self::INTEGRACAO]
            && $r->hasHeader('X-Correlation-ID'));
    }

    public function test_prefixo_do_authorization_configuravel(): void
    {
        config(['uau.authorization_prefixo' => 'Bearer ']);
        $this->fakeUau(['Empresa/ObterEmpresasAtivas' => Http::response([])]);

        $this->cliente()->empresasAtivas();

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'ObterEmpresasAtivas')
            && $r->header('Authorization') === ['Bearer '.self::TOKEN]);
    }

    public function test_token_em_cache_autentica_uma_vez_so(): void
    {
        $this->fakeUau([
            'Empresa/ObterEmpresasAtivas' => Http::response([]),
            'Obras/ObterObrasAtivas' => Http::response([]),
        ]);

        $this->cliente()->empresasAtivas();
        $this->cliente()->obrasAtivas();

        $this->assertSame(1, $this->quantasVezes('Autenticador/AutenticarUsuario'));
        $this->assertSame(3, IntegracaoLog::count());
    }

    public function test_token_como_objeto_tambem_e_aceito(): void
    {
        Http::fake([
            self::BASE.'/Autenticador/AutenticarUsuario' => Http::response(['Token' => self::TOKEN]),
            self::BASE.'/Empresa/ObterEmpresasAtivas' => Http::response([]),
        ]);

        $this->cliente()->empresasAtivas();

        Http::assertSent(fn (Request $r) => $r->header('Authorization') === [self::TOKEN]);
    }

    public function test_401_renova_o_token_e_repete_uma_vez(): void
    {
        Http::fake([
            self::BASE.'/Autenticador/AutenticarUsuario' => Http::sequence()
                ->push('"'.self::TOKEN.'"')
                ->push('"tok-novo-4"'),
            self::BASE.'/Empresa/ObterEmpresasAtivas' => Http::sequence()
                ->push(['Mensagem' => 'Token expirado.'], 401)
                ->push($this->exemplo('empresas-ativas.json')),
        ]);

        $this->assertCount(3, $this->cliente()->empresasAtivas());

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'ObterEmpresasAtivas') && $r->header('Authorization') === ['tok-novo-4']);
        $this->assertSame(
            [['autenticar', 'sucesso'], ['empresas-ativas', 'erro'], ['autenticar', 'sucesso'], ['empresas-ativas', 'sucesso']],
            IntegracaoLog::orderBy('id')->get()->map(fn ($l) => [$l->operacao, $l->status->value])->all(),
        );
    }

    public function test_401_duas_vezes_e_rejeitado(): void
    {
        $this->fakeUau(['Empresa/ObterEmpresasAtivas' => Http::response(['Mensagem' => 'Não autorizado.'], 401)]);

        $erro = $this->capturar(fn () => $this->cliente()->empresasAtivas());

        $this->assertInstanceOf(UauRejeitou::class, $erro);
        $this->assertSame(401, $erro->httpStatus);
        $this->assertStringContainsString('mesmo depois de uma nova autenticação', $erro->getMessage());
    }

    public function test_credencial_errada_na_autenticacao_e_rejeitada(): void
    {
        Http::fake([self::BASE.'/Autenticador/AutenticarUsuario' => Http::response(['Mensagem' => 'Login do usuário ou a senha estão incorretos.'], 400)]);

        $erro = $this->capturar(fn () => $this->cliente()->empresasAtivas());

        $this->assertInstanceOf(UauRejeitou::class, $erro);
        $this->assertSame('Login do usuário ou a senha estão incorretos.', $erro->getMessage());
        $this->assertSame(['Login' => 'ablapi', 'Senha' => '[oculto]'], IntegracaoLog::sole()->payload);
    }

    public function test_400_e_rejeitado_com_a_mensagem_e_o_detalhe_no_log(): void
    {
        $this->fakeUau(['Obras/ObterObrasAtivas' => Http::response([
            'Mensagem' => 'Parâmetro inválido.', 'Descricao' => null, 'Detalhe' => 'at UAU.Obras.Consultar()',
        ], 400)]);

        $erro = $this->capturar(fn () => $this->cliente()->obrasAtivas());

        $this->assertInstanceOf(UauRejeitou::class, $erro);
        $this->assertSame('Parâmetro inválido.', $erro->getMessage());
        $this->assertSame(400, $erro->httpStatus);

        $log = IntegracaoLog::find($erro->logId);
        $this->assertSame(StatusIntegracao::Erro, $log->status);
        $this->assertSame("Parâmetro inválido.\nat UAU.Obras.Consultar()", $log->erro);
    }

    public static function statusDeIndisponibilidade(): array
    {
        return ['500' => [500], '503' => [503], '429' => [429]];
    }

    #[DataProvider('statusDeIndisponibilidade')]
    public function test_5xx_e_429_sao_indisponibilidade(int $status): void
    {
        $this->fakeUau(['ConfigGerais/RetornarVersaoWS' => Http::response(['Mensagem' => 'Erro interno.'], $status)]);

        $erro = $this->capturar(fn () => $this->cliente()->versao());

        $this->assertInstanceOf(UauIndisponivel::class, $erro);
        $this->assertSame($status, $erro->httpStatus);
        $this->assertSame(1, $this->quantasVezes('RetornarVersaoWS'), 'sem retentativa no cliente');
    }

    public function test_falha_de_conexao_e_indisponibilidade(): void
    {
        $this->fakeUau(['ConfigGerais/RetornarVersaoWS' => Http::failedConnection()]);

        $erro = $this->capturar(fn () => $this->cliente()->versao());

        $this->assertInstanceOf(UauIndisponivel::class, $erro);
        $this->assertNull($erro->httpStatus);
        $log = IntegracaoLog::find($erro->logId);
        $this->assertSame('versao', $log->operacao);
        $this->assertNull($log->http_status);
        $this->assertStringContainsString('Sem conexão com o UAU', $log->erro);
    }

    public function test_autenticacao_fora_do_ar_e_indisponibilidade(): void
    {
        Http::fake([self::BASE.'/Autenticador/AutenticarUsuario' => Http::response(['Mensagem' => 'Erro ao autenticar.'], 500)]);

        $this->assertInstanceOf(UauIndisponivel::class, $this->capturar(fn () => $this->cliente()->versao()));
        $this->assertSame(0, $this->quantasVezes('RetornarVersaoWS'));
    }

    public function test_pessoa_consulta_ativas_e_inativas_pelo_documento_so_com_digitos(): void
    {
        $this->fakeUau(['Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus' => Http::response($this->exemplo('pessoas/03174682940.json'))]);

        $pessoa = $this->cliente()->pessoaPorDocumento('031.746.829-40');

        $this->assertInstanceOf(PessoaUau::class, $pessoa);
        $this->assertSame('03174682940', $pessoa->documento);
        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), 'ConsultarDadosPessoaPorCpfCnpjEStatus')
            && $r->data() === ['cpf_cnpj' => '03174682940', 'status' => 2]);

        $resposta = IntegracaoLog::where('operacao', 'pessoa-por-documento')->sole()->resposta;
        $this->assertSame('[oculto]', $resposta['senhaUauWeb']);
        $this->assertSame('[oculto]', $resposta['loguinUauWeb']);
        $this->assertSame('[oculto]', $resposta['dadosBancarios']);
    }

    public function test_pessoa_nao_encontrada_e_null_e_nao_conta_como_erro(): void
    {
        $this->fakeUau(['Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus' => Http::sequence()
            ->push('', 200)
            ->push(['Mensagem' => 'Pessoa não encontrada.'], 400)
            ->push(['Mensagem' => 'Nenhum registro para o CPF/CNPJ informado.'], 400)]);

        $this->assertNull($this->cliente()->pessoaPorDocumento('52998224725'));
        $this->assertNull($this->cliente()->pessoaPorDocumento('52998224725'));
        $this->assertNull($this->cliente()->pessoaPorDocumento('52998224725'));
        $this->assertSame(0, IntegracaoLog::where('status', StatusIntegracao::Erro)->count());
    }

    public function test_outro_400_na_consulta_de_pessoa_e_rejeitado(): void
    {
        $this->fakeUau(['Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus' => Http::response(
            ['Mensagem' => 'Verifique se a condição para realizar a consulta está correta.'], 400)]);

        $this->assertInstanceOf(UauRejeitou::class, $this->capturar(fn () => $this->cliente()->pessoaPorDocumento('52998224725')));
    }

    public function test_resposta_fora_do_formato_e_rejeitada_e_nunca_vira_lista_vazia(): void
    {
        $this->fakeUau(['Empresa/ObterEmpresasAtivas' => Http::sequence()
            ->push('manutenção programada', 200)
            ->push([['Desc_emp' => 'Sem código']], 200)]);

        $semLista = $this->capturar(fn () => $this->cliente()->empresasAtivas());
        $semCodigo = $this->capturar(fn () => $this->cliente()->empresasAtivas());

        $this->assertInstanceOf(UauRejeitou::class, $semLista);
        $this->assertStringContainsString('era esperada uma lista', $semLista->getMessage());
        $this->assertInstanceOf(UauRejeitou::class, $semCodigo);
        $this->assertStringContainsString('Codigo_emp', $semCodigo->getMessage());
        $this->assertSame(['bruto' => 'manutenção programada'], IntegracaoLog::find($semLista->logId)->resposta);
    }

    public function test_dataset_do_dotnet_tambem_e_lido(): void
    {
        $this->fakeUau(['Obras/ObterObrasAtivas' => Http::response([['Obras' => [
            ['Cod_obr' => 'System.String', 'Empresa_obr' => 'System.Int32'],
            ['Cod_obr' => 'OB01', 'Empresa_obr' => 1, 'Descr_obr' => 'Residencial'],
        ]]])]);

        $obras = $this->cliente()->obrasAtivas();

        $this->assertCount(1, $obras);
        $this->assertSame('OB01', $obras[0]->codigo);
    }

    public function test_versao_do_uau(): void
    {
        $this->fakeUau(['ConfigGerais/RetornarVersaoWS' => Http::response('"1.0.4.12"')]);

        $this->assertSame('1.0.4.12', $this->cliente()->versao());
    }

    public function test_contexto_liga_a_chamada_a_entidade_sem_mudar_o_cliente_original(): void
    {
        $pessoa = PessoaFisica::factory()->create(['cpf' => '52998224725']);
        $this->fakeUau(['Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus' => Http::response($this->exemplo('pessoas/52998224725.json'))]);
        $cliente = $this->cliente();

        $cliente->comContexto(entidade: $pessoa, chaveIdempotencia: 'pessoa:52998224725', tentativa: 2)->pessoaPorDocumento($pessoa->cpf);
        $cliente->pessoaPorDocumento($pessoa->cpf);

        [$comContexto, $semContexto] = IntegracaoLog::where('operacao', 'pessoa-por-documento')->orderBy('id')->get();
        $this->assertSame(['pessoa-fisica', $pessoa->id, 'pessoa:52998224725', 2], [$comContexto->entidade_type, $comContexto->entidade_id, $comContexto->chave_idempotencia, $comContexto->tentativas]);
        $this->assertSame([null, null, 1], [$semContexto->entidade_type, $semContexto->chave_idempotencia, $semContexto->tentativas]);
        $this->assertNull(IntegracaoLog::where('operacao', 'autenticar')->sole()->entidade_type, 'a autenticação não é sobre a pessoa');
    }

    public function test_log_guarda_a_correlacao_enviada_e_a_duracao(): void
    {
        $this->fakeUau(['Empresa/ObterEmpresasAtivas' => Http::response([])]);

        $this->cliente()->empresasAtivas();

        $log = IntegracaoLog::where('operacao', 'empresas-ativas')->sole();
        Http::assertSent(fn (Request $r) => $r->header('X-Correlation-ID') === [$log->correlacao]);
        $this->assertSame('Empresa/ObterEmpresasAtivas', $log->rota);
        $this->assertSame(200, $log->http_status);
        $this->assertNotNull($log->duracao_ms);
        $this->assertSame(['token' => '[oculto]'], IntegracaoLog::where('operacao', 'autenticar')->sole()->resposta);
    }

    public function test_configuracao_invalida_falha_antes_de_qualquer_chamada(): void
    {
        $casos = [
            'sem token de integração' => [['token_integracao' => ''], 'UAU_TOKEN_INTEGRACAO'],
            'sem login e senha' => [['login' => null, 'senha' => ''], 'UAU_LOGIN, UAU_SENHA'],
            'login com 9 caracteres' => [['login' => 'abl-prime'], 'autentica como cliente'],
            'URL de exemplo' => [['base_url' => 'https://<host>:<porta>/uauAPI/api/v1.0'], 'não é uma URL válida'],
        ];

        foreach ($casos as $caso => [$config, $trecho]) {
            $erro = $this->capturar(fn () => new UauClient(app(\App\Integrations\Uau\RegistroIntegracao::class), array_merge(config('uau'), $config)));
            $this->assertInstanceOf(UauConfiguracaoInvalida::class, $erro, $caso);
            $this->assertStringContainsString($trecho, $erro->getMessage(), $caso);
        }

        Http::assertNothingSent();
    }

    public function test_http_so_e_aceito_no_ambiente_local_ou_com_a_trava_desligada(): void
    {
        $http = array_merge(config('uau'), ['base_url' => 'http://187.32.118.169:9556/uauAPI/api/v1.0']);
        $registro = app(\App\Integrations\Uau\RegistroIntegracao::class);

        $this->assertInstanceOf(UauClient::class, new UauClient($registro, $http), 'testing aceita http');

        $this->app['env'] = 'production';
        $erro = $this->capturar(fn () => new UauClient($registro, $http));
        $this->assertInstanceOf(UauConfiguracaoInvalida::class, $erro);
        $this->assertStringContainsString('http://', $erro->getMessage());

        $this->assertInstanceOf(UauClient::class, new UauClient($registro, array_merge($http, ['exigir_https' => false])));
    }

    private function cliente(): UauClient
    {
        return app(UauClient::class);
    }

    /** Autenticação OK + as rotas dadas (relativas à base). */
    private function fakeUau(array $rotas): void
    {
        $fakes = [self::BASE.'/Autenticador/AutenticarUsuario' => Http::response('"'.self::TOKEN.'"')];

        foreach ($rotas as $rota => $resposta) {
            $fakes[self::BASE.'/'.$rota] = $resposta;
        }

        Http::fake($fakes);
    }

    private function quantasVezes(string $rota): int
    {
        return Http::recorded(fn (Request $r) => str_ends_with($r->url(), $rota))->count();
    }

    private function capturar(callable $chamada): ?\Throwable
    {
        try {
            $chamada();
        } catch (\Throwable $e) {
            return $e;
        }

        return null;
    }

    private function exemplo(string $arquivo): mixed
    {
        return json_decode(file_get_contents(resource_path('fixtures/uau/'.$arquivo)), true, flags: JSON_THROW_ON_ERROR);
    }
}
