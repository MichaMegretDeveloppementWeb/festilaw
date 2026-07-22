<?php

use App\Services\System\ProductionSafetyService;

use function Pest\Laravel\get;

it('sets security headers on a normal 200 response', function () {
    get('/')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('sets security headers on a 404 error response too (headers are global, not web-group only)', function () {
    get('/this-route-does-not-exist')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('redirects the language switch back to a same-origin page', function () {
    get('/language/fr', ['referer' => url('/pricing')])
        ->assertRedirect(url('/pricing'));
});

it('never follows an external referer on the language switch (no open redirect)', function () {
    get('/language/fr', ['referer' => 'https://evil.example/phish'])
        ->assertRedirect(route('home'));
});

it('flags a dev/fake production configuration as unfit for production', function () {
    config()->set('payment.enabled', ['fake']);
    config()->set('signature.default', 'fake');
    config()->set('mail.default', 'log');
    config()->set('app.debug', true);

    $violations = app(ProductionSafetyService::class)->violations();

    expect($violations)->not->toBeEmpty()
        ->and(implode("\n", $violations))->toContain('Stripe')
        ->and(implode("\n", $violations))->toContain('SignWell')
        ->and(implode("\n", $violations))->toContain('MAIL_MAILER')
        ->and(implode("\n", $violations))->toContain('APP_DEBUG');
});

it('passes a clean production configuration', function () {
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe.secret_key', 'sk_live_x');
    config()->set('payment.drivers.stripe.webhook_secret', 'whsec_x');
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell.api_key', 'key_x');
    config()->set('signature.drivers.signwell.test_mode', false);
    config()->set('mail.default', 'smtp');
    config()->set('app.debug', false);

    expect(app(ProductionSafetyService::class)->violations())->toBe([]);
});

it('fails the go-live check command when the configuration is unfit', function () {
    config()->set('payment.enabled', ['fake']);

    $this->artisan('festilaw:check-production')->assertFailed();
});
