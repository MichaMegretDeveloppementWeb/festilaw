<?php

use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\TopicAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Data\Blog\Generation\BlogAgentReply;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Services\Blog\Generation\BlogEffortLevels;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Files\Image;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Responses\Data\UrlCitation;
use Laravel\Ai\Responses\StructuredTextResponse;
use Laravel\Ai\Responses\TextResponse;
use Tests\Support\BlogImageFiles;
use Tests\Support\EditorialFixtures;
use Tests\Support\EditorialFixtures as Fixtures;
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

/** Une reponse de chat d'OpenRouter, telle que le SDK la lit sur le reseau. */
function openRouterCompletion(string $content, string $finishReason = 'stop'): array
{
    return [
        'id' => 'gen-test-1',
        'provider' => 'Anthropic',
        'model' => 'anthropic/claude-haiku-5.5',
        'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $content], 'finish_reason' => $finishReason]],
        'usage' => ['prompt_tokens' => 1_200, 'completion_tokens' => 300, 'cost' => 0.0123, 'completion_tokens_details' => ['reasoning_tokens' => 40]],
    ];
}

it('asks for the reasoning effort of the step and the cheapest provider, and for a structured answer only a provider that enforces the format', function () {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::sequence()
        ->push(openRouterCompletion((string) json_encode(Fixtures::topic())))
        ->push(openRouterCompletion('Notes.'))]);

    app(BlogAgentCaller::class)->call('topic', new TopicAgent('Choose.'), 'Topic.', 'anthropic/claude-haiku-5.5', 90);
    app(BlogAgentCaller::class)->call('research_notes', new WebSearchAgent('Search.', 3, config('blog.ai.web_search')), 'Search.', 'anthropic/claude-haiku-5.5', 90);

    $sent = Http::recorded()->map(fn (array $pair) => $pair[0]->data())->values();
    // Au niveau d'effort Moyen, le choix du sujet reflechit un cran en dessous des autres etapes. Au 17/11, rien ne
    // demandait le fournisseur le moins cher.
    expect($sent[0]['reasoning'])->toBe(['effort' => 'medium'])
        ->and($sent[0]['provider'])->toBe(['sort' => 'price', 'require_parameters' => true])
        ->and($sent[1]['reasoning'])->toBe(['effort' => 'high'])
        ->and($sent[1]['provider'])->toBe(['sort' => 'price'])
        ->and($sent[1]['tools'][0]['type'])->toBe('openrouter:web_search')
        ->and($sent[1]['tools'][0]['parameters'])->toBe(['max_uses' => 3, 'engine' => 'exa', 'max_results' => 5, 'max_characters' => 1_500, 'max_total_results' => 15]);
});

