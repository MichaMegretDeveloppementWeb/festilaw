<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\Payment\PaymentGatewayInterface;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

final class PaymentServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Stripe needs its config injected.
        $this->app->singleton(
            StripePaymentGateway::class,
            fn (): StripePaymentGateway => new StripePaymentGateway((array) config('payment.drivers.stripe', [])),
        );

        // All known providers are tagged; the Registry keeps only the ones enabled by config.
        $this->app->tag([StripePaymentGateway::class], 'payment.gateways');

        $this->app->singleton(PaymentGatewayRegistry::class, function (Application $app): PaymentGatewayRegistry {
            $enabled = (array) config('payment.enabled', ['stripe']);

            $gateways = array_filter(
                iterator_to_array($app->tagged('payment.gateways')),
                fn (PaymentGatewayInterface $gateway): bool => in_array($gateway->key(), $enabled, true),
            );

            return new PaymentGatewayRegistry($gateways);
        });
    }
}
