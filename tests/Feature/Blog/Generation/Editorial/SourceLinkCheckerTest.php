<?php

use App\Contracts\Blog\Generation\HostResolver;
use App\Services\Blog\Generation\Editorial\Sources\SourceLinkChecker;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/*
 | Test des liens de source : un lien mort sort de l'article, une adresse qui vise le reseau local ou le
 | serveur n'est jamais demandee. DNS et reseau simules.
 */

beforeEach(function () {
    Http::preventStrayRequests();

    app()->instance(HostResolver::class, new class implements HostResolver
    {
        public function resolve(string $host): array
        {
            return match ($host) {
                'intranet.example' => ['10.0.0.12'],
                'rebind.example' => ['93.184.216.34', '127.0.0.1'],
                'nowhere.example' => [],
                default => ['93.184.216.34'],
            };
        }
    });
});

it('keeps a live link and drops a link that is gone', function () {
    Http::fake([
        'https://commission.europa.eu/*' => Http::response('ok'),
        'https://help.etsy.com/*' => Http::response('', 404),
        'https://old.example/*' => Http::response('', 410),
    ]);

    expect(app(SourceLinkChecker::class)->check([
        'https://commission.europa.eu/gpsr',
        'https://help.etsy.com/article',
        'https://old.example/page',
    ]))->toBe([
        'https://commission.europa.eu/gpsr' => true,
        'https://help.etsy.com/article' => false,
        'https://old.example/page' => false,
    ]);
});

it('keeps a link that refuses robots or fails on the server side, which does not prove it is gone', function (int $status) {
    Http::fake(['*' => Http::response('', $status)]);

    expect(app(SourceLinkChecker::class)->check(['https://eur-lex.europa.eu/doc']))->toBe(['https://eur-lex.europa.eu/doc' => true]);
})->with([401, 403, 429, 503]);

it('drops a link that does not answer', function () {
    Http::fake(['*' => Http::failedConnection()]);

    expect(app(SourceLinkChecker::class)->check(['https://slow.example/page']))->toBe(['https://slow.example/page' => false]);
});

it('never requests an address that is not plain web, or that points to a local or private network', function (string $url) {
    Http::fake();

    expect(app(SourceLinkChecker::class)->check([$url]))->toBe([$url => false]);
    Http::assertNothingSent();
})->with([
    'ftp' => 'ftp://files.example/doc.pdf',
    'file' => 'file:///etc/passwd',
    'javascript' => 'javascript:alert(1)',
    'unusual port' => 'https://example.org:8443/admin',
    'credentials in the address' => 'https://user:secret@example.org/',
    'loopback address' => 'http://127.0.0.1/',
    'private address' => 'http://192.168.1.10/',
    'cloud metadata address' => 'http://169.254.169.254/latest/meta-data',
    'name of a private host' => 'https://intranet.example/',
    'name with one local address among others' => 'https://rebind.example/',
    'name that does not resolve' => 'https://nowhere.example/',
]);

it('follows a redirect to a public address, checking it like the first one', function () {
    Http::fake([
        'https://short.example/*' => Http::response('', 301, ['Location' => 'https://www.gov.uk/guidance']),
        'https://www.gov.uk/*' => Http::response('ok'),
    ]);

    expect(app(SourceLinkChecker::class)->check(['https://short.example/x']))->toBe(['https://short.example/x' => true]);
});

it('drops a link that redirects to a private address, without requesting it', function () {
    Http::fake([
        'https://short.example/*' => Http::response('', 302, ['Location' => 'http://intranet.example/secret']),
        '*' => Http::response('should not be requested'),
    ]);

    expect(app(SourceLinkChecker::class)->check(['https://short.example/x']))->toBe(['https://short.example/x' => false]);
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'intranet.example'));
});

it('drops a link that keeps redirecting', function () {
    Http::fake(['*' => Http::response('', 302, ['Location' => '/again'])]);

    expect(app(SourceLinkChecker::class)->check(['https://loop.example/start']))->toBe(['https://loop.example/start' => false]);
    Http::assertSentCount(4);
});
