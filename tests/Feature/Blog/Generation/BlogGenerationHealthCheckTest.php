<?php

declare(strict_types=1);

use App\Livewire\Admin\Blog\GenerationDashboard;
use App\Models\BlogGeneration;
use App\Repositories\Blog\BlogGenerationHealthRepository;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Support\BlogAi;

/*
 | Verification quotidienne des services de la generation (controle du 20/11) : sans appel a un modele, elle
 | dit ce qui empecherait la generation de tourner des annees sans intervention, journalise chaque probleme en
 | erreur (le rapport du matin le reprend) et montre au tableau de bord qu'un probleme est signale.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    config()->set('blog.ai.pipeline', 'editorial');
    config()->set('ai.providers.openrouter.key', 'or-test-key');
    config()->set('services.pixabay.key', 'px-test-key');
    $this->travelTo('2026-11-21 05:45:00');
});

/**
 * Ce que repondent OpenRouter et Pixabay quand tout va bien : chaque modele du catalogue liste, propose au prix
 * du catalogue par un fournisseur qui accepte la sortie structuree et la reflexion, et qui lit les images.
 *
 * @return array<string, mixed>
 */
function healthyServices(): array
{
    $offers = [];

    foreach (config('blog.ai.models') as $modelId => $model) {
        $offers[$modelId] = ['input' => $model['input'], 'output' => $model['output'], 'params' => ['structured_outputs', 'reasoning'], 'image' => true];
    }

    return [
        'key' => [200, ['limit' => 30, 'limit_remaining' => 25]],
        'listed' => array_fill_keys(array_keys(config('blog.ai.models')), null),
        'images' => array_fill_keys(array_keys(config('blog.ai.image_models')), null),
        'offers' => $offers,
        'pixabay' => 200,
    ];
}

/** @param  array<string, mixed>  $services */
function fakeServices(array $services): void
{
    $list = static fn (array $models): array => ['data' => array_map(
        static fn (string $id, ?string $expires): array => ['id' => $id, 'expiration_date' => $expires],
        array_keys($models),
        array_values($models),
    )];

    Http::fake(function (Request $request) use ($services, $list) {
        $url = $request->url();

        if (str_starts_with($url, 'https://pixabay.com/api/')) {
            return Http::response(['hits' => []], $services['pixabay']);
        }

        if ($url === 'https://openrouter.ai/api/v1/key') {
            expect($request->hasHeader('Authorization', 'Bearer or-test-key'))->toBeTrue();

            return Http::response(['data' => $services['key'][1]], $services['key'][0]);
        }

        if ($url === 'https://openrouter.ai/api/v1/models?output_modalities=image') {
            return Http::response($list($services['images']));
        }

        if ($url === 'https://openrouter.ai/api/v1/models') {
            return Http::response($list($services['listed']), $services['listed_status'] ?? 200);
        }

        if (preg_match('~^https://openrouter\.ai/api/v1/models/(.+)/endpoints$~', $url, $match) === 1) {
            $offer = $services['offers'][$match[1]] ?? null;

            return $offer === null ? Http::response(['error' => ['code' => 404]], 404) : Http::response(['data' => [
                'id' => $match[1],
                'architecture' => ['input_modalities' => $offer['image'] ? ['text', 'image'] : ['text']],
                'endpoints' => [
                    ['provider_name' => 'Pricey', 'pricing' => ['prompt' => '0.001', 'completion' => '0.001'], 'supported_parameters' => ['reasoning']],
                    ['provider_name' => 'Cheapest', 'pricing' => ['prompt' => (string) ($offer['input'] / 100_000_000), 'completion' => (string) ($offer['output'] / 100_000_000)], 'supported_parameters' => $offer['params']],
                ],
            ]]);
        }

        return Http::response([], 500);
    });
}

/** @return list<string> */
function healthProblems(): array
{
    test()->artisan('festilaw:blog-health-check');

    return app(BlogGenerationHealthRepository::class)->latest()?->problems ?? [];
}

it('finds nothing to report when every service answers as expected', function () {
    fakeServices(healthyServices());
    Log::spy();

    $this->artisan('festilaw:blog-health-check')->assertOk();

    $health = app(BlogGenerationHealthRepository::class)->latest();
    expect($health->problems)->toBe([])
        ->and($health->checkedAt->toDateTimeString())->toBe('2026-11-21 05:45:00');
    Log::shouldNotHaveReceived('error');
});

it('reports a rejected key, and stops there for OpenRouter', function () {
    $services = healthyServices();
    $services['key'] = [401, ['error' => ['message' => 'No auth credentials found']]];
    fakeServices($services);

    expect(healthProblems())->toBe(['OpenRouter rejected the API key (HTTP 401).']);
});

it('reports a key nearing its limit', function () {
    $services = healthyServices();
    $services['key'] = [200, ['limit' => 30, 'limit_remaining' => 4.5]];
    fakeServices($services);

    expect(healthProblems())->toBe(['The OpenRouter key has 4.50 $ left of its 30.00 $ limit.']);
});

