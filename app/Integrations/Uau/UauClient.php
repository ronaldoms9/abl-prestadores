<?php

namespace App\Integrations\Uau;

use App\Enums\StatusIntegracao;
use App\Integrations\Uau\Data\EmpresaUau;
use App\Integrations\Uau\Data\ObraUau;
use App\Integrations\Uau\Data\PessoaUau;
use App\Integrations\Uau\Exceptions\UauConfiguracaoInvalida;
use App\Integrations\Uau\Exceptions\UauException;
use App\Integrations\Uau\Exceptions\UauIndisponivel;
use App\Integrations\Uau\Exceptions\UauRejeitou;
use App\Models\IntegracaoLog;
use App\Support\Documento;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Cliente HTTP da UAU API v1.0 (Globaltec).
 *
 * - Todas as rotas são POST relativas à UAU_BASE_URL, com o header
 *   X-INTEGRATION-Authorization. As autenticadas levam também Authorization
 *   com o token da sessão, sem "Bearer" (como na integração do SGTR).
 * - O token fica em cache; um 401 renova o token e repete a chamada uma vez.
 * - Sem retentativa aqui: quem tenta de novo é o job (fatia 5).
 * - Cada requisição, inclusive a autenticação, vira uma linha em integracao_logs.
 */
class UauClient implements UauGateway
{
    private const ROTA_AUTENTICAR = 'Autenticador/AutenticarUsuario';

    /** "status" da consulta de pessoa: 0 ativas, 1 inativas, 2 ambas. */
    private const PESSOAS_ATIVAS_E_INATIVAS = 2;

    /** Acima disso o UAU autentica como cliente, não como usuário (Swagger). */
    private const LOGIN_MAXIMO = 8;

    private ContextoChamada $contexto;

    /** @param  array<string, mixed>  $config  config('uau') */
    public function __construct(
        private readonly RegistroIntegracao $registro,
        private readonly array $config,
    ) {
        $this->validarConfiguracao();
        $this->contexto = new ContextoChamada();
    }

    public function comContexto(?Model $entidade = null, ?string $chaveIdempotencia = null, int $tentativa = 1): static
    {
        $copia = clone $this;
        $copia->contexto = new ContextoChamada($entidade, $chaveIdempotencia, $tentativa);

        return $copia;
    }

    public function empresasAtivas(): array
    {
        return $this->chamar(OperacaoUau::EmpresasAtivas, 'Empresa/ObterEmpresasAtivas', null,
            fn ($json) => array_map(EmpresaUau::fromArray(...), $this->lista($json)));
    }

    public function obrasAtivas(): array
    {
        return $this->chamar(OperacaoUau::ObrasAtivas, 'Obras/ObterObrasAtivas', null,
            fn ($json) => array_map(ObraUau::fromArray(...), $this->lista($json)));
    }

    public function pessoaPorDocumento(string $documento): ?PessoaUau
    {
        $digitos = Documento::digitos($documento)
            ?? throw new InvalidArgumentException('Informe o CPF ou o CNPJ da pessoa.');

        return $this->chamar(
            OperacaoUau::PessoaPorDocumento,
            'Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus',
            ['cpf_cnpj' => $digitos, 'status' => self::PESSOAS_ATIVAS_E_INATIVAS],
            fn ($json) => ($linha = RespostaUau::objeto($json)) === null ? null : PessoaUau::fromArray($linha),
            naoEncontrada: fn (Response $resposta, $json) => $resposta->status() === 400
                && preg_match($this->config['padrao_nao_encontrada'], RespostaUau::mensagem($json) ?? '') === 1,
        );
    }

    public function versao(): string
    {
        return $this->chamar(OperacaoUau::Versao, 'ConfigGerais/RetornarVersaoWS', null, function ($json, Response $resposta) {
            $versao = is_scalar($json) ? (string) $json : trim($resposta->body(), " \t\n\r\"");

            return trim($versao) !== '' ? trim($versao) : throw new InvalidArgumentException('a versão veio vazia');
        });
    }