it('asks for the reasoning of the effort level, and the lowest effort a model accepts when it may refuse none', function () {
    // Regles de Micha du 19/11 et du 20/11 : baisser d'un niveau baisse la reflexion de chaque etape, monter la
    // monte, et le choix du sujet comme le dossier restent un cran en dessous, sauf au niveau Eleve.
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::response(openRouterCompletion('Text.'))]);
    $caller = app(BlogAgentCaller::class);

    $caller->call('writing', new TestBlogAgent, 'Write.', 'openai/gpt-6-luna', 90, effort: BlogEffortLevel::Low);
    $caller->call('topic', new TestBlogAgent, 'Topic.', 'anthropic/claude-haiku-5.5', 90, effort: BlogEffortLevel::Low);
    $caller->call('writing', new TestBlogAgent, 'Write.', 'openai/gpt-6-luna', 90, effort: BlogEffortLevel::Medium);
    $caller->call('topic', new TestBlogAgent, 'Topic.', 'anthropic/claude-haiku-5.5', 90, effort: BlogEffortLevel::Medium);
    $caller->call('writing', new TestBlogAgent, 'Write.', 'openai/gpt-6-luna', 90, effort: BlogEffortLevel::High);
    $caller->call('topic', new TestBlogAgent, 'Topic.', 'anthropic/claude-haiku-5.5', 90, effort: BlogEffortLevel::High);
    $caller->call('photo_check', new TestBlogAgent, 'Photo.', 'anthropic/claude-haiku-5.5', 90, effort: BlogEffortLevel::Low);
    $caller->call('photo_check', new TestBlogAgent, 'Photo.', 'anthropic/claude-haiku-5.5', 90, effort: BlogEffortLevel::High);

    // Aucun niveau ne demande plus l'absence de reflexion. Un reglage qui la demanderait l'obtient, sauf pour un
    // modele qui pourrait la refuser (DeepSeek), qui recoit le plus petit effort qu'il accepte.
    config(['blog.ai.effort.levels.low.reasoning.topic' => 'none']);
    app()->forgetInstance(BlogEffortLevels::class);
    app()->forgetInstance(BlogAgentCaller::class);
    $caller = app(BlogAgentCaller::class);
    $caller->call('topic', new TestBlogAgent, 'Topic.', 'anthropic/claude-haiku-5.5', 90, effort: BlogEffortLevel::Low);
    $caller->call('topic', new TestBlogAgent, 'Topic.', 'deepseek/deepseek-v4.1-flash', 90, effort: BlogEffortLevel::Low);

    expect(Http::recorded()->map(fn (array $pair): mixed => $pair[0]->data()['reasoning'] ?? null)->values()->all())->toBe([
        ['effort' => 'medium'],
        ['effort' => 'low'],
        ['effort' => 'high'],
        ['effort' => 'medium'],
        ['effort' => 'high'],
        ['effort' => 'high'],
        // Le controle des photos reste un cran en dessous, meme au niveau Eleve.
        ['effort' => 'low'],
        ['effort' => 'medium'],
        ['effort' => 'none'],
        ['effort' => 'low'],
    ]);
});

it('sends an attached photo to the model, and counts it in what the call may cost', function () {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::response(openRouterCompletion('Text.'))]);
    $photo = Image::fromPath(BlogImageFiles::jpeg(640, 427));
    $reserved = static function (array $attachments): int {
        try {
            app(BlogAgentCaller::class)->call('photo_check', new TestBlogAgent, 'Photo.', 'anthropic/claude-haiku-5.5', 90, budgetLeftMicrousd: 0, attachments: $attachments);
        } catch (BlogAiProviderException $e) {
            preg_match('/up to (\d+) microusd/', $e->getMessage(), $matches);

            return (int) $matches[1];
        }

        return 0;
    };

    app(BlogAgentCaller::class)->call('photo_check', new TestBlogAgent, 'Photo.', 'anthropic/claude-haiku-5.5', 90, attachments: [$photo]);

    $content = Http::recorded()->first()[0]->data()['messages'][1]['content'] ?? [];
    expect(collect($content)->pluck('type')->all())->toContain('image_url')
        ->and($reserved([$photo]))->toBeGreaterThan($reserved([]));
});

it('writes one line per call to the log, with its tokens and cost but no text', function () {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::response(openRouterCompletion('Notes.'))]);
    Log::spy();

    app(BlogAgentCaller::class)->call('research_notes', new WebSearchAgent('Search.', 3), 'A secret prompt.', 'anthropic/claude-haiku-5.5', 90, generationId: 76);

    Log::shouldHaveReceived('info')->with('Blog AI call.', Mockery::on(fn (array $line): bool => $line['generation_id'] === 76
        && $line['step'] === 'research_notes'
        && $line['provider'] === 'Anthropic'
        && $line['openrouter_id'] === 'gen-test-1'
        && $line['finish_reason'] === 'stop'
        && $line['reasoning_tokens'] === 40
        && $line['cost_microusd'] === 12_300
        && ! str_contains((string) json_encode($line), 'A secret prompt.')))->once();
});

