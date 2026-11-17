<?php

namespace Database\Seeders;

use App\Permission;
use Illuminate\Database\Seeder;

/**
 * Cria e atualiza as permissões. Não mexe nos vínculos com perfis: isso é
 * do PerfilSeeder, que usa a lista das permissões criadas nesta execução.
 */
class PermissionSeeder extends Seeder
{
    /** @var array<int, string> Nomes das permissões criadas na última execução. */
    public static array $criadas = [];

    public function run(): void
    {
        static::$criadas = [];

        foreach ($this->permissoes() as $permissao) {
            $registro = Permission::updateOrCreate(
                ['name' => $permissao['name']],
                [
                    'display_name' => $permissao['display_name'],
                    'description' => $permissao['description'] ?? null,
                    'module' => $permissao['module'],
                ],
            );

            if ($registro->wasRecentlyCreated) {
                static::$criadas[] = $registro->name;
            }
        }
    }

    /**
     * @return array<int, array{name: string, display_name: string, description?: string, module: string}>
     */
    protected function permissoes(): array
    {
        return [
            ['name' => 'user-list', 'display_name' => 'Listar usuários', 'description' => 'Acessa a tela de usuários e lista os cadastrados', 'module' => 'Usuários'],
            ['name' => 'user-create', 'display_name' => 'Cadastrar usuários', 'description' => 'Cadastra usuários', 'module' => 'Usuários'],
            ['name' => 'user-edit', 'display_name' => 'Editar usuários', 'description' => 'Edita usuários, inclusive ativa e desativa', 'module' => 'Usuários'],
            ['name' => 'user-delete', 'display_name' => 'Excluir usuários', 'description' => 'Exclui usuários cadastrados por engano', 'module' => 'Usuários'],

            ['name' => 'grupo-list', 'display_name' => 'Listar perfis', 'description' => 'Acessa a tela de perfis e lista os cadastrados', 'module' => 'Perfis'],
            ['name' => 'grupo-create', 'display_name' => 'Cadastrar perfis', 'description' => 'Cadastra perfis de acesso', 'module' => 'Perfis'],
            ['name' => 'grupo-edit', 'display_name' => 'Editar perfis', 'description' => 'Edita perfis e suas permissões', 'module' => 'Perfis'],
            ['name' => 'grupo-delete', 'display_name' => 'Excluir perfis', 'description' => 'Exclui perfis de acesso', 'module' => 'Perfis'],

            ['name' => 'empresa-list', 'display_name' => 'Ver empresas', 'module' => 'Cadastros UAU'],
            ['name' => 'obra-list', 'display_name' => 'Ver obras', 'module' => 'Cadastros UAU'],

            ['name' => 'pessoa-list', 'display_name' => 'Ver pessoas', 'module' => 'Pessoas'],
            ['name' => 'pessoa-create', 'display_name' => 'Cadastrar pessoas', 'description' => 'Cadastra pessoas físicas e jurídicas', 'module' => 'Pessoas'],
            ['name' => 'pessoa-edit', 'display_name' => 'Editar pessoas', 'description' => 'Edita pessoas físicas e jurídicas, dados bancários e responsável da PJ', 'module' => 'Pessoas'],
            ['name' => 'pessoa-importar', 'display_name' => 'Carregar planilha de pessoas', 'description' => 'Carrega a planilha e vê os relatórios de carga', 'module' => 'Pessoas'],

            ['name' => 'cargo-list', 'display_name' => 'Ver cargos', 'module' => 'Cargos e remuneração'],
            ['name' => 'cargo-create', 'display_name' => 'Cadastrar cargos', 'module' => 'Cargos e remuneração'],
            ['name' => 'cargo-edit', 'display_name' => 'Editar cargos', 'module' => 'Cargos e remuneração'],
            ['name' => 'remuneracao-list', 'display_name' => 'Ver remuneração', 'description' => 'Vê a remuneração atual e o histórico de cada colaborador', 'module' => 'Cargos e remuneração'],
            ['name' => 'remuneracao-reajustar', 'display_name' => 'Definir e reajustar remuneração', 'description' => 'Define a remuneração inicial e faz reajustes individuais e em lote', 'module' => 'Cargos e remuneração'],

            ['name' => 'documento-list', 'display_name' => 'Ver e baixar documentos', 'description' => 'Vê e baixa os documentos anexados a pessoas e colaboradores', 'module' => 'Documentos'],
            ['name' => 'documento-edit', 'display_name' => 'Anexar e excluir documentos', 'module' => 'Documentos'],
            ['name' => 'documento-tipo-gerir', 'display_name' => 'Gerir tipos de documento', 'description' => 'Cria e edita os tipos de documento e se exigem validade', 'module' => 'Documentos'],
            ['name' => 'alerta-documentos', 'display_name' => 'Receber alerta de documentos', 'description' => 'Recebe o resumo diário por e-mail de documentos vencidos e a vencer', 'module' => 'Documentos'],

            ['name' => 'qualificacao-list', 'display_name' => 'Ver qualificações', 'description' => 'Vê as qualificações dos colaboradores, a lista de pendências e baixa certificados', 'module' => 'Qualificações'],
            ['name' => 'qualificacao-edit', 'display_name' => 'Registrar qualificações', 'description' => 'Registra e exclui qualificações do colaborador', 'module' => 'Qualificações'],
            ['name' => 'qualificacao-tipo-gerir', 'display_name' => 'Gerir tipos de qualificação e matriz', 'description' => 'Cria os tipos de qualificação e define o que cada cargo exige', 'module' => 'Qualificações'],
            ['name' => 'alerta-qualificacoes', 'display_name' => 'Receber alerta de qualificações', 'description' => 'Recebe o resumo diário por e-mail de qualificações ausentes, vencidas e a vencer', 'module' => 'Qualificações'],

            ['name' => 'colaborador-list', 'display_name' => 'Ver colaboradores', 'description' => 'Quem tem equipe-gerir e não tem colaborador-edit vê só os colaboradores das equipes que gere hoje', 'module' => 'Colaboradores'],
            ['name' => 'colaborador-create', 'display_name' => 'Cadastrar colaboradores', 'description' => 'Liga uma pessoa física a uma pessoa jurídica, cargo, empresa e obra', 'module' => 'Colaboradores'],
            ['name' => 'colaborador-edit', 'display_name' => 'Editar colaboradores', 'description' => 'Edita colaboradores e os vínculos com equipes', 'module' => 'Colaboradores'],

            ['name' => 'equipe-list', 'display_name' => 'Ver equipes', 'description' => 'Quem também tem equipe-gerir e não tem equipe-edit vê só as equipes que gere', 'module' => 'Equipes'],
            ['name' => 'equipe-create', 'display_name' => 'Cadastrar equipes', 'module' => 'Equipes'],
            ['name' => 'equipe-edit', 'display_name' => 'Editar equipes', 'description' => 'Edita equipes, seus gestores e os colaboradores de cada equipe', 'module' => 'Equipes'],
            ['name' => 'equipe-gerir', 'display_name' => 'Gerir equipes', 'description' => 'Pode ser gestor de equipe; só quem tem esta permissão entra na lista de gestores', 'module' => 'Equipes'],

            ['name' => 'beneficio-list', 'display_name' => 'Ver benefícios', 'description' => 'Vê a tabela de benefícios, os valores e os benefícios de cada colaborador', 'module' => 'Benefícios'],
            ['name' => 'beneficio-gerir', 'display_name' => 'Gerir benefícios', 'description' => 'Cria benefícios, define e reajusta valores (inclusive em lote) e define os benefícios padrão de cada cargo', 'module' => 'Benefícios'],
            ['name' => 'colaborador-beneficio-edit', 'display_name' => 'Vincular benefícios ao colaborador', 'description' => 'Vincula e encerra benefícios do colaborador, com valor personalizado opcional', 'module' => 'Benefícios'],

            ['name' => 'contrato-list', 'display_name' => 'Ver e baixar contratos', 'description' => 'Vê a linha do tempo de contratos, aditivos e distratos do colaborador e baixa os PDFs', 'module' => 'Contratos'],
            ['name' => 'contrato-gerar', 'display_name' => 'Gerar contratos', 'description' => 'Gera contrato, aditivo e distrato em PDF, renova e cancela rascunhos', 'module' => 'Contratos'],
            ['name' => 'contrato-assinar', 'display_name' => 'Anexar contrato assinado', 'module' => 'Contratos'],
            ['name' => 'contrato-modelo-gerir', 'display_name' => 'Gerir modelos de contrato', 'description' => 'Cria e edita os modelos de contrato, aditivo e distrato e suas versões', 'module' => 'Contratos'],
            ['name' => 'alerta-contratos', 'display_name' => 'Receber alerta de contratos', 'description' => 'Recebe o resumo diário por e-mail de contratos vencidos e a vencer', 'module' => 'Contratos'],

            ['name' => 'admissao-gerir', 'display_name' => 'Gerir admissões', 'description' => 'Inicia a admissão, marca os itens do checklist e conclui a admissão; configura os itens do checklist', 'module' => 'Admissão e desligamento'],
            ['name' => 'colaborador-desligar', 'display_name' => 'Desligar colaboradores', 'description' => 'Registra o desligamento, gera o distrato e vê a prévia dos valores finais', 'module' => 'Admissão e desligamento'],

            ['name' => 'ferias-list', 'display_name' => 'Ver férias e saldo', 'description' => 'Vê os períodos aquisitivos, o saldo e as solicitações de férias do colaborador', 'module' => 'Férias'],
            ['name' => 'ferias-solicitar', 'display_name' => 'Solicitar férias', 'description' => 'Registra solicitação de férias de colaboradores; o gestor só dos que gere', 'module' => 'Férias'],
            ['name' => 'ferias-aprovar-rh', 'display_name' => 'Aprovar férias (RH)', 'description' => 'Aprova férias no nível do RH (padrão: nível 2 do fluxo)', 'module' => 'Férias'],
            ['name' => 'ferias-aprovar-qualquer', 'display_name' => 'Aprovar qualquer férias', 'description' => 'Recebe a solicitação quando nenhum nível tem aprovador elegível', 'module' => 'Férias'],
            ['name' => 'ferias-niveis-configurar', 'display_name' => 'Configurar níveis de aprovação de férias', 'module' => 'Férias'],
            ['name' => 'alerta-ferias', 'display_name' => 'Receber alerta de férias', 'description' => 'Recebe o resumo diário por e-mail de saldos de férias perto do limite de gozo', 'module' => 'Férias'],

            ['name' => 'integracao-list', 'display_name' => 'Ver monitoramento das integrações', 'module' => 'Integrações'],
            ['name' => 'integracao-sincronizar', 'display_name' => 'Sincronizar agora', 'module' => 'Integrações'],
            ['name' => 'integracao-reenviar', 'display_name' => 'Reprocessar falha de integração', 'module' => 'Integrações'],

            ['name' => 'auditoria-list', 'display_name' => 'Ver auditoria', 'module' => 'Configurações'],
            ['name' => 'horizon-acessar', 'display_name' => 'Acessar painel de filas (Horizon)', 'module' => 'Configurações'],
        ];
    }
}
