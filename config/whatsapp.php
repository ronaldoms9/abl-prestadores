<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Driver
    |--------------------------------------------------------------------------
    |
    | 'waha' fala com o container de verdade; 'fake' guarda tudo em memória e é
    | o que permite testar o motor de conversa inteiro sem celular nenhum.
    | phpunit.xml fixa 'fake'.
    |
    */

    'driver' => env('WAHA_DRIVER', 'waha'),

    /*
    |--------------------------------------------------------------------------
    | Conexão com o WAHA
    |--------------------------------------------------------------------------
    */

    'base_url' => env('WAHA_BASE_URL', 'http://waha:3000'),
    'api_key' => env('WAHA_API_KEY'),
    'hmac_key' => env('WAHA_HMAC_KEY'),
    'engine' => env('WAHA_ENGINE', 'NOWEB'),
    'default_instance' => env('WAHA_DEFAULT_INSTANCE', 'waha-1'),
    'webhook_url' => env('WAHA_WEBHOOK_URL', env('APP_URL').'/api/webhooks/waha'),

    'timeout' => (int) env('WAHA_TIMEOUT', 5),

    /*
    |--------------------------------------------------------------------------
    | Webhook
    |--------------------------------------------------------------------------
    |
    | O WAHA assina o corpo bruto com SHA-512. A janela existe para barrar
    | reenvio; a idempotência existe porque a retentativa do WAHA transformaria
    | uma resposta lenta em três respostas ao cliente final.
    |
    */

    'webhook' => [
        'algoritmo' => 'sha512',
        'janela_segundos' => (int) env('WAHA_WEBHOOK_JANELA', 300),
        'idempotencia_minutos' => (int) env('WAHA_WEBHOOK_IDEMPOTENCIA_MINUTOS', 10),
    ],

    /*
    |--------------------------------------------------------------------------
    | Conversa
    |--------------------------------------------------------------------------
    */

    'fila' => env('WAHA_FILA', 'whatsapp'),

    'debounce_segundos' => (int) env('WAHA_DEBOUNCE_SEGUNDOS', 8),

    // O arquivo local do WAHA expira em 180s: mídia é baixada na hora, nunca
    // depois do debounce.
    'media_ttl_segundos' => 180,

    'digitando' => [
        'min_segundos' => (int) env('WAHA_DIGITANDO_MIN', 2),
        'max_segundos' => (int) env('WAHA_DIGITANDO_MAX', 4),
    ],

    // Mostrada ao cliente final quando o bot não pode responder: silêncio é a
    // única falha que o dono nunca perdoa, porque ele perde venda sem saber.
    'mensagem_escape' => env(
        'WAHA_MENSAGEM_ESCAPE',
        'Recebi sua mensagem! Já estou passando para uma pessoa do nosso time responder por aqui.'
    ),

    'handoff' => [
        'silencio_horas' => (int) env('WAHA_HANDOFF_SILENCIO_HORAS', 4),
        'confianca_minima' => (float) env('WAHA_CONFIANCA_MINIMA', 0.7),
    ],

    'expurgo_dias' => (int) env('WAHA_EXPURGO_DIAS', 90),

];
