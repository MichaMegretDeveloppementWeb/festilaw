<?php

use App\Http\Middleware\EnsureProductionIsConfigured;
use App\Services\System\ProductionSafetyService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

use function Pest\Laravel\get;

/** Sets a clean, production-fit configuration. */
function cleanProductionConfig(): void
{
    config()->set('payment.enabled', ['stripe']);
    config()->set('payment.drivers.stripe.secret_key', 'sk_live_x');
    config()->set('payment.drivers.stripe.webhook_secret', 'whsec_x');
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell.api_key', 'key_x');
    config()->set('signature.drivers.signwell.test_mode', false);
    config()->set('mail.default', 'smtp');
    config()->set('app.debug', false);
}

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

it('flags an incomplete production configuration as unfit for production', function () {
    config()->set('payment.enabled', []);       // Stripe non actif
    config()->set('signature.default', 'none');  // SignWell non actif
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
    config()->set('payment.enabled', []); // Stripe non actif

    $this->artisan('festilaw:check-production')->assertFailed();
});

it('serves normally in production despite an unfit config, logging a non-blocking warning', function () {
    $this->app['env'] = 'production';
    Cache::flush(); // repartir d'un throttle vierge
    Log::spy();
    config()->set('payment.enabled', []); // Stripe non actif -> violation

    $response = app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), fn () => response('ok'));

    // Le site est servi (plus de blocage 503) ...
    expect($response->getContent())->toBe('ok');
    // ... et un avertissement non bloquant est trace.
    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'non-blocking')
            && ($context['violations'] ?? []) !== []);
});

it('throttles the production warning to avoid flooding the logs on every request', function () {
    $this->app['env'] = 'production';
    Cache::flush();
    Log::spy();
    config()->set('payment.enabled', []); // meme jeu de manquements sur les deux requetes

    $handler = fn () => response('ok');
    app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), $handler);
    app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), $handler);

    // Deux requetes, un seul log (throttle par empreinte du jeu de manquements).
    Log::shouldHaveReceived('warning')->once();
});

it('serves normally in production when the configuration is clean, without warning', function () {
    $this->app['env'] = 'production';
    Cache::flush();
    Log::spy();
    cleanProductionConfig();

    $response = app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
    Log::shouldNotHaveReceived('warning');
});

it('the production check does nothing outside production, even with an unfit config', function () {
    // Environnement de test (non-production) + config inapte : le garde ne doit PAS bloquer.
    config()->set('payment.enabled', []);

    $response = app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});
