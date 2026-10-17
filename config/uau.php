<?php

/*
|--------------------------------------------------------------------------
| Integração com o UAU (Globaltec)
|--------------------------------------------------------------------------
|
| Credenciais e limites da API REST do UAU. Com 'fake' ligado, a aplicação
| usa o cliente falso em vez de chamar a API (ligação feita na fatia 4).
|
*/

return [
    'base_url' => env('UAU_BASE_URL'),
    'login' => env('UAU_LOGIN'),
    'senha' => env('UAU_SENHA'),
    'token_integracao' => env('UAU_TOKEN_INTEGRACAO'),
    'timeout' => (int) env('UAU_TIMEOUT', 30),
    'token_ttl' => (int) env('UAU_TOKEN_TTL', 1800),
    'fake' => (bool) env('UAU_FAKE', true),

    // Prefixo do header Authorization antes do token. O UAU aceita o token puro
    // (confirmado na integração do SGTR); "Bearer " fica disponível sem mudar código.
    'authorization_prefixo' => env('UAU_AUTHORIZATION_PREFIXO', ''),

    // Recusa URL http:// fora do ambiente local: login, senha e tokens trafegam nas chamadas.
    'exigir_https' => (bool) env('UAU_EXIGIR_HTTPS', true),

    // Mensagem de um 400 em Pessoas/ConsultarDadosPessoaPorCpfCnpjEStatus que
    // significa "pessoa não encontrada". Provisório: confirmar com uau:ping --documento.
    'padrao_nao_encontrada' => '/n[ãa]o (foi )?encontrad|nenhum/iu',

    // Campos trocados por "[oculto]" em integracao_logs, em qualquer nível, sem
    // diferenciar maiúsculas. Nunca gravar senha, token ou dado bancário.
    'campos_ocultos' => [
        'senha', 'senhauauweb', 'loguinuauweb', 'dadosbancarios',
        'token', 'authorization', 'x-integration-authorization',
    ],

    // Dados de exemplo do FakeUauClient (fictícios; ver o README da pasta).
    'fake_fixtures' => resource_path('fixtures/uau'),

    // Trava da importação de empresas e obras: uma lista que desativaria mais
    // que esta fração dos ativos (ou uma lista vazia) suspende a importação sem
    // gravar nada. Com aval da ABL PRIME, rodar uma vez com 1.0 para liberar.
    'limite_inativacao' => (float) env('UAU_LIMITE_INATIVACAO', 0.5),

    // Carga de pessoas por planilha (fatia 5b): limite de linhas por arquivo,
    // linhas consultadas por execução do job e disco onde o arquivo fica guardado.
    'planilha' => [
        'max_linhas' => (int) env('UAU_PLANILHA_MAX_LINHAS', 2000),
        'lote' => (int) env('UAU_PLANILHA_LOTE', 50),
        'disco' => env('UAU_PLANILHA_DISCO', 'local'),
    ],

    // Conciliação diária completa (fatia 6), no fuso APP_TIMEZONE.
    'sincronizacao' => [
        'horario' => env('UAU_SINCRONIZACAO_HORARIO', '05:00'),
    ],

    // Significado dos códigos de Status_obr (código => rótulo). Pendente da ABL PRIME:
    // vazio, a tela de obras mostra "Código N". Preencher não exige mudar código.
    'status_obra' => [
        // 0 => 'Em andamento',
    ],
];
