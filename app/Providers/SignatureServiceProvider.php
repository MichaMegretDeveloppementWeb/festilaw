<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Repositories\SettingRepository;
use App\Services\Signature\SignatureManager;
use App\Services\Signature\SignWellWebhookRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class SignatureServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SignatureManager::class);

        // Type-hinting SignatureGatewayInterface resolves the provider set in config/signature.php.
        $this->app->bind(
            SignatureGatewayInterface::class,
            fn (Application $app): SignatureGatewayInterface => $app->make(SignatureManager::class)->driver(),
        );

        // Webhook(s) SignWell et leurs ids (cle HMAC des evenements), lus a chaque resolution de la config.
        $this->app->bind(
            SignWellWebhookRegistry::class,
            fn (Application $app): SignWellWebhookRegistry => new SignWellWebhookRegistry(
                (array) config('signature.drivers.signwell', []),
                $app->make(SettingRepository::class),
            ),
        );
    }
}
