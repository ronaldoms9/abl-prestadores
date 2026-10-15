<?php

namespace App\WhatsApp;

use App\WhatsApp\Contracts\WhatsAppProvider;
use App\WhatsApp\Fake\FakeWhatsAppProvider;
use App\WhatsApp\Waha\WahaProvider;
use Illuminate\Support\ServiceProvider;

class WhatsAppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(WhatsAppProvider::class, function ($app) {
            if ($app['config']->get('whatsapp.driver') === 'fake') {
                return new FakeWhatsAppProvider();
            }

            return new WahaProvider(
                baseUrl: $app['config']->get('whatsapp.base_url'),
                apiKey: $app['config']->get('whatsapp.api_key'),
                timeout: (int) $app['config']->get('whatsapp.timeout', 5),
                webhookUrl: $app['config']->get('whatsapp.webhook_url'),
                hmacKey: $app['config']->get('whatsapp.hmac_key'),
            );
        });
    }
}
