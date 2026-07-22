<?php

use App\Exceptions\Payment\PaymentException;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps only the providers enabled by config', function () {
    config()->set('payment.enabled', ['stripe']);

    $registry = app(PaymentGatewayRegistry::class);

    expect($registry->has('stripe'))->toBeTrue()
        ->and($registry->get('stripe'))->toBeInstanceOf(StripePaymentGateway::class)
        ->and($registry->has('paypal'))->toBeFalse()
        ->and($registry->options())->toHaveKey('stripe');
});

it('throws when asking for a provider that is not enabled', function () {
    config()->set('payment.enabled', ['stripe']);

    app(PaymentGatewayRegistry::class)->get('paypal');
})->throws(PaymentException::class);
