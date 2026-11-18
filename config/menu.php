<?php

/*
|--------------------------------------------------------------------------
| Menu lateral
|--------------------------------------------------------------------------
|
| Um item aparece quando a rota existe e o usuário tem a permissão (sem
| 'permissao', aparece para todos). Um grupo aparece quando tem ao menos um
| item visível. Assim, as seções surgem à medida que as rotas são criadas.
| 'ativo' (opcional) lista os padrões de rota que marcam o item como ativo;
| o padrão é o prefixo da rota, por exemplo 'user.*' para 'user.index'.
|
| A filtragem fica em App\View\Menu.
|
*/

return [
    ['titulo' => 'Dashboard', 'icone' => 'bi bi-speedometer', 'rota' => 'home', 'ativo' => ['home', 'main', 'dashboard']],
    ['titulo' => 'Pessoas', 'icone' => 'bi bi-people', 'itens' => [
        ['titulo' => 'Pessoas físicas', 'rota' => 'pessoa-fisica.index', 'permissao' => 'pessoa-list'],
        ['titulo' => 'Pessoas jurídicas', 'rota' => 'pessoa-juridica.index', 'permissao' => 'pessoa-list'],
        ['titulo' => 'Carregar planilha', 'rota' => 'importacao-pessoa.index', 'permissao' => 'pessoa-importar'],
    ]],
    ['titulo' => 'Colaboradores', 'icone' => 'bi bi-person-badge', 'rota' => 'colaborador.index', 'permissao' => 'colaborador-list'],
    ['titulo' => 'Cargos e salários', 'icone' => 'bi bi-cash-coin', 'itens' => [
        ['titulo' => 'Cargos', 'rota' => 'cargo.index', 'permissao' => 'cargo-list'],
        ['titulo' => 'Reajuste em lote', 'rota' => 'reajuste.lote', 'permissao' => 'remuneracao-reajustar', 'ativo' => ['reajuste.lote', 'reajuste.lote.*']],
    ]],
    ['titulo' => 'Contratos', 'icone' => 'bi bi-file-earmark-text', 'itens' => [
        ['titulo' => 'Aditivos pendentes', 'rota' => 'aditivo.pendentes', 'permissao' => 'contrato-gerar', 'ativo' => ['aditivo.pendentes', 'aditivo.pendentes.*']],
        ['titulo' => 'Modelos', 'rota' => 'contrato-modelo.index', 'permissao' => 'contrato-modelo-gerir', 'ativo' => ['contrato-modelo.*']],
    ]],
    ['titulo' => 'Benefícios', 'icone' => 'bi bi-gift', 'itens' => [
        ['titulo' => 'Tabela de benefícios', 'rota' => 'beneficio.index', 'permissao' => 'beneficio-list', 'ativo' => ['beneficio.*']],
        ['titulo' => 'Reajuste de benefícios', 'rota' => 'reajuste.beneficios', 'permissao' => 'beneficio-gerir', 'ativo' => ['reajuste.beneficios', 'reajuste.beneficios.*']],
    ]],
    ['titulo' => 'Admissão', 'icone' => 'bi bi-person-check', 'itens' => [
        ['titulo' => 'Checklist de admissão', 'rota' => 'admissao-item.index', 'permissao' => 'admissao-gerir', 'ativo' => ['admissao-item.*']],
    ]],
    ['titulo' => 'Qualificações', 'icone' => 'bi bi-award', 'itens' => [
        ['titulo' => 'Pendências', 'rota' => 'qualificacao.pendentes', 'permissao' => 'qualificacao-list'],
        ['titulo' => 'Tipos de qualificação', 'rota' => 'qualificacao-tipo.index', 'permissao' => 'qualificacao-tipo-gerir'],
        ['titulo' => 'Matriz por cargo', 'rota' => 'qualificacao.matriz', 'permissao' => 'qualificacao-tipo-gerir'],
    ]],
    ['titulo' => 'Férias e ausências', 'icone' => 'bi bi-calendar-heart', 'itens' => [
        ['titulo' => 'Solicitações de férias', 'rota' => 'ferias.index', 'permissao' => ['ferias-list', 'ferias-aprovar-rh', 'ferias-aprovar-qualquer'], 'ativo' => ['ferias.index', 'ferias.decidir.form']],
        ['titulo' => 'Calendário', 'rota' => 'ferias.calendario', 'permissao' => 'ferias-list', 'ativo' => ['ferias.calendario', 'ferias.calendario.*']],
        ['titulo' => 'Feriados', 'rota' => 'feriado.index', 'permissao' => 'feriado-list', 'ativo' => ['feriado.*']],
        ['titulo' => 'Ocorrências', 'rota' => 'ocorrencia.index', 'permissao' => 'ocorrencia-list', 'ativo' => ['ocorrencia.*']],
        ['titulo' => 'Fluxos de aprovação', 'rota' => 'ferias-nivel.index', 'permissao' => 'ferias-niveis-configurar', 'ativo' => ['ferias-nivel.*', 'ferias-fluxo.*']],
    ]],
    ['titulo' => 'Fechamento mensal', 'icone' => 'bi bi-calculator', 'rota' => 'fechamento.index', 'permissao' => 'fechamento-list', 'ativo' => ['fechamento.*']],
    ['titulo' => 'Relatórios', 'icone' => 'bi bi-bar-chart-line', 'rota' => 'relatorio.index', 'permissao' => ['fechamento-list', 'ferias-list', 'qualificacao-list', 'contrato-list', 'nota-list'], 'ativo' => ['relatorio.*']],
    ['titulo' => 'Notas fiscais', 'icone' => 'bi bi-receipt', 'rota' => 'nota.index', 'permissao' => 'nota-list', 'ativo' => ['nota.*']],
    ['titulo' => 'Meu portal', 'icone' => 'bi bi-person-circle', 'itens' => [
        ['titulo' => 'Início', 'rota' => 'portal.index', 'permissao' => 'portal-acessar'],
        ['titulo' => 'Minhas férias', 'rota' => 'portal.ferias', 'permissao' => 'portal-acessar', 'ativo' => ['portal.ferias', 'portal.ferias.*']],
        ['titulo' => 'Minhas notas', 'rota' => 'portal.notas', 'permissao' => 'portal-acessar', 'ativo' => ['portal.notas', 'portal.nota.*']],
    ]],
    ['titulo' => 'Equipes', 'icone' => 'bi bi-diagram-3', 'rota' => 'equipe.index', 'permissao' => 'equipe-list'],
    ['titulo' => 'Empresas', 'icone' => 'bi bi-building', 'rota' => 'empresa.index', 'permissao' => 'empresa-list'],
    ['titulo' => 'Obras', 'icone' => 'bi bi-cone-striped', 'rota' => 'obra.index', 'permissao' => 'obra-list'],
    ['titulo' => 'Integrações', 'icone' => 'bi bi-arrow-left-right', 'rota' => 'integracao.index', 'permissao' => 'integracao-list'],
    ['titulo' => 'Ajuda', 'icone' => 'bi bi-question-circle', 'rota' => 'ajuda.index', 'permissao' => ['colaborador-edit', 'colaborador-create', 'admissao-gerir', 'contrato-gerar', 'ferias-aprovar-rh', 'equipe-gerir', 'fechamento-aprovar', 'nota-decidir', 'portal-acessar', 'grupo-edit', 'fechamento-reabrir', 'horizon-acessar'], 'ativo' => ['ajuda.*']],
    ['titulo' => 'Configurações', 'icone' => 'bi bi-gear', 'itens' => [
        ['titulo' => 'Usuários', 'rota' => 'user.index', 'permissao' => 'user-list'],
        ['titulo' => 'Perfis', 'rota' => 'grupo.index', 'permissao' => 'grupo-list'],
        ['titulo' => 'Tipos de documento', 'rota' => 'documento-tipo.index', 'permissao' => 'documento-tipo-gerir'],
        ['titulo' => 'Auditoria', 'rota' => 'auditoria.index', 'permissao' => 'auditoria-list'],
    ]],
];