it('reports a chosen model that OpenRouter retired, offers without the needed parameters, and a topic model blind to images', function () {
    BlogAi::saveSettings(['models' => ['writing' => 'anthropic/claude-sonnet-5.5', 'topic' => 'deepseek/deepseek-v4.1-flash']]);
    $services = healthyServices();
    unset($services['offers']['openai/gpt-6.1-terra']);
    $services['offers']['anthropic/claude-sonnet-5.5']['params'] = ['reasoning'];
    $services['offers']['deepseek/deepseek-v4.1-flash']['image'] = false;
    fakeServices($services);

    expect(healthProblems())->toBe([
        'Model [deepseek/deepseek-v4.1-flash] chosen for [topic] does not read images, so stock photos cannot be checked.',
        'No OpenRouter provider of [anthropic/claude-sonnet-5.5] (chosen for [writing]) accepts structured outputs with reasoning.',
        'Model [openai/gpt-6.1-terra] chosen for [verification] is no longer offered by OpenRouter.',
    ]);
});

it('compares the catalog with the cheapest provider that accepts what the site sends, not with a pricier one', function () {
    $services = healthyServices();
    $services['offers']['openai/gpt-6-luna']['output'] = 70;
    fakeServices($services);

    expect(healthProblems())->toBe(['The cheapest provider of [openai/gpt-6-luna] now charges 10/70 cents per million tokens, the catalog (config/blog.php) says 10/50.']);
});

it('reports catalog models that OpenRouter no longer lists or will soon retire, and a chosen image model that left', function () {
    $services = healthyServices();
    unset($services['listed']['x-ai/grok-4.7'], $services['images']['openai/gpt-5.4-image-2']);
    $services['images']['google/gemini-3-pro-image'] = '2026-12-01';
    $services['listed']['google/gemini-4-carbon'] = '2027-06-01';
    fakeServices($services);

    expect(healthProblems())->toBe([
        'Image model [openai/gpt-5.4-image-2] chosen for the images is no longer offered by OpenRouter.',
        'Catalog model [x-ai/grok-4.7] is no longer listed by OpenRouter.',
        'Model [google/gemini-3-pro-image] of the catalog will be retired by OpenRouter on 2026-12-01.',
        'Catalog model [openai/gpt-5.4-image-2] is no longer listed by OpenRouter.',
    ]);
});

it('reports OpenRouter or Pixabay out of reach as a problem, never as a crash', function () {
    $services = healthyServices();
    $services['pixabay'] = 400;
    $services['listed_status'] = 503;
    fakeServices($services);

    expect(healthProblems())->toBe([
        'OpenRouter could not be checked: /models answered HTTP 503',
        'Blog image source failed: Pixabay answered HTTP 400',
    ]);
});

it('reports a queue that stopped moving', function () {
    fakeServices(healthyServices());
    BlogGeneration::factory()->create(['updated_at' => now()->subHours(3)]);

    expect(healthProblems())->toBe(['The blog generation queue has not moved since 2026-11-21 02:45:00 UTC (more than 120 minutes).']);
});

it('does not take a generation that works for a stuck queue', function () {
    fakeServices(healthyServices());
    BlogGeneration::factory()->create(['updated_at' => now()->subHours(3)]);
    BlogGeneration::factory()->running()->create(['updated_at' => now()->subMinutes(10)]);

    expect(healthProblems())->toBe([]);
});

it('reports the EU rules reference once its review date has passed, and shows it to the editor', function () {
    fakeServices(healthyServices());
    $this->travelTo('2027-11-20 05:45:00');

    expect(healthProblems())->toBe(['The EU rules reference (resources/prompts/blog/eu-product-rules.md, checked on 2026-11-19) was due for review on 2027-11-19.']);
    Livewire::test(GenerationDashboard::class)
        ->assertSee('La fiche des règles européennes est à relire')
        ->assertSee('Problème technique signalé à l\'assistance technique');
});

it('calls no outside service when the pipeline is the simulated one', function () {
    config()->set('blog.ai.pipeline', 'simulated');

    expect(healthProblems())->toBe([]);
    Http::assertNothingSent();
});

it('logs every problem as an error, so the morning report carries it', function () {
    $services = healthyServices();
    $services['pixabay'] = 400;
    fakeServices($services);
    Log::spy();

    $this->artisan('festilaw:blog-health-check')->assertFailed();

    Log::shouldHaveReceived('error')->once()->with('Blog generation health check: Blog image source failed: Pixabay answered HTTP 400');
});

it('shows on the dashboard when the services were last checked', function () {
    fakeServices(healthyServices());
    $this->artisan('festilaw:blog-health-check');

    Livewire::test(GenerationDashboard::class)
        ->assertSee('Services vérifiés le 21/11')
        ->assertDontSee('La fiche des règles européennes est à relire');
});

it('runs every morning just before the error report', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains((string) $event->command, 'festilaw:blog-health-check'));

    expect($event?->expression)->toBe('45 5 * * *');
});
