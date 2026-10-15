<?php

return [

    'base_url' => env('OPENROUTER_BASE_URL', 'https://openrouter.ai/api/v1'),
    'api_key' => env('OPENROUTER_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Modelos
    |--------------------------------------------------------------------------
    |
    | Versão fixada, nunca alias flutuante: um '-latest' troca o modelo debaixo
    | do prompt sem aviso. A lista de chat é ordenada — o primeiro é o principal
    | e o segundo é a reserva, de outro provedor.
    |
    | Confirmado em 14/09/2026 na lista pública de endpoints ZDR da OpenRouter:
    | gemini-2.5-flash-lite (Google), gpt-5-nano (Azure) e whisper-large-v3-turbo
    | (DeepInfra) têm endpoint com retenção zero.
    |
    */

    'modelos' => array_values(array_filter([
        env('OPENROUTER_MODELO_PRINCIPAL', 'google/gemini-2.5-flash-lite'),
        env('OPENROUTER_MODELO_RESERVA', 'openai/gpt-5-nano'),
    ])),

    'modelo_transcricao' => env('OPENROUTER_MODELO_TRANSCRICAO', 'openai/whisper-large-v3-turbo'),

    'timeout' => (int) env('OPENROUTER_TIMEOUT', 20),
    'tentativas' => (int) env('OPENROUTER_TENTATIVAS', 2),

    // Retenção zero de dados: dado de conversa de terceiro não fica no provedor.
    'zdr' => (bool) env('OPENROUTER_ZDR', true),

];
