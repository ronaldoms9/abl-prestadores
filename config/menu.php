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
    ['titulo' => 'Equipes', 'icone' => 'bi bi-diagram-3', 'rota' => 'equipe.index', 'permissao' => 'equipe-list'],
    ['titulo' => 'Empresas', 'icone' => 'bi bi-building', 'rota' => 'empresa.index', 'permissao' => 'empresa-list'],
    ['titulo' => 'Obras', 'icone' => 'bi bi-cone-striped', 'rota' => 'obra.index', 'permissao' => 'obra-list'],
    ['titulo' => 'Integrações', 'icone' => 'bi bi-arrow-left-right', 'rota' => 'integracao.index', 'permissao' => 'integracao-list'],
    ['titulo' => 'Configurações', 'icone' => 'bi bi-gear', 'itens' => [
        ['titulo' => 'Usuários', 'rota' => 'user.index', 'permissao' => 'user-list'],
        ['titulo' => 'Perfis', 'rota' => 'grupo.index', 'permissao' => 'grupo-list'],
        ['titulo' => 'Tipos de documento', 'rota' => 'documento-tipo.index', 'permissao' => 'documento-tipo-gerir'],
        ['titulo' => 'Auditoria', 'rota' => 'auditoria.index', 'permissao' => 'auditoria-list'],
    ]],
];