    /**
     * Uma chamada autenticada: envia, renova o token num 401 e interpreta a resposta.
     *
     * @param  Closure(mixed $json, Response $resposta): mixed  $interpretar  Lança InvalidArgumentException se o formato não servir.
     * @param  (Closure(Response, mixed): bool)|null  $naoEncontrada  Falha HTTP que é, na verdade, "não encontrado" (devolve null).
     */
    private function chamar(OperacaoUau $operacao, string $rota, ?array $corpo, Closure $interpretar, ?Closure $naoEncontrada = null): mixed
    {
        $tentativa = $this->enviar($operacao, $rota, $corpo, $this->token());

        if ($tentativa->resposta->status() === 401) {
            $this->registrar($tentativa, StatusIntegracao::Erro, 'Token recusado (401); autenticando de novo.');
            Cache::forget($this->chaveDoToken());
            $tentativa = $this->enviar($operacao, $rota, $corpo, $this->token());
        }

        return $this->concluir($tentativa, $interpretar, $naoEncontrada);
    }

    private function concluir(Tentativa $tentativa, Closure $interpretar, ?Closure $naoEncontrada = null): mixed
    {
        $resposta = $tentativa->resposta;
        $json = $resposta->json();

        if (! $resposta->successful()) {
            if ($naoEncontrada !== null && $naoEncontrada($resposta, $json)) {
                $this->registrar($tentativa, StatusIntegracao::Sucesso);

                return null;
            }

            $mensagem = RespostaUau::mensagem($json) ?? "O UAU respondeu HTTP {$resposta->status()}.";
            $log = $this->registrar($tentativa, StatusIntegracao::Erro, trim($mensagem."\n".RespostaUau::detalhe($json)));

            throw $this->excecaoPara($tentativa->operacao, $resposta->status(), $mensagem, $log);
        }

        try {
            $resultado = $interpretar($json, $resposta);
        } catch (InvalidArgumentException $e) {
            $log = $this->registrar($tentativa, StatusIntegracao::Erro, 'Resposta fora do formato esperado: '.$e->getMessage());

            throw new UauRejeitou('O UAU respondeu fora do formato esperado: '.$e->getMessage().'.', $resposta->status(), $log?->id, $e);
        }

        $this->registrar($tentativa, StatusIntegracao::Sucesso);

        return $resultado;
    }

    private function enviar(OperacaoUau $operacao, string $rota, ?array $corpo, ?string $token): Tentativa
    {
        $correlacao = (string) Str::uuid();
        $inicio = CarbonImmutable::now();
        $relogio = hrtime(true);

        $requisicao = Http::baseUrl(rtrim($this->config['base_url'], '/'))
            ->timeout((int) $this->config['timeout'])
            ->acceptJson()
            ->asJson()
            ->withHeaders(array_filter([
                'X-INTEGRATION-Authorization' => trim($this->config['token_integracao']),
                'X-Correlation-ID' => $correlacao,
                'Authorization' => $token === null ? null : $this->config['authorization_prefixo'].$token,
            ], fn ($valor) => $valor !== null));

        try {
            $resposta = $corpo === null
                ? $requisicao->withBody('', 'application/json')->post($rota)
                : $requisicao->post($rota, $corpo);
        } catch (ConnectionException $e) {
            $tentativa = new Tentativa($operacao, $rota, $corpo, null, $inicio, $this->milissegundos($relogio), $correlacao);
            $log = $this->registrar($tentativa, StatusIntegracao::Erro, 'Sem conexão com o UAU: '.$e->getMessage());

            throw new UauIndisponivel('Sem conexão com o UAU ou tempo de resposta esgotado.', null, $log?->id, $e);
        }

        return new Tentativa($operacao, $rota, $corpo, $resposta, $inicio, $this->milissegundos($relogio), $correlacao);
    }

    private function token(): string
    {
        return Cache::remember($this->chaveDoToken(), (int) $this->config['token_ttl'], fn () => $this->autenticar());
    }

    private function autenticar(): string
    {
        $tentativa = $this->enviar(OperacaoUau::Autenticar, self::ROTA_AUTENTICAR, [
            'Login' => $this->config['login'],
            'Senha' => $this->config['senha'],
        ], token: null);

        return $this->concluir($tentativa, fn ($json, Response $resposta) => $this->extrairToken($json, $resposta->body()));
    }

