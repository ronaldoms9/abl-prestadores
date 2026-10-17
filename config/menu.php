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
    ['titulo' => 'Empresas', 'icone' => 'bi bi-building', 'rota' => 'empresa.index', 'permissao' => 'empresa-list'],
    ['titulo' => 'Obras', 'icone' => 'bi bi-cone-striped', 'rota' => 'obra.index', 'permissao' => 'obra-list'],
    ['titulo' => 'Integrações', 'icone' => 'bi bi-arrow-left-right', 'rota' => 'integracao.index', 'permissao' => 'integracao-list'],
    ['titulo' => 'Configurações', 'icone' => 'bi bi-gear', 'itens' => [
        ['titulo' => 'Usuários', 'rota' => 'user.index', 'permissao' => 'user-list'],
        ['titulo' => 'Perfis', 'rota' => 'grupo.index', 'permissao' => 'grupo-list'],
        ['titulo' => 'Auditoria', 'rota' => 'auditoria.index', 'permissao' => 'auditoria-list'],
    ]],
];
