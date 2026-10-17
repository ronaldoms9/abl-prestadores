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
];
