<?php

return [
    /*
    | Consulta de CNPJ (E2, fatia 3). BrasilAPI é gratuita e sem chave. A resposta
    | fica em cache por 24 h; falha da consulta não impede o cadastro manual.
    */
    'consulta_cnpj' => [
        'url' => env('CONSULTA_CNPJ_URL', 'https://brasilapi.com.br/api/cnpj/v1'),
        'timeout' => (int) env('CONSULTA_CNPJ_TIMEOUT', 8),
        'cache_horas' => 24,
    ],
];
