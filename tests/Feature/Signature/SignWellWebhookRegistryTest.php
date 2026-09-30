<?php

use App\Repositories\SettingRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;

/*
 | festilaw:signwell-webhook (lancee par deploy.sh) : retrouve ou cree le webhook SignWell du site et
 | memorise son id, cle HMAC des evenements. Ne supprime jamais rien, jamais d'URL locale enregistree.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('signature.default', 'signwell');
    config()->set('signature.drivers.signwell', [
        'api_key' => 'testkey',
        'api_application_id' => null,
        'api_base_url' => 'https://www.signwell.com/api/v1',
        'test_mode' => false,
    ]);
    URL::forceRootUrl('https://festilaw.com');
    URL::forceScheme('https');
});

function storedWebhookIds(): ?array
{
    $raw = app(SettingRepository::class)->get('signwell.webhook_ids');

    return $raw === null ? null : json_decode($raw, true);
}

it('keeps the webhook already registered for the site and stores its id, creating nothing', function () {
    Http::fake(['*/api/v1/hooks' => Http::response([
        ['id' => 'aaaa1111-created-in-the-ui', 'callback_url' => 'https://festilaw.com/webhooks/signature'],
        ['id' => 'bbbb2222-another-site', 'callback_url' => 'https://another-site.example/webhooks/signature'],
    ])]);

    $this->artisan('festilaw:signwell-webhook')
        ->expectsOutputToContain('Webhook SignWell trouve')
        ->doesntExpectOutputToContain('aaaa1111-created-in-the-ui') // id jamais affiche en entier
        ->assertOk();

    expect(storedWebhookIds())->toBe(['aaaa1111-created-in-the-ui']);
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST');
});

it('creates the webhook when none calls the site, then stores its id', function () {
    Http::fake([
        'https://www.signwell.com/api/v1/hooks' => Http::sequence()
            ->push([['id' => 'bbbb2222-another-site', 'callback_url' => 'https://another-site.example/webhooks/signature']])
            ->push(['id' => 'cccc3333-new-hook', 'callback_url' => 'https://festilaw.com/webhooks/signature'], 201),
    ]);

    $this->artisan('festilaw:signwell-webhook')
        ->expectsOutputToContain('Webhook SignWell cree')
        ->assertOk();

    expect(storedWebhookIds())->toBe(['cccc3333-new-hook']);
    Http::assertSent(fn (ClientRequest $request): bool => $request->method() === 'POST'
        && $request['callback_url'] === 'https://festilaw.com/webhooks/signature'
        && $request->hasHeader('X-Api-Key', 'testkey'));
});

it('only reports on a dry run: nothing created, nothing stored', function () {
    Http::fake(['*/api/v1/hooks' => Http::response([])]);

    $this->artisan('festilaw:signwell-webhook', ['--dry' => true])
        ->expectsOutputToContain('il serait cree')
        ->assertOk();

    expect(storedWebhookIds())->toBeNull();
    Http::assertNotSent(fn (ClientRequest $request): bool => $request->method() === 'POST');
});

it('recreates the webhook: the new one first, then only the old ones of the site are deleted', function () {
    Http::fake([
        'https://www.signwell.com/api/v1/hooks' => Http::sequence()
            ->push([
                ['id' => 'aaaa1111-silenced-hook', 'callback_url' => 'https://festilaw.com/webhooks/signature'],
                ['id' => 'bbbb2222-another-site', 'callback_url' => 'https://another-site.example/webhooks/signature'],
            ])
            ->push(['id' => 'dddd4444-fresh-hook', 'callback_url' => 'https://festilaw.com/webhooks/signature'], 201),
        'https://www.signwell.com/api/v1/hooks/*' => Http::response(null, 204),
    ]);

    $this->artisan('festilaw:signwell-webhook', ['--recreate' => true])
        ->expectsOutputToContain('Webhook SignWell recree')
        ->doesntExpectOutputToContain('dddd4444-fresh-hook')
        ->assertOk();

    expect(storedWebhookIds())->toBe(['dddd4444-fresh-hook']);

    $methods = collect(Http::recorded())->map(fn (array $pair): string => $pair[0]->method().' '.parse_url($pair[0]->url(), PHP_URL_PATH))->all();
    expect($methods)->toBe([
        'GET /api/v1/hooks',
        'POST /api/v1/hooks',                                  // le neuf d'abord...
        'DELETE /api/v1/hooks/aaaa1111-silenced-hook',         // ...puis l'ancien du site, et lui seul
    ]);
});

it('only reports what a recreate would do on a dry run', function () {
    Http::fake(['*/api/v1/hooks' => Http::response([['id' => 'aaaa1111-silenced-hook', 'callback_url' => 'https://festilaw.com/webhooks/signature']])]);

    $this->artisan('festilaw:signwell-webhook', ['--recreate' => true, '--dry' => true])
        ->expectsOutputToContain('DRY-RUN')
        ->assertOk();

    expect(storedWebhookIds())->toBeNull();
    Http::assertSentCount(1); // la seule lecture de la liste
});

it('never registers a local development URL on a SignWell account', function (string $root) {
    URL::forceRootUrl($root);
    URL::forceScheme((string) parse_url($root, PHP_URL_SCHEME));
    Http::fake();

    $this->artisan('festilaw:signwell-webhook')
        ->expectsOutputToContain('non publique')
        ->assertFailed();

    Http::assertNothingSent();
    expect(storedWebhookIds())->toBeNull();
})->with(['https://festilaw.test', 'http://festilaw.com', 'https://localhost']);

it('does nothing when SignWell is not configured', function () {
    config()->set('signature.drivers.signwell.api_key', null);
    Http::fake();

    $this->artisan('festilaw:signwell-webhook')->assertOk();

    Http::assertNothingSent();
});

it('reports a SignWell outage without failing silently', function () {
    Http::fake(['*/api/v1/hooks' => Http::response(['error' => 'down'], 500)]);

    $this->artisan('festilaw:signwell-webhook')
        ->expectsOutputToContain('impossible')
        ->assertFailed();

    expect(storedWebhookIds())->toBeNull();
});