    /** O token vem como string JSON; algumas versões devolvem um objeto (token/Token/access_token). */
    private function extrairToken(mixed $json, string $corpo): string
    {
        $token = match (true) {
            is_string($json) => $json,
            is_array($json) => RespostaUau::valor($json, 'token') ?? RespostaUau::valor($json, 'access_token'),
            default => trim($corpo, " \t\n\r\""),
        };

        if (! is_string($token) || trim($token) === '') {
            throw new InvalidArgumentException('o UAU não devolveu o token de autenticação');
        }

        return trim($token);
    }

    /** O token depende da URL e do login: trocar um deles não reaproveita o token antigo. */
    private function chaveDoToken(): string
    {
        return 'uau.token.'.md5($this->config['base_url'].'|'.$this->config['login']);
    }

    /** @return list<array<string, mixed>> */
    private function lista(mixed $json): array
    {
        // Uma resposta que não é lista não pode virar "nenhum registro ativo":
        // a importação inativaria tudo.
        if (! is_array($json)) {
            throw new InvalidArgumentException('era esperada uma lista');
        }

        return RespostaUau::linhas($json);
    }

    private function registrar(Tentativa $tentativa, StatusIntegracao $status, ?string $erro = null): ?IntegracaoLog
    {
        $autenticacao = $tentativa->operacao === OperacaoUau::Autenticar;
        $resposta = $tentativa->resposta;

        return $this->registro->registrar(
            operacao: $tentativa->operacao,
            rota: $tentativa->rota,
            // A autenticação não é "sobre" a entidade do job; só a tentativa vale.
            contexto: $autenticacao ? new ContextoChamada(tentativa: $this->contexto->tentativa) : $this->contexto,
            payload: $tentativa->corpo,
            // O corpo de uma autenticação bem-sucedida é o token: nunca vai para o log.
            resposta: $autenticacao && $resposta?->successful() ? ['token' => Sanitizador::OCULTO] : $resposta?->body(),
            httpStatus: $resposta?->status(),
            status: $status,
            erro: $erro === '' ? null : $erro,
            executadoEm: $tentativa->inicio,
            duracaoMs: $tentativa->duracaoMs,
            correlacao: $tentativa->correlacao,
        );
    }

    private function excecaoPara(OperacaoUau $operacao, int $status, string $mensagem, ?IntegracaoLog $log): UauException
    {
        if ($status >= 500 || $status === 429) {
            return new UauIndisponivel($mensagem, $status, $log?->id);
        }

        if ($status === 401 && $operacao !== OperacaoUau::Autenticar) {
            $mensagem = 'O UAU recusou o token mesmo depois de uma nova autenticação: '.$mensagem;
        }

        return new UauRejeitou($mensagem, $status, $log?->id);
    }

    private function milissegundos(int $inicio): int
    {
        return (int) round((hrtime(true) - $inicio) / 1_000_000);
    }

    private function validarConfiguracao(): void
    {
        $faltando = array_keys(array_filter([
            'UAU_BASE_URL' => $this->config['base_url'] ?? null,
            'UAU_LOGIN' => $this->config['login'] ?? null,
            'UAU_SENHA' => $this->config['senha'] ?? null,
            'UAU_TOKEN_INTEGRACAO' => $this->config['token_integracao'] ?? null,
        ], fn ($valor) => blank($valor)));

        if ($faltando !== []) {
            throw new UauConfiguracaoInvalida('Configuração do UAU incompleta no .env: '.implode(', ', $faltando).'.');
        }

        $url = (string) $this->config['base_url'];

        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new UauConfiguracaoInvalida("UAU_BASE_URL não é uma URL válida: {$url}");
        }

        if ($this->config['exigir_https'] && ! str_starts_with(mb_strtolower($url), 'https://') && ! app()->environment('local', 'testing')) {
            throw new UauConfiguracaoInvalida('UAU_BASE_URL usa http://: login, senha e tokens trafegariam abertos. '
                .'Publique a API em HTTPS ou, só em ambiente controlado, defina UAU_EXIGIR_HTTPS=false.');
        }

        $tamanho = mb_strlen((string) $this->config['login']);

        if ($tamanho > self::LOGIN_MAXIMO) {
            throw new UauConfiguracaoInvalida("UAU_LOGIN tem {$tamanho} caracteres. Com mais de ".self::LOGIN_MAXIMO
                .', o UAU autentica como cliente, não como usuário: use um usuário técnico com até '.self::LOGIN_MAXIMO.'.');
        }
    }
}