it('refuses a structured answer cut off at its length limit, and keeps what it cost', function () {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::response(openRouterCompletion('{"decision": "top', 'length'))]);

    expect(fn () => app(BlogAgentCaller::class)->call('topic', new TopicAgent('Choose.'), 'Topic.', 'anthropic/claude-haiku-5.5', 90))
        ->toThrow(function (BlogAiProviderException $e): void {
            expect($e->getMessage())->toContain('cut off at its limit of 4000 tokens')
                ->and($e->usage->costMicrousd)->toBe(12_300);
        });
});

it('refuses a structured answer that is not JSON, which the SDK hands back empty', function () {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::response(openRouterCompletion('Here is my choice of topic.'))]);

    expect(fn () => app(BlogAgentCaller::class)->call('topic', new TopicAgent('Choose.'), 'Topic.', 'anthropic/claude-haiku-5.5', 90))
        ->toThrow(BlogAiProviderException::class, 'not valid JSON');
});

it('keeps free notes cut off at their length limit, marked as such, since they were paid for', function () {
    config(['ai.providers.openrouter.key' => 'test-key']);
    Http::fake(['openrouter.ai/*' => Http::response(openRouterCompletion('Partial notes', 'length'))]);

    $reply = app(BlogAgentCaller::class)->call('research_notes', new WebSearchAgent('Search.', 3), 'Search.', 'anthropic/claude-haiku-5.5', 90);

    expect($reply->text())->toBe('Partial notes')
        ->and($reply->cutOff)->toBeTrue();
});

it('does not send a call whose maximum cost does not fit before the safety stop', function () {
    Http::fake();

    // Trois recherches a 0,007 $ depassent a elles seules les 0,01 $ qui restent.
    expect(fn () => app(BlogAgentCaller::class)->call('research_notes', new WebSearchAgent('Search.', 3, config('blog.ai.web_search')), 'Search.', 'anthropic/claude-haiku-5.5', 90, budgetLeftMicrousd: 10_000))
        ->toThrow(fn (BlogAiProviderException $e) => expect($e->overBudget)->toBeTrue()->and($e->getMessage())->toContain('not sent'));
    Http::assertNothingSent();
});

it('reserves the longest answer at the measured price of the model, and at one and a half times the catalog for a model never measured', function () {
    // Banc du 18/11 : la triple marge reservait 0,33 $ pour une verification de Terra qui en coute 0,016.
    config(['blog.ai.models' => [...config('blog.ai.models'), 'test/never-measured' => ['name' => 'Never measured', 'provider' => 'Test', 'input' => 100, 'output' => 500]]]);
    FactCheckAgent::fake([[...EditorialFixtures::article(), 'removed_claims' => [], 'report_fr' => 'x']])->preventStrayPrompts();
    $call = static fn (string $model): BlogAgentReply => app(BlogAgentCaller::class)->call('verification', new FactCheckAgent('Check.', 1_200), 'Text.', $model, 90, budgetLeftMicrousd: 150_000);

    // Terra, mesure au prix du catalogue : 20 000 jetons de reponse au plus, environ 0,10 $, sous les 0,15 $ restants.
    expect($call('openai/gpt-6.1-terra')->structured())->not->toBe([]);
    // Le meme prix sans mesure compte une fois et demie : environ 0,15 $, au-dela de ce qui reste.
    expect(fn () => $call('test/never-measured'))->toThrow(fn (BlogAiProviderException $e) => expect($e->overBudget)->toBeTrue());
});

it('reports any other provider error as a failed call of the step, which the engine retries', function () {
    TestBlogAgent::fake(fn () => throw new RuntimeException('Upstream timeout'));

    expect(fn () => app(BlogAgentCaller::class)->call('research', new TestBlogAgent, 'Research.', 'm', 90))
        ->toThrow(function (BlogAiProviderException $e): void {
            expect($e->creditExhausted)->toBeFalse()
                ->and($e->getMessage())->toContain('[research]')->toContain('Upstream timeout')
                ->and($e->usage)->not->toBeNull();
        });
});
