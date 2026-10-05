<?php

declare(strict_types=1);

use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/*
 | festilaw:stripe-webhook (lancee par deploy.sh) : ajoute a l'endpoint webhook Stripe du site les evenements
 | qu'il traite (remboursements, litiges perdus, sessions expirees). N'en retire jamais, ne cree jamais
 | d'endpoint (son secret irait dans .env), n'envoie jamais rien depuis une URL locale.
 */

const SITE_STRIPE_WEBHOOK = 'https://festilaw.com/webhooks/payment/stripe';

beforeEach(function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe', ['secret_key' => 'sk_live_x', 'webhook_secret' => 'whsec_x']);
    URL::forceRootUrl('https://festilaw.com');
    URL::forceScheme('https');
});

/** @param  list<string>  $events */
function fakeStripeEndpoints(array $events, string $url = SITE_STRIPE_WEBHOOK): void
{
    Http::fake([
        'https://api.stripe.com/v1/webhook_endpoints?*' => Http::response(['data' => [
            ['id' => 'we_other', 'url' => 'https://another-site.example/stripe', 'enabled_events' => ['*']],
            ['id' => 'we_site', 'url' => $url, 'enabled_events' => $events],
        ]]),
        'https://api.stripe.com/v1/webhook_endpoints/*' => Http::response(['id' => 'we_site']),
    ]);
}

function stripeEndpointUpdates(): array
{
    return collect(Http::recorded())
        ->map(fn (array $pair): ClientRequest => $pair[0])
        ->filter(fn (ClientRequest $request): bool => $request->method() === 'POST')
        ->values()
        ->all();
}

it('adds the refund, dispute and expiry events to the site endpoint, keeping its own', function () {
    fakeStripeEndpoints(['checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed', 'customer.created']);

    $this->artisan('festilaw:stripe-webhook')
        ->expectsOutputToContain('evenements ajoutes checkout.session.expired, charge.refunded, charge.dispute.closed')
        ->doesntExpectOutputToContain('sk_live_x')
        ->doesntExpectOutputToContain('whsec_x')
        ->assertOk();

    $updates = stripeEndpointUpdates();
    expect($updates)->toHaveCount(1)
        ->and($updates[0]->url())->toBe('https://api.stripe.com/v1/webhook_endpoints/we_site')
        ->and($updates[0]->hasHeader('Authorization', 'Bearer sk_live_x'))->toBeTrue()
        ->and($updates[0]['enabled_events'])->toBe([
            'checkout.session.completed',
            'checkout.session.async_payment_succeeded',
            'checkout.session.async_payment_failed',
            'customer.created',
            'checkout.session.expired',
            'charge.refunded',
            'charge.dispute.closed',
        ]);
});

it('changes nothing on an endpoint already listening to every needed event, or to all events', function (array $events, string $expected) {
    fakeStripeEndpoints($events);

    $this->artisan('festilaw:stripe-webhook')
        ->expectsOutputToContain($expected)
        ->assertOk();

    expect(stripeEndpointUpdates())->toBe([]);
})->with([
    'complete' => [[
        'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'checkout.session.async_payment_failed',
        'checkout.session.expired', 'charge.refunded', 'charge.dispute.closed',
    ], 'evenements a jour'],
    'all events' => [['*'], 'ecoute deja tous les evenements'],
]);

it('only reports what it would add on a dry run', function () {
    fakeStripeEndpoints(['checkout.session.completed']);

    $this->artisan('festilaw:stripe-webhook', ['--dry' => true])
        ->expectsOutputToContain('ajouterait checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired, charge.refunded, charge.dispute.closed. [DRY-RUN]')
        ->assertOk();

    expect(stripeEndpointUpdates())->toBe([]);
});

it('never creates an endpoint: warns and fails when none calls the site', function () {
    fakeStripeEndpoints(['checkout.session.completed'], url: 'https://festilaw.com/some/other/path');

    $this->artisan('festilaw:stripe-webhook')
        ->expectsOutputToContain('Aucun endpoint webhook Stripe')
        ->assertFailed();

    expect(stripeEndpointUpdates())->toBe([]);
});

it('never calls Stripe from a local development URL', function (string $root) {
    URL::forceRootUrl($root);
    URL::forceScheme((string) parse_url($root, PHP_URL_SCHEME));
    Http::fake();

    $this->artisan('festilaw:stripe-webhook')
        ->expectsOutputToContain('non publique')
        ->assertFailed();

    Http::assertNothingSent();
})->with(['https://festilaw.test', 'http://festilaw.com', 'https://localhost']);

it('does nothing when Stripe is not active or has no secret key', function (array $enabled, ?string $key) {
    config()->set('payment.enabled', $enabled);
    config()->set('payment.drivers.stripe.secret_key', $key);
    Http::fake();

    $this->artisan('festilaw:stripe-webhook')->assertOk();

    Http::assertNothingSent();
})->with([
    'Stripe not enabled' => [['fake'], 'sk_live_x'],
    'no secret key' => [['stripe'], null],
]);

it('shows the Stripe answer and fails when Stripe refuses the update', function () {
    Http::fake([
        'https://api.stripe.com/v1/webhook_endpoints?*' => Http::response(['data' => [
            ['id' => 'we_site', 'url' => SITE_STRIPE_WEBHOOK, 'enabled_events' => ['checkout.session.completed']],
        ]]),
        'https://api.stripe.com/v1/webhook_endpoints/*' => Http::response(['error' => ['message' => 'The provided key does not have the required permissions']], 403),
    ]);

    $this->artisan('festilaw:stripe-webhook')
        ->expectsOutputToContain('(Stripe HTTP 403 : {"error":{"message":"The provided key does not have the required permissions"}})')
        ->assertFailed();
});
