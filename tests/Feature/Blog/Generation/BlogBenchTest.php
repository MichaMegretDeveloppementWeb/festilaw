<?php

use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\ResearchAgent;
use App\Ai\Agents\Blog\StyleAgent;
use App\Ai\Agents\Blog\TopicAgent;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Ai\Agents\Blog\VersionCheckAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Ai\Agents\Blog\WriterAgent;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Models\BlogGeneration;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Image;
use Tests\Support\BlogImageFiles;
use Tests\Support\EditorialFixtures as Fixtures;

/*
 | Banc d'essai des modeles (festilaw:blog-bench, SC7) : le pipeline rejoue sur un sujet et deux configurations,
 | avec des agents simules qui notent le modele de chaque appel. Rien ne doit etre cree dans le site.
 */

uses(RefreshDatabase::class);

const BENCH_DEEPSEEK = 'deepseek/deepseek-v4.1-flash';
const BENCH_HAIKU = 'anthropic/claude-haiku-5.5';

beforeEach(function () {
    Sleep::fake();
    Http::preventStrayRequests();
    Fixtures::publicHosts();
    config([
        'blog.ai.bench.topics' => ['etsy' => ['kind' => 'news', 'topic' => 'Adding an economic operator on Etsy']],
        'blog.ai.bench.configurations' => [
            'current' => ['name' => 'Haiku + DeepSeek', 'models' => ['writing' => BENCH_DEEPSEEK, 'verification' => BENCH_HAIKU, 'translation' => BENCH_DEEPSEEK]],
            'haiku' => ['name' => 'Haiku', 'models' => ['writing' => BENCH_HAIKU, 'verification' => BENCH_HAIKU, 'translation' => BENCH_HAIKU]],
        ],
    ]);
});

/**
 * Chaque agent repond comme pour un article qui va au bout, et note le modele de chaque appel.
 *
 * @param  Closure(string): void|null  $beforeWriting  appele avec le modele avant chaque reponse du redacteur (pour faire echouer un appel)
 * @return ArrayObject<string, list<string>> les modeles appeles, par agent
 */
function fakeBenchAgents(?Closure $beforeWriting = null): ArrayObject
{
    $calls = new ArrayObject;
    $answer = static fn (string $agent, mixed $response): Closure => static function ($prompt, $attachments, $provider, string $model) use ($calls, $agent, $response): mixed {
        $calls[$agent] = [...($calls[$agent] ?? []), $model];

        return $response instanceof Closure ? $response($model) : $response;
    };

    Storage::fake('local');
    Http::fake(['https://eur-lex.europa.eu/*' => Http::response('ok'), 'https://help.etsy.com/*' => Http::response('ok')]);
    Image::fake([base64_encode(file_get_contents(BlogImageFiles::png(1536, 1024)))])->preventStrayImages();
    TopicAgent::fake($answer('topic', [...Fixtures::topic(), 'report_fr' => 'Sujet impose.']))->preventStrayPrompts();
    WebSearchAgent::fake($answer('research_notes', Fixtures::notesCiting()))->preventStrayPrompts();
    ResearchAgent::fake($answer('research', [...Fixtures::research(), 'report_fr' => 'Deux sources officielles.']))->preventStrayPrompts();
    WriterAgent::fake($answer('writing', static function (string $model) use ($beforeWriting): array {
        $beforeWriting?->__invoke($model);

        return Fixtures::writerAnswer();
    }))->preventStrayPrompts();
    FactCheckAgent::fake($answer('verification', [...Fixtures::article(), 'removed_claims' => [['claim' => 'An unsupported date.', 'reason' => 'Not in the file.']], 'report_fr' => 'Une affirmation retiree.']))->preventStrayPrompts();
    StyleAgent::fake($answer('style', [...Fixtures::article(), 'report_fr' => 'Style relu.']))->preventStrayPrompts();
    VersionCheckAgent::fake($answer('version_check', ['corrections' => [], 'report_fr' => 'Version conforme.']))->preventStrayPrompts();
    TranslatorAgent::fake($answer('translation', [...Fixtures::article(['title' => 'Ajouter un opérateur économique sur Etsy']), 'image_alts' => [['number' => 0, 'alt' => 'Un colis']], 'report_fr' => 'Version.']))->preventStrayPrompts();

    return $calls;
}

/** @return array<string, mixed> */
function benchFile(string $path): array
{
    return json_decode(Storage::disk('local')->get("blog-bench/test/{$path}"), true);
}

