<?php

namespace App\Integrations\Uau;

use App\Enums\StatusIntegracao;
use App\Integrations\Uau\Data\EmpresaUau;
use App\Integrations\Uau\Data\ObraUau;
use App\Integrations\Uau\Data\PessoaUau;
use App\Integrations\Uau\Exceptions\UauException;
use App\Support\Documento;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use RuntimeException;

/**
 * UAU simulado (UAU_FAKE=true): lê os dados de exemplo de resources/fixtures/uau.
 *
 * - Monta os DTOs com os mesmos fromArray() do cliente real.
 * - Grava integracao_logs como o real, com a rota "fake:...", para a tela de
 *   monitoramento ter o que mostrar em desenvolvimento e na homologação.
 * - Guarda as chamadas e simula falhas, para os testes das fatias seguintes.
 */
class FakeUauClient implements UauGateway
{
    private ContextoChamada $contexto;

    /** Chamadas e falhas programadas, compartilhadas com as cópias de comContexto(). */
    private object $estado;

    public function __construct(
        private readonly RegistroIntegracao $registro,
        private readonly string $diretorio,
    ) {
        $this->contexto = new ContextoChamada();
        $this->estado = new class
        {
            /** @var list<array{operacao: OperacaoUau, argumentos: array|null, contexto: ContextoChamada}> */
            public array $chamadas = [];

            /** @var array<string, list<UauException>> */
            public array $falhas = [];
        };
    }

    public function comContexto(?Model $entidade = null, ?string $chaveIdempotencia = null, int $tentativa = 1): static
    {
        $copia = clone $this;
        $copia->contexto = new ContextoChamada($entidade, $chaveIdempotencia, $tentativa);

        return $copia;
    }

    public function empresasAtivas(): array
    {
        return $this->executar(OperacaoUau::EmpresasAtivas, 'Empresa/ObterEmpresasAtivas', null, 'empresas-ativas.json',
            fn ($json) => array_map(EmpresaUau::fromArray(...), RespostaUau::linhas($json)));
    }

    public function obrasAtivas(): array
    {
        return $this->executar(OperacaoUau::ObrasAtivas, 'Obras/ObterObrasAtivas', null, 'obras-ativas.json',
            fn ($json) => array_map(ObraUau::fromArray(...), RespostaUau::linhas($json)));
    }

    public function pessoaPorDocumento(string $documento): ?PessoaUau
    {
        $digitos = Documento::digitos($documento)
            ?? throw new InvalidArgumentException('Informe o CPF ou o CNPJ da pessoa.');

        return $this->executar(
            OperacaoUau::PessoaPorDocumento,
            'Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus',
            ['cpf_cnpj' => $digitos, 'status' => 2],
            "pessoas/{$digitos}.json",
            fn ($json) => ($linha = RespostaUau::objeto($json)) === null ? null : PessoaUau::fromArray($linha),
            obrigatorio: false,
        );
    }

    public function versao(): string
    {
        return $this->executar(OperacaoUau::Versao, 'ConfigGerais/RetornarVersaoWS', null, 'versao.json', fn ($json) => (string) $json);
    }

    /**
     * Faz as próximas $vezes chamadas da operação falharem com $erro, para
     * testar a retentativa dos jobs. Depois disso, a operação volta ao normal.
     */
    public function falharEm(OperacaoUau $operacao, UauException $erro, int $vezes = 1): static
    {
        $this->estado->falhas[$operacao->value] = [
            ...($this->estado->falhas[$operacao->value] ?? []),
            ...array_fill(0, $vezes, $erro),
        ];

        return $this;
    }

    /** @return list<array{operacao: OperacaoUau, argumentos: array|null, contexto: ContextoChamada}> */
    public function chamadas(?OperacaoUau $operacao = null): array
    {
        return array_values(array_filter(
            $this->estado->chamadas,
            fn ($chamada) => $operacao === null || $chamada['operacao'] === $operacao,
        ));
    }

    /** @param  Closure(mixed $json): mixed  $interpretar */
    private function executar(OperacaoUau $operacao, string $rota, ?array $payload, string $arquivo, Closure $interpretar, bool $obrigatorio = true): mixed
    {
        $this->estado->chamadas[] = ['operacao' => $operacao, 'argumentos' => $payload, 'contexto' => $this->contexto];
        $inicio = CarbonImmutable::now();

        if (($this->estado->falhas[$operacao->value] ?? []) !== []) {
            $erro = array_shift($this->estado->falhas[$operacao->value]);
            $this->registrar($operacao, $rota, $payload, null, $erro->httpStatus, StatusIntegracao::Erro, $erro->getMessage(), $inicio);

            throw $erro;
        }

        $json = $this->ler($arquivo, $obrigatorio);
        $resultado = $interpretar($json);
        $this->registrar($operacao, $rota, $payload, $json, 200, StatusIntegracao::Sucesso, null, $inicio);

        return $resultado;
    }

    private function ler(string $arquivo, bool $obrigatorio): mixed
    {
        $caminho = rtrim($this->diretorio, '/').'/'.$arquivo;

        if (! is_file($caminho)) {
            return $obrigatorio
                ? throw new RuntimeException("Dado de exemplo do UAU não encontrado: {$caminho}")
                : null;
        }

        return json_decode(file_get_contents($caminho), true, flags: JSON_THROW_ON_ERROR);
    }

    private function registrar(OperacaoUau $operacao, string $rota, ?array $payload, mixed $resposta, ?int $httpStatus, StatusIntegracao $status, ?string $erro, CarbonImmutable $inicio): void
    {
        $this->registro->registrar(
            operacao: $operacao,
            rota: 'fake:'.$rota,
            contexto: $this->contexto,
            payload: $payload,
            resposta: $resposta,
            httpStatus: $httpStatus,
            status: $status,
            erro: $erro,
            executadoEm: $inicio,
            duracaoMs: 0,
        );
    }
}
