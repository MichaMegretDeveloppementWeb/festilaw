<?php

use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Models\Submission;
use App\Services\Payment\PaymentReturnService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Symfony's getter includes the { ... }i delimiters added by its setter.
    $this->originalTrustedHosts = array_map(static fn (string $pattern): string => substr($pattern, 1, -2), Request::getTrustedHosts());
    config(['app.url' => 'https://festilaw.com']);
    // TrustHosts is skipped in unit tests; mirror the production host policy explicitly.
    Request::setTrustedHosts(['^(.+\.)?festilaw\.com$']);
});

afterEach(function () {
    Request::setTrustedHosts($this->originalTrustedHosts);
    URL::forceRootUrl(config('app.url'));
    URL::forceScheme(parse_url(config('app.url'), PHP_URL_SCHEME));
});

it('keeps existing personal links usable after replacing only the www host', function (string $pack, string $route) {
    $submission = Submission::factory()->{$pack}()->create([
        'resume_token' => Str::random(48),
        'resume_expires_at' => now()->addDays(30),
    ]);
    $token = $submission->resume_token;
    URL::forceRootUrl('https://www.festilaw.com');
    URL::forceScheme('https');
    $oldUrl = route($route, ['dossier' => $token]);

    // Simulate the server redirect target; Pest does not execute .htaccess.
    URL::forceRootUrl('https://festilaw.com');
    get(str_replace('https://www.festilaw.com/', 'https://festilaw.com/', $oldUrl))->assertOk();
    expect($submission->fresh()->resume_token)->toBe($token);
})->with([
    'starter journey' => ['starter', 'get-started.starter.journey'],
    'client project' => ['starter', 'my-project'],
    'scale space' => ['scale', 'get-started.scale.space'],
]);

it('preserves relative payment signatures across the host redirect and still rejects tampering', function () {
    $submission = Submission::factory()->starter()->create();
    $payment = $submission->payments()->create([
        'type' => PaymentType::StarterSubscription,
        'amount_cents' => 1000,
        'currency' => 'EUR',
        'provider' => 'stripe',
        'status' => PaymentStatus::Pending,
    ]);
    URL::forceRootUrl('https://www.festilaw.com');
    URL::forceScheme('https');
    [$success, $cancel] = app(PaymentReturnService::class)->returnUrls($payment);

    URL::forceRootUrl('https://festilaw.com');
    $success = str_replace('https://www.festilaw.com/', 'https://festilaw.com/', $success);
    $cancel = str_replace('https://www.festilaw.com/', 'https://festilaw.com/', $cancel);
    get($success)->assertRedirect(route('get-started.starter.journey', ['dossier' => $submission->resume_token, 'payment_return' => 1]));
    get($cancel)->assertRedirect(route('get-started.starter.journey', ['dossier' => $submission->resume_token, 'payment_cancelled' => 1]));
    get(str_replace('status=success', 'status=cancelled', $success))->assertForbidden();
});

it('uses the bare production domain in canonical sitemap and organization data', function () {
    URL::forceRootUrl('https://festilaw.com');
    URL::forceScheme('https');
    $response = get('https://festilaw.com/pricing?source=audit')->assertOk();
    $response->assertSee('<link rel="canonical" href="https://festilaw.com/pricing">', false);
    preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $response->getContent(), $matches);
    $organization = json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR)['@graph'][0];
    expect($organization['legalName'])->toBe('Festilaw B.V.')
        ->and($organization['url'])->toBe('https://festilaw.com')
        ->and($organization['logo'])->toBe('https://festilaw.com/images/logo-festilaw-272.png')
        ->and($organization['identifier']['value'])->toBe('77058720')
        ->and($organization['vatID'])->toBe('NL860886761B01')
        ->and($organization['address']['streetAddress'])->toBe('Spoorstraat 30A');
    get('https://festilaw.com/sitemap.xml')->assertOk()
        ->assertSee('<loc>https://festilaw.com/pricing</loc>', false)
        ->assertDontSee('www.festilaw.com');
});
