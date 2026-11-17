<?php

namespace App\Support\Contratos;

use App\Enums\MotivoAditivo;
use App\Models\Colaborador;
use App\Models\Contrato;
use App\Support\Dinheiro;
use App\Support\Documento;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

/**
 * Catálogo das variáveis que o modelo de contrato aceita, no formato
 * {{grupo.nome}}. É a lista que o editor mostra e a única que o salvamento do
 * modelo permite (variável desconhecida bloqueia). Cada variável sabe o valor
 * de exemplo mostrado na pré-visualização do modelo.
 *
 * Os valores reais vêm de valores(): chave => texto puro (o renderizador
 * escapa) ou HtmlString (já seguro, como a lista de benefícios). Chave sem
 * dado no cadastro fica de fora, e o renderizador avisa quais faltaram.
 */
class ContratoVariaveis
{
    /** chave => [grupo, rótulo, exemplo] */
    public const CATALOGO = [
        'colaborador.nome' => ['Colaborador', 'Nome', 'Maria da Silva'],
        'colaborador.cpf' => ['Colaborador', 'CPF', '123.456.789-09'],
        'colaborador.email' => ['Colaborador', 'E-mail', 'maria@exemplo.com.br'],
        'colaborador.telefone' => ['Colaborador', 'Telefone', '(62) 99999-0000'],
        'colaborador.endereco' => ['Colaborador', 'Endereço', 'Rua das Flores, 10 – Centro – Goiânia/GO – 74000-000'],
        'colaborador.data_admissao' => ['Colaborador', 'Data de admissão', '02/03/2026'],

        'pj.razao_social' => ['Pessoa jurídica', 'Razão social', 'Silva Serviços LTDA'],
        'pj.nome_fantasia' => ['Pessoa jurídica', 'Nome fantasia', 'Silva Serviços'],
        'pj.cnpj' => ['Pessoa jurídica', 'CNPJ', '12.345.678/0001-95'],
        'pj.endereco' => ['Pessoa jurídica', 'Endereço', 'Av. Central, 100 – Setor Sul – Goiânia/GO – 74000-100'],
        'pj.inscricao_municipal' => ['Pessoa jurídica', 'Inscrição municipal', '123456'],
        'pj.responsavel_nome' => ['Pessoa jurídica', 'Responsável: nome', 'Maria da Silva'],
        'pj.responsavel_cpf' => ['Pessoa jurídica', 'Responsável: CPF', '123.456.789-09'],

        'cargo.nome' => ['Cargo, empresa e obra', 'Cargo', 'Mestre de obras'],
        'cargo.descricao' => ['Cargo, empresa e obra', 'Descrição do cargo', 'Coordena as equipes de campo.'],
        'empresa.razao_social' => ['Cargo, empresa e obra', 'Empresa: razão social', 'ABL PRIME Empreendimentos S/A'],
        'empresa.cnpj' => ['Cargo, empresa e obra', 'Empresa: CNPJ', '98.765.432/0001-10'],
        'empresa.endereco' => ['Cargo, empresa e obra', 'Empresa: endereço', 'Av. Principal, 500 – Centro – Goiânia/GO – 74000-500'],
        'obra.descricao' => ['Cargo, empresa e obra', 'Obra', 'Residencial Aurora'],

        'remuneracao.valor' => ['Remuneração', 'Valor mensal', 'R$ 5.000,00'],
        'remuneracao.valor_extenso' => ['Remuneração', 'Valor mensal por extenso', 'cinco mil reais'],
        'remuneracao.vigencia_inicio' => ['Remuneração', 'Início da vigência', '02/03/2026'],

        'beneficios.lista' => ['Benefícios', 'Lista de benefícios com valores', '• Vale transporte: R$ 300,00 (fixo mensal)'],

        'contrato.numero' => ['Contrato', 'Número', '2026/0001'],
        'contrato.numero_pai' => ['Contrato', 'Número do contrato original (aditivo e distrato)', '2026/0001'],
        'contrato.vigencia_inicio' => ['Contrato', 'Início da vigência', '02/03/2026'],
        'contrato.vigencia_fim' => ['Contrato', 'Fim da vigência', '01/03/2027'],
        'contrato.data' => ['Contrato', 'Data de geração por extenso', '17 de novembro de 2026'],

        'aditivo.motivo' => ['Aditivo', 'Motivo do aditivo', 'Reajuste de remuneração'],
        'aditivo.valor_anterior' => ['Aditivo', 'Remuneração anterior', 'R$ 5.000,00'],
        'aditivo.valor_novo' => ['Aditivo', 'Nova remuneração', 'R$ 5.250,00'],
        'aditivo.valor_novo_extenso' => ['Aditivo', 'Nova remuneração por extenso', 'cinco mil duzentos e cinquenta reais'],
        'aditivo.cargo_anterior' => ['Aditivo', 'Cargo anterior', 'Encarregado'],
        'aditivo.cargo_novo' => ['Aditivo', 'Novo cargo', 'Mestre de obras'],
        'aditivo.nova_vigencia_fim' => ['Aditivo', 'Nova data de fim', '01/03/2028'],
        'aditivo.beneficios_alteracao' => ['Aditivo', 'Alteração de benefícios', 'Benefício incluído: Vale transporte'],

        'desligamento.data' => ['Desligamento', 'Data do desligamento', '30/06/2026'],
        'desligamento.motivo' => ['Desligamento', 'Motivo do desligamento', 'Término da obra'],

        'data.hoje' => ['Data', 'Data de hoje', '17/11/2026'],
        'data.hoje_extenso' => ['Data', 'Data de hoje por extenso', '17 de novembro de 2026'],
    ];

