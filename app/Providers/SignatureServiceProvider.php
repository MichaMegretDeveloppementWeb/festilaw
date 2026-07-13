<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Signature\SignatureGatewayInterface;
use App\Services\Signature\SignatureManager;
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
    }
}
