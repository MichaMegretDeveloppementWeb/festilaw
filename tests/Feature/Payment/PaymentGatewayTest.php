<?php

use App\Data\Payment\CheckoutSessionData;
use App\Exceptions\Payment\PaymentException;
use App\Models\Payment;
use App\Services\Payment\FakePaymentGateway;
use App\Services\Payment\PaymentGatewayRegistry;
use App\Services\Payment\StripePaymentGateway;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('enables only the fake provider by default', function () {
    config()->set('payment.enabled', ['fake']);

    $registry = app(PaymentGatewayRegistry::class);

    expect($registry->all())->toHaveKey('fake')
        ->and($registry->has('stripe'))->toBeFalse()
        ->and($registry->get('fake'))->toBeInstanceOf(FakePaymentGateway::class)
        ->and($registry->options())->toHaveKey('fake');
});

it('lets several providers coexist and be picked by key', function () {
    config()->set('payment.enabled', ['fake', 'stripe']);

    $registry = app(PaymentGatewayRegistry::class);

    expect($registry->all())->toHaveKeys(['fake', 'stripe'])
        ->and($registry->get('stripe'))->toBeInstanceOf(StripePaymentGateway::class)
        ->and($registry->get('fake'))->toBeInstanceOf(FakePaymentGateway::class);
});

it('throws when asking for a provider that is not enabled', function () {
    config()->set('payment.enabled', ['fake']);

    app(PaymentGatewayRegistry::class)->get('paypal');
})->throws(PaymentException::class);

it('creates a checkout with the fake provider without any external call', function () {
    config()->set('payment.enabled', ['fake']);
    $payment = Payment::factory()->create();

    $session = app(PaymentGatewayRegistry::class)->get('fake')->createCheckout($payment);

    expect($session)->toBeInstanceOf(CheckoutSessionData::class)
        ->and($session->providerReference)->toStartWith('fake_')
        ->and($session->redirectUrl)->not->toBeEmpty();
});
