<?php

use App\Http\Middleware\EnsureProductionIsConfigured;
use App\Services\System\ProductionSafetyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Mail\Transport\ResendTransport;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
    config()->set('ai.providers.openrouter.key', 'or_x');
    config()->set('services.pixabay.key', 'px_x');
    config()->set('blog.ai.pipeline', 'editorial');
    config()->set('analytics.enabled', true);
    config()->set('festilaw.tech_alert_email', 'tech@example.com');
    config()->set('logging.default', 'stack');
    config()->set('logging.channels.stack.channels', ['daily']);
}

// Les pages publiques lisent la base : lien "Blog" de l'en-tete et du pied de page, bloc de l'accueil.
uses(RefreshDatabase::class);

it('sets security headers on a normal 200 response', function () {
    get('/')->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('sets security headers on a 404 error response too (headers are global, not web-group only)', function () {
    get('/this-route-does-not-exist')
        ->assertNotFound()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});

it('redirects the language switch back to a same-origin page', function () {
    // Page a adresse unique (tunnel) : retour a la meme adresse, la langue est en session.
    get('/language/fr', ['referer' => route('get-started.index')])
        ->assertRedirect(route('get-started.index'));

    // Page publique (une adresse par langue) : retour a son equivalent dans la langue demandee.
    get('/language/fr', ['referer' => url('/pricing')])
        ->assertRedirect(url('/fr/pricing'));
});

it('never follows an external referer on the language switch (no open redirect)', function () {
    // Jamais vers l'hote externe : accueil du site, dans la langue demandee.
    get('/language/fr', ['referer' => 'https://evil.example/phish'])
        ->assertRedirect(route('fr.home'));

    // Meme chemin qu'une page publique, mais sur un autre hote : il n'est pas "traduit" pour autant.
    get('/language/es', ['referer' => 'https://evil.example/pricing'])
        ->assertRedirect(route('es.home'));
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

it('accepts Resend as a production mail transport and can build it (native SDK installed)', function () {
    cleanProductionConfig();
    config()->set('mail.default', 'resend');
    config()->set('services.resend.key', 're_test_dummy');

    // "resend" n'est pas un transport simule : la checklist prod l'accepte.
    expect(app(ProductionSafetyService::class)->violations())->toBe([]);
    // Et le SDK Resend est bien installe : le transport se construit.
    expect(Mail::mailer('resend')->getSymfonyTransport())->toBeInstanceOf(ResendTransport::class);
});

it('passes a clean production configuration', function () {
    cleanProductionConfig();

    expect(app(ProductionSafetyService::class)->violations())->toBe([]);
});

it('flags a production configuration without the keys of the generation, or with the measure switched off', function () {
    // SC9 : la commande de mise en ligne verifie aussi la generation d'articles et la mesure d'audience.
    cleanProductionConfig();
    config()->set('ai.providers.openrouter.key', '');
    config()->set('services.pixabay.key', null);
    config()->set('blog.ai.pipeline', 'simulated');
    config()->set('analytics.enabled', '1');

    $violations = implode("\n", app(ProductionSafetyService::class)->violations());

    expect($violations)->toContain('OPENROUTER_API_KEY')
        ->and($violations)->toContain('PIXABAY_API_KEY')
        ->and($violations)->toContain('BLOG_AI_PIPELINE')
        ->and($violations)->toContain('ANALYTICS_ENABLED');
});

it('flags a production configuration without a technical address, or with the log in one ever-growing file', function () {
    cleanProductionConfig();
    config()->set('festilaw.tech_alert_email', ' ');
    config()->set('logging.channels.stack.channels', ['single']);

    $violations = implode("\n", app(ProductionSafetyService::class)->violations());

    expect($violations)->toContain('FESTILAW_TECH_ALERT_EMAIL')
        ->and($violations)->toContain('LOG_STACK=daily');
});

it('fails the go-live check command when the configuration is unfit', function () {
    config()->set('payment.enabled', []); // Stripe non actif

    $this->artisan('festilaw:check-production')->assertFailed();
});

it('serves normally in production despite an unfit config, logging a non-blocking error for the morning report', function () {
    $this->app['env'] = 'production';
    Cache::flush(); // repartir d'un throttle vierge
    Log::spy();
    config()->set('payment.enabled', []); // Stripe non actif -> violation

    $response = app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), fn () => response('ok'));

    // Le site est servi (plus de blocage 503) ...
    expect($response->getContent())->toBe('ok');
    // ... et une erreur non bloquante est tracee : le rapport du matin la porte (audit du 23/11).
    Log::shouldHaveReceived('error')
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
    Log::shouldHaveReceived('error')->once();
});

it('serves normally in production when the configuration is clean, without warning', function () {
    $this->app['env'] = 'production';
    Cache::flush();
    Log::spy();
    cleanProductionConfig();

    $response = app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
    Log::shouldNotHaveReceived('error');
});

it('the production check does nothing outside production, even with an unfit config', function () {
    // Environnement de test (non-production) + config inapte : le garde ne doit PAS bloquer.
    config()->set('payment.enabled', []);

    $response = app(EnsureProductionIsConfigured::class)->handle(Request::create('/'), fn () => response('ok'));

    expect($response->getContent())->toBe('ok');
});