it('writes every configuration from one shared research and one image, without creating anything in the site', function () {
    $calls = fakeBenchAgents();

    $this->artisan('festilaw:blog-bench', ['--run' => 'test'])->assertSuccessful();

    // La recherche et l'image ne se paient qu'une fois ; chaque configuration ecrit avec ses propres modeles.
    expect($calls['research_notes'])->toHaveCount(1)
        ->and($calls['research'])->toHaveCount(1)
        ->and(array_values(array_unique($calls['writing'])))->toBe([BENCH_DEEPSEEK, BENCH_HAIKU])
        ->and(array_values(array_unique($calls['translation'])))->toBe([BENCH_DEEPSEEK, BENCH_HAIKU])
        ->and(array_values(array_unique($calls['verification'])))->toBe([BENCH_HAIKU]);
    expect(BlogGeneration::query()->count() + BlogPost::query()->count() + BlogImage::query()->count())->toBe(0);
    Storage::disk('local')->assertExists(['blog-bench/test/topics/etsy.json', 'blog-bench/test/images/etsy.png']);
    expect(benchFile('topics/etsy.json')['outputs']['topic']['kind'])->toBe('news');
    expect(benchFile('articles/etsy--current.json'))
        ->configuration->toBe(['key' => 'current', 'name' => 'Haiku + DeepSeek', 'models' => ['writing' => BENCH_DEEPSEEK, 'verification' => BENCH_HAIKU, 'translation' => BENCH_DEEPSEEK]])
        ->image->generated->toBeTrue()
        ->outputs->toHaveKeys(['writing', 'verification', 'style', 'translation_fr', 'translation_es'])
        ->measures->sources->toMatchArray(['research' => 2, 'research_official' => 2, 'shown' => 2, 'shown_official' => 2])
        ->measures->removed_claims->toBe(1)
        ->measures->links->toBe(['external' => 1, 'internal' => 2]);
    expect(benchFile('articles/etsy--haiku.json')['image'])->toMatchArray(['generated' => false, 'reason' => 'shared']);
});

it('resumes a run without calling a model again for what is already written', function () {
    $calls = fakeBenchAgents();
    Storage::disk('local')->put('blog-bench/test/topics/etsy.json', json_encode(['cost_microusd' => 0, 'outputs' => []]));
    Storage::disk('local')->put('blog-bench/test/articles/etsy--current.json', json_encode(benchFileFixture()));
    Storage::disk('local')->put('blog-bench/test/articles/etsy--haiku.json', json_encode(benchFileFixture('haiku')));

    $this->artisan('festilaw:blog-bench', ['--run' => 'test'])->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([]);
});

it('goes on with the next configuration when an article fails, and writes nothing for it', function () {
    fakeBenchAgents(static function (string $model): void {
        if ($model === BENCH_DEEPSEEK) {
            throw new RuntimeException('provider down');
        }
    });

    $this->artisan('festilaw:blog-bench', ['--run' => 'test'])
        ->expectsOutputToContain('L\'étape writing n\'a pas abouti pour etsy / current.')
        ->assertFailed();

    Storage::disk('local')->assertMissing('blog-bench/test/articles/etsy--current.json');
    Storage::disk('local')->assertExists('blog-bench/test/articles/etsy--haiku.json');
});

it('stops the whole bench when the provider credit is exhausted', function () {
    $calls = fakeBenchAgents(static fn () => throw InsufficientCreditsException::forProvider('openrouter', 402));

    $this->artisan('festilaw:blog-bench', ['--run' => 'test'])
        ->expectsOutputToContain('Le crédit du fournisseur d\'IA est épuisé')
        ->assertFailed();

    // Aucun nouvel essai, et la seconde configuration n'est pas tentee.
    expect($calls['writing'])->toBe([BENCH_DEEPSEEK]);
});

it('reports a disk that cannot be written, and goes no further for the topic', function () {
    $calls = fakeBenchAgents();
    // Un fichier a la place du dossier du banc : l'ecriture echoue vraiment sur le disque.
    Storage::disk('local')->put('blog-bench', 'not a folder');

    $this->artisan('festilaw:blog-bench', ['--run' => 'test'])->assertFailed();

    expect($calls->getArrayCopy())->not->toHaveKey('writing');
});