    /** @return array<int, string> */
    public static function chaves(): array
    {
        return array_keys(self::CATALOGO);
    }

    public static function existe(string $chave): bool
    {
        return array_key_exists($chave, self::CATALOGO);
    }

    /**
     * Para o painel do editor: grupo => [chave => rótulo].
     *
     * @return array<string, array<string, string>>
     */
    public static function porGrupo(): array
    {
        $grupos = [];
        foreach (self::CATALOGO as $chave => [$grupo, $rotulo]) {
            $grupos[$grupo][$chave] = $rotulo;
        }

        return $grupos;
    }

    /**
     * Valores de exemplo, para pré-visualizar um modelo sem colaborador.
     *
     * @return array<string, string|HtmlString>
     */
    public static function exemplos(): array
    {
        $valores = [];
        foreach (self::CATALOGO as $chave => [, , $exemplo]) {
            $valores[$chave] = $exemplo;
        }
        $valores['beneficios.lista'] = new HtmlString('<ul><li>Vale transporte: R$ 300,00 (fixo mensal)</li><li>Vale alimentação: R$ 35,00 (por dia trabalhado)</li></ul>');

        return $valores;
    }

    /**
     * Valores reais de um colaborador. Quem chama carrega as relações
     * (pessoaFisica, pessoaJuridica.responsavelAtual.pessoaFisica, cargo,
     * empresa, obra) e passa a remuneração e os benefícios vigentes na data
     * de referência. $dados traz o que é próprio de aditivo e distrato.
     *
     * @param  Collection<int, array{beneficio: \App\Models\Beneficio, valor: ?int, personalizado: bool}>  $beneficios
     * @param  array<string, mixed>  $dados
     * @return array<string, string|HtmlString>
     */
    public static function valores(
        Colaborador $colaborador,
        ?int $remuneracaoCentavos,
        ?CarbonInterface $remuneracaoInicio,
        Collection $beneficios,
        ?Contrato $contrato,
        array $dados,
        CarbonInterface $hoje,
    ): array {
        $pf = $colaborador->pessoaFisica;
        $pj = $colaborador->pessoaJuridica;
        $responsavel = $pj->responsavelAtual?->pessoaFisica;
        $data = fn (?CarbonInterface $d) => $d?->format('d/m/Y');
        $moeda = fn (?int $c) => $c === null ? null : Dinheiro::formatar($c);
        $extenso = fn (?int $c) => $c === null ? null : Extenso::moeda($c);

        $valores = [
            'colaborador.nome' => $pf->nome,
            'colaborador.cpf' => $pf->cpf_formatado,
            'colaborador.email' => $pf->email,
            'colaborador.telefone' => $pf->telefone,
            'colaborador.endereco' => $pf->endereco?->formatado(),
            'colaborador.data_admissao' => $data($colaborador->data_admissao),

            'pj.razao_social' => $pj->razao_social,
            'pj.nome_fantasia' => $pj->nome_fantasia,
            'pj.cnpj' => $pj->cnpj_formatado,
            'pj.endereco' => $pj->endereco?->formatado(),
            'pj.inscricao_municipal' => $pj->inscricao_municipal,
            'pj.responsavel_nome' => $responsavel?->nome,
            'pj.responsavel_cpf' => $responsavel?->cpf_formatado,

            'cargo.nome' => $colaborador->cargo?->nome,
            'cargo.descricao' => $colaborador->cargo?->descricao,
            'empresa.razao_social' => $colaborador->empresa?->razao_social,
            'empresa.cnpj' => $colaborador->empresa?->cnpj_formatado,
            'empresa.endereco' => $colaborador->empresa?->endereco?->formatado(),
            'obra.descricao' => $colaborador->obra?->descricao,

            'remuneracao.valor' => $moeda($remuneracaoCentavos),
            'remuneracao.valor_extenso' => $extenso($remuneracaoCentavos),
            'remuneracao.vigencia_inicio' => $data($remuneracaoInicio),

            'beneficios.lista' => self::listaBeneficios($beneficios),

            'contrato.numero' => $contrato?->numero,
            'contrato.numero_pai' => $contrato?->pai?->numero,
            'contrato.vigencia_inicio' => $data($contrato?->vigencia_inicio),
            'contrato.vigencia_fim' => $data($contrato?->vigencia_fim),
            'contrato.data' => Extenso::data($hoje),

            'aditivo.motivo' => $contrato?->motivo?->rotulo(),
            'aditivo.valor_anterior' => isset($dados['valor_anterior']) ? $moeda((int) $dados['valor_anterior']) : null,
            'aditivo.valor_novo' => isset($dados['valor_novo']) ? $moeda((int) $dados['valor_novo']) : null,
            'aditivo.valor_novo_extenso' => isset($dados['valor_novo']) ? $extenso((int) $dados['valor_novo']) : null,
            'aditivo.cargo_anterior' => $dados['cargo_anterior'] ?? null,
            'aditivo.cargo_novo' => $dados['cargo_novo'] ?? null,
            'aditivo.beneficios_alteracao' => $dados['beneficios_alteracao'] ?? null,
            'aditivo.nova_vigencia_fim' => isset($dados['nova_vigencia_fim']) ? date('d/m/Y', strtotime($dados['nova_vigencia_fim'])) : null,

            'desligamento.data' => $data($colaborador->data_desligamento),
            'desligamento.motivo' => $colaborador->motivo_desligamento,

            'data.hoje' => $hoje->format('d/m/Y'),
            'data.hoje_extenso' => Extenso::data($hoje),
        ];

        return array_filter($valores, fn ($valor) => $valor !== null && $valor !== '');
    }

    /** @param  Collection<int, array{beneficio: \App\Models\Beneficio, valor: ?int, personalizado: bool}>  $beneficios */
    private static function listaBeneficios(Collection $beneficios): ?HtmlString
    {
        $itens = $beneficios->filter(fn ($b) => $b['valor'] !== null);

        if ($itens->isEmpty()) {
            return null;
        }

        $linhas = $itens->map(fn ($b) => '<li>'.e($b['beneficio']->nome).': '.e(Dinheiro::formatar($b['valor'])).' ('.e(mb_strtolower($b['beneficio']->tipo_calculo->rotulo())).')</li>');

        return new HtmlString('<ul>'.$linhas->implode('').'</ul>');
    }
}
