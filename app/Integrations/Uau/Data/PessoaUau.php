<?php

namespace App\Integrations\Uau\Data;

use App\Support\Documento;
use Carbon\CarbonImmutable;
use InvalidArgumentException;

/**
 * Pessoa de Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus.
 *
 * Só os campos que o sistema usa. Senha e login do portal do UAU
 * (senhaUauWeb, loguinUauWeb) e dados bancários nunca entram aqui.
 */
final readonly class PessoaUau
{
    use LeCamposUau;

    /**
     * @param  list<EnderecoUau>  $enderecos
     * @param  list<TelefoneUau>  $telefones
     */
    public function __construct(
        public int $codigo,
        public TipoPessoaUau $tipo,
        public string $documento,
        public string $nome,
        public bool $ativa,
        public ?string $nomeFantasia = null,
        public ?CarbonImmutable $nascimento = null,
        public ?string $email = null,
        public ?string $inscricaoMunicipal = null,
        public ?string $inscricaoEstadual = null,
        public ?CarbonImmutable $alteradaEm = null,
        public array $enderecos = [],
        public array $telefones = [],
    ) {
    }

    public static function fromArray(array $uau): self
    {
        $tipo = TipoPessoaUau::tryFrom((int) self::obrigatorio(self::inteiro($uau, 'tipo'), 'tipo'))
            ?? throw new InvalidArgumentException('tipo de pessoa desconhecido na resposta do UAU');

        $documento = Documento::digitos(self::obrigatorio(self::texto($uau, 'cpfCnpj'), 'cpfCnpj'))
            ?? throw new InvalidArgumentException('campo cpfCnpj sem dígitos na resposta do UAU');

        return new self(
            codigo: self::obrigatorio(self::inteiro($uau, 'codigoPessoa'), 'codigoPessoa'),
            tipo: $tipo,
            // O UAU devolve texto, mas um documento que perdeu o zero à esquerda é recomposto pelo tipo.
            documento: str_pad($documento, $tipo->digitos(), '0', STR_PAD_LEFT),
            nome: self::texto($uau, 'nomePessoa') ?? '',
            // pessoaAtiva: 0 ativa, 1 inativa.
            ativa: self::inteiro($uau, 'pessoaAtiva') !== 1,
            nomeFantasia: self::texto($uau, 'nomeFantasia'),
            nascimento: self::data($uau, 'dataDeNascimento'),
            email: self::texto($uau, 'email'),
            inscricaoMunicipal: self::texto($uau, 'inscricaoMunicipal'),
            inscricaoEstadual: self::texto($uau, 'inscricaoEstadual'),
            alteradaEm: self::data($uau, 'dataDeAlteracao'),
            enderecos: array_map(EnderecoUau::fromArray(...), self::lista($uau, 'enderecos')),
            telefones: array_map(TelefoneUau::fromArray(...), self::lista($uau, 'telefones')),
        );
    }

    /** O endereço principal (tipo 0) ou, sem ele, o primeiro da lista. */
    public function enderecoPrincipal(): ?EnderecoUau
    {
        foreach ($this->enderecos as $endereco) {
            if ($endereco->tipo === EnderecoUau::PRINCIPAL) {
                return $endereco;
            }
        }

        return $this->enderecos[0] ?? null;
    }

    /** O celular ou, sem ele, o primeiro telefone com número. */
    public function telefonePrincipal(): ?string
    {
        foreach ($this->telefones as $telefone) {
            if ($telefone->tipo === TelefoneUau::CELULAR && $telefone->completo() !== null) {
                return $telefone->completo();
            }
        }

        foreach ($this->telefones as $telefone) {
            if ($telefone->completo() !== null) {
                return $telefone->completo();
            }
        }

        return null;
    }
}
