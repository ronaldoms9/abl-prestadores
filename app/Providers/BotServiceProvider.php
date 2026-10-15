<?php

namespace App\Providers;

use App\OpenRouter\OpenRouterClient;
use Illuminate\Support\ServiceProvider;

class BotServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(OpenRouterClient::class, function ($app) {
            $config = $app['config'];

            return new OpenRouterClient(
                baseUrl: $config->get('openrouter.base_url'),
                apiKey: $config->get('openrouter.api_key'),
                zdr: (bool) $config->get('openrouter.zdr'),
                timeout: (int) $config->get('openrouter.timeout'),
                tentativas: (int) $config->get('openrouter.tentativas'),
            );
        });
    }
}
