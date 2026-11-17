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
    | Contratos (E3, fatia 3). PDFs gerados e contratos assinados ficam em disco
    | privado e só saem por rota autorizada. Numeração AAAA/NNNN; o prefixo e o
    | reinício anual podem ser mudados em `configuracoes` (chave contratos.numeracao,
    | campos prefixo e reinicia_anualmente). Padrão: sem prefixo, reinicia todo ano.
    */
    'contratos' => [
        'disco' => env('CONTRATOS_DISCO', 'local'),
        'tamanho_maximo_kb' => 20480,
        'extensoes_assinado' => ['pdf', 'jpg', 'jpeg', 'png'],
        'numeracao' => ['prefixo' => '', 'reinicia_anualmente' => true],
        'alerta_dias' => [30, 15, 5],
    ],

    /*
    | Férias (E4, fatia 2). Todos os valores podem ser mudados em `configuracoes`
    | (chaves ferias.dias_direito, ferias.limite_gozo_meses, ferias.alerta_dias);
    | estes são os padrões. O período aquisitivo tem 12 meses a partir da admissão;
    | o limite de gozo é o fim dele mais `limite_gozo_meses`. O alerta avisa quando
    | o saldo está a `alerta_dias` dias do limite (ou já passou dele).
    */
    'ferias' => [
        'dias_direito' => 30,
        'limite_gozo_meses' => 12,
        'alerta_dias' => 60,
    ],

    /*
    | Alertas por e-mail. A antecedência pode ser mudada em `configuracoes`
    | (chave alertas.documentos, campo dias); este é o padrão.
    */
    'alertas' => [
        'documentos_dias' => 30,
        'qualificacoes_dias' => 30,
    ],
];
