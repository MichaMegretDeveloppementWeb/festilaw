<?php

use App\Exceptions\Blog\BlogAiProviderException;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use Tests\Support\TestBlogAgent;

/*
 | Passage des etapes editoriales vers un modele : fournisseur et modele fixes, consommation relevee sur
 | tous les tours de l'agent, erreurs du fournisseur traduites pour le moteur. Agents simules par le SDK,
 | aucun appel reel.
 */

beforeEach(function () {
    Http::preventStrayRequests();
});

/** Une reponse brute d'OpenRouter qui porte son cout reel. */
function openRouterRaw(float $cost, int $input, int $output, int $searches = 0): Response
{
    return new Response(new Psr7Response(200, [], json_encode([
        'model' => 'anthropic/claude-sonnet-5.5',
        'usage' => [
            'prompt_tokens' => $input,
            'completion_tokens' => $output,
            'cost' => $cost,
            'server_tool_use_details' => ['web_search_requests' => $searches],
        ],
    ])));
}

it('calls the agent through the configured provider with the model of the settings', function () {
    TestBlogAgent::fake(['ok'])->preventStrayPrompts();

    $reply = app(BlogAgentCaller::class)->call('topic', new TestBlogAgent, 'Find a topic.', 'anthropic/claude-sonnet-5.5', 90);

    expect($reply->text())->toBe('ok');
    TestBlogAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Find a topic.')
        && $prompt->provider->name() === 'openrouter'
        && $prompt->model === 'anthropic/claude-sonnet-5.5');
});

it('reads the real cost and the web searches of a call from the raw response', function () {
    TestBlogAgent::fake([
        (new TextResponse('done', new TextUsage(1_200, 300), new Meta('openrouter', 'anthropic/claude-sonnet-5.5')))
            ->withRawResponse(openRouterRaw(0.0123, 1_200, 300, searches: 2)),
    ])->preventStrayPrompts();

    $usage = app(BlogAgentCaller::class)->call('research', new TestBlogAgent, 'Research.', 'anthropic/claude-sonnet-5.5', 90)->usage;

    expect($usage->costMicrousd)->toBe(12_300)
        ->and($usage->costIsEstimated)->toBeFalse()
        ->and($usage->inputTokens)->toBe(1_200)
        ->and($usage->outputTokens)->toBe(300)
        ->and($usage->webSearches)->toBe(2)
        ->and($usage->model)->toBe('anthropic/claude-sonnet-5.5');
});

it('adds up every turn of an agent that used a tool, and estimates a turn whose raw response is missing', function () {
    TestBlogAgent::fake([
        new ToolCall('call-1', 'ping', []),
        (new TextResponse('done', new TextUsage(800, 200), new Meta('openrouter', 'anthropic/claude-sonnet-5.5')))
            ->withRawResponse(openRouterRaw(0.004, 800, 200)),
    ])->preventStrayPrompts();

    $usage = app(BlogAgentCaller::class)->call('writing', new TestBlogAgent, 'Write.', 'anthropic/claude-sonnet-5.5', 90)->usage;

    // Le tour de l'outil n'a pas de reponse brute : son cout (nul ici) est estime, et le total le dit.
    expect($usage->costMicrousd)->toBe(4_000)
        ->and($usage->costIsEstimated)->toBeTrue()
        ->and($usage->inputTokens)->toBe(800);
});

it('hands back the structured output and the pages the model cited, without duplicates', function () {
    $meta = new Meta('openrouter', 'm', collect([
        new UrlCitation('https://commission.europa.eu/gpsr', 'GPSR'),
        new UrlCitation('https://commission.europa.eu/gpsr', 'GPSR again'),
        new UrlCitation('https://help.etsy.com/economic-operator', 'Etsy help'),
    ]));
    TestBlogAgent::fake([new StructuredTextResponse(['topic' => 'Labels'], '{"topic":"Labels"}', new TextUsage(10, 5), $meta)])->preventStrayPrompts();

    $reply = app(BlogAgentCaller::class)->call('topic', new TestBlogAgent, 'Topic.', 'm', 90);

    expect($reply->citations())->toBe([
        ['url' => 'https://commission.europa.eu/gpsr', 'title' => 'GPSR'],
        ['url' => 'https://help.etsy.com/economic-operator', 'title' => 'Etsy help'],
    ]);
});

it('reports an exhausted credit, which the engine does not retry', function (Throwable $failure) {
    TestBlogAgent::fake(fn () => throw $failure);

    expect(fn () => app(BlogAgentCaller::class)->call('topic', new TestBlogAgent, 'Topic.', 'm', 90))
        ->toThrow(fn (BlogAiProviderException $e) => expect($e->creditExhausted)->toBeTrue());
})->with([
    'no credit left (402)' => fn () => InsufficientCreditsException::forProvider('openrouter', 402),
    'key limit reached (403)' => fn () => new RequestException(new Response(new Psr7Response(403, [], '{"error":{"message":"Key limit exceeded"}}'))),
]);

it('reports any other provider error as a failed call of the step, which the engine retries', function () {
    TestBlogAgent::fake(fn () => throw new RuntimeException('Upstream timeout'));

    expect(fn () => app(BlogAgentCaller::class)->call('research', new TestBlogAgent, 'Research.', 'm', 90))
        ->toThrow(function (BlogAiProviderException $e): void {
            expect($e->creditExhausted)->toBeFalse()
                ->and($e->getMessage())->toContain('[research]')->toContain('Upstream timeout')
                ->and($e->usage)->not->toBeNull();
        });
});