it('gives the estimated cost without calling any model', function () {
    $calls = fakeBenchAgents();

    $this->artisan('festilaw:blog-bench', ['--dry' => true])
        ->expectsOutputToContain('Haiku + DeepSeek')
        ->expectsOutputToContain('Banc complet (1 sujet(s))')
        ->assertSuccessful();

    expect($calls->getArrayCopy())->toBe([]);
    Image::assertNothingGenerated();
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('splits the estimate into shared research and writing without losing a step', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $models = ['topic' => BENCH_HAIKU, 'research' => BENCH_HAIKU, 'writing' => BENCH_DEEPSEEK, 'verification' => BENCH_HAIKU, 'translation' => BENCH_DEEPSEEK];
    $none = new GenerationImageOptions(BlogAiImageMode::None, 1);

    $whole = $forecaster->forecast($models, 1_000, $none, true);
    $shared = $forecaster->forecast($models, 1_000, $none, true, ['topic', 'research_notes', 'research']);
    $writing = $forecaster->forecast($models, 1_000, $none, true, ['writing', 'verification', 'style', 'translation_fr', 'version_check_fr', 'translation_es', 'version_check_es']);

    expect([$shared->lowMicrousd + $writing->lowMicrousd, $shared->highMicrousd + $writing->highMicrousd])->toBe([$whole->lowMicrousd, $whole->highMicrousd])
        ->and($shared->lowMicrousd)->toBeGreaterThan(0)
        ->and($writing->lowMicrousd)->toBeGreaterThan(0);
});

it('refuses a topic or a configuration that the bench does not know', function () {
    $calls = fakeBenchAgents();

    $this->artisan('festilaw:blog-bench', ['--topic' => ['nope']])
        ->expectsOutputToContain('Sujet ou configuration inconnu : nope.')
        ->assertFailed();

    expect($calls->getArrayCopy())->toBe([]);
});

it('replays the verification of a written article with each model asked, one file per model, nothing created in the site', function () {
    seedWrittenBench();
    $models = new ArrayObject;
    FactCheckAgent::fake(function ($prompt, $attachments, $provider, string $model) use ($models): array {
        $models[] = $model;

        return [...Fixtures::article(), 'removed_claims' => [['claim' => 'An unsupported date.', 'reason' => 'Not in the file.']], 'report_fr' => 'x'];
    })->preventStrayPrompts();

    $this->artisan('festilaw:blog-bench-verify', ['run' => 'test', '--model' => ['openai/gpt-6-luna', 'anthropic/claude-haiku-5.5']])->assertSuccessful();

    expect($models->getArrayCopy())->toBe(['openai/gpt-6-luna', 'anthropic/claude-haiku-5.5'])
        ->and(BlogGeneration::query()->count())->toBe(0);
    expect(benchFile('verifications/etsy--current--openai_gpt-6-luna.json'))
        ->model->toBe('openai/gpt-6-luna')
        ->removed_claims->toHaveCount(1);
    Storage::disk('local')->assertExists('blog-bench/test/verifications/etsy--current--anthropic_claude-haiku-5.5.json');

    // Une reprise ne repaie pas une verification deja ecrite.
    $this->artisan('festilaw:blog-bench-verify', ['run' => 'test', '--model' => ['openai/gpt-6-luna']])->assertSuccessful();

    expect($models)->toHaveCount(2);
});

it('gives the cost of a verification bench without calling any model, and refuses an article the bench does not have', function () {
    seedWrittenBench();
    FactCheckAgent::fake()->preventStrayPrompts();

    $this->artisan('festilaw:blog-bench-verify', ['run' => 'test', '--model' => ['openai/gpt-6-luna'], '--dry' => true])
        ->expectsOutputToContain('1 article(s), 1 modele(s)')
        ->assertSuccessful();
    $this->artisan('festilaw:blog-bench-verify', ['run' => 'test', '--model' => ['openai/gpt-6-luna'], '--article' => ['nope--current']])
        ->expectsOutputToContain('Sujet ou configuration inconnu : nope--current.')
        ->assertFailed();

    FactCheckAgent::assertNeverPrompted();
});

/** Un banc deja joue, reduit a ce que la verification relit : le sujet prepare et le texte redige d'un article. */
function seedWrittenBench(): void
{
    Storage::fake('local');
    $topic = ['key' => 'etsy', 'kind' => 'news', 'topic' => 'Adding an economic operator on Etsy'];
    Storage::disk('local')->put('blog-bench/test/topics/etsy.json', json_encode(['topic' => $topic, 'cost_microusd' => 0, 'outputs' => ['topic' => Fixtures::topic(), 'research' => Fixtures::research()]]));
    Storage::disk('local')->put('blog-bench/test/articles/etsy--current.json', json_encode(['topic' => $topic, 'words_asked' => 1_000, 'outputs' => ['writing' => Fixtures::writingOutput()]]));
}

/**
 * Un article deja ecrit, reduit a ce que la commande relit.
 *
 * @return array<string, mixed>
 */
function benchFileFixture(string $configuration = 'current'): array
{
    return [
        'configuration' => ['key' => $configuration, 'name' => $configuration],
        'cost_microusd' => 1_000,
        'preparation_cost_microusd' => 500,
        'duration_ms' => 2_000,
        'measures' => ['words' => ['style' => 900], 'removed_claims' => 0],
    ];
}
