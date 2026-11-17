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

    /*
    | Documentos anexados (E2, fatia 5). Disco privado: o arquivo só sai por rota
    | autorizada. Tamanho em KB e extensões aceitas no envio.
    */
    'documentos' => [
        'disco' => env('DOCUMENTOS_DISCO', 'local'),
        'tamanho_maximo_kb' => 10240,
        'extensoes' => ['pdf', 'jpg', 'jpeg', 'png'],
    ],

    /*
    | Alertas por e-mail. A antecedência pode ser mudada em `configuracoes`
    | (chave alertas.documentos, campo dias); este é o padrão.
    */
    'alertas' => [
        'documentos_dias' => 30,
    ],
];
