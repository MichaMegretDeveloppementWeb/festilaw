<?php

use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogPostTranslation;
use App\Repositories\Blog\BlogTopicHistoryRepository;
use App\Services\Blog\Generation\Editorial\BlogInternalLinkResolver;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Tests\Support\BlogContent;

/*
 | Fondations du pipeline editorial : consignes lues dans leurs fichiers, liens internes traduits dans la
 | langue de chaque version, historique des sujets pour eviter les doublons.
 */

uses(RefreshDatabase::class);

it('reads the shared instructions of the agents from their versioned files', function (string $name) {
    expect(app(BlogPromptLibrary::class)->get($name))->not->toBeEmpty();
})->with(['editorial-guidelines', 'writing-voice-en', 'writing-voice-fr', 'writing-voice-es', 'lexicon-fr', 'lexicon-es', 'signals-en', 'signals-fr', 'signals-es']);

it('states the decisions of 16/11 in the editorial guidelines, and forbids invented material in the voice rules', function () {
    $library = app(BlogPromptLibrary::class);

    expect($library->get('editorial-guidelines'))
        ->toContain('End on what the seller does next')
        ->toContain('Never end with a paragraph that repeats')
        ->toContain('not a law firm')
        ->and($library->get('writing-voice-en'))->toContain('Never invent an anecdote, a testimony');
});

it('keeps the notes for maintainers out of what the model reads, fills values and reads lists', function () {
    $directory = storage_path('framework/testing/prompts');
    File::ensureDirectoryExists($directory);
    File::put($directory.'/sample.md', "<!-- source: a note for maintainers -->\nWrite about {{topic}} for {{reader}}.");
    File::put($directory.'/words.md', "# A list\ncrucial\n\n  delve  \n");
    $library = new BlogPromptLibrary($directory);

    expect($library->get('sample'))->toBe('Write about {{topic}} for {{reader}}.')
        ->and($library->render('sample', ['topic' => 'Etsy labels', 'reader' => 'UK sellers']))->toBe('Write about Etsy labels for UK sellers.')
        ->and($library->lines('words'))->toBe(['crucial', 'delve']);

    File::deleteDirectory($directory);
});

it('stops with a clear error when an instruction file is missing, or when a name tries to leave the folder', function (string $name) {
    expect(fn () => app(BlogPromptLibrary::class)->get($name))
        ->toThrow(BlogGenerationException::class, "prompt [{$name}] is missing");
})->with(['does-not-exist', '../../.env', 'Editorial-Guidelines']);

it('turns internal link tokens into the address of each language, and drops a link that leads nowhere', function () {
    $markdown = 'Check [your case](festilaw:page/quiz), [our prices](festilaw:page/pricing), [this guide](festilaw:post/7), [a ghost](festilaw:post/99) and [a page](festilaw:page/unknown).';
    $slugs = [7 => ['en' => 'etsy-labels', 'fr' => 'etiquettes-etsy', 'es' => 'etiquetas-etsy']];
    $resolver = app(BlogInternalLinkResolver::class);

    expect($resolver->resolve($markdown, 'en', $slugs))
        ->toBe('Check [your case](/#quiz), [our prices](/pricing), [this guide](/blog/etsy-labels), a ghost and a page.')
        ->and($resolver->resolve($markdown, 'fr', $slugs))
        ->toBe('Check [your case](/fr#quiz), [our prices](/fr/pricing), [this guide](/fr/blog/etiquettes-etsy), a ghost and a page.')
        ->and($resolver->resolve($markdown, 'es', $slugs))
        ->toContain('[this guide](/es/blog/etiquetas-etsy)');
});

it('lists every known title for the topic step, in two queries, english first and rejected topics included', function () {
    BlogContent::published('label');
    $french = BlogContent::draft('bougies');
    BlogPostTranslation::query()->where('blog_post_id', $french->id)->whereIn('locale', ['en', 'es'])->update(['title' => null, 'excerpt' => null]);
    BlogGeneration::factory()->create(['topic_title' => 'Toy safety on Amazon']);
    BlogGeneration::factory()->create(['topic_title' => 'Candle warnings', 'topic_rejected_at' => now()]);
    BlogGeneration::factory()->create(['topic_title' => null]);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $history = app(BlogTopicHistoryRepository::class)->load();
    expect(count(DB::getQueryLog()))->toBe(2);
    DB::disableQueryLog();

    expect(collect($history->posts)->pluck('title')->sort()->values()->all())->toBe(['Bougies fr', 'Label en'])
        ->and(collect($history->proposedTopics)->sortBy('title')->values()->all())->toBe([
            ['title' => 'Candle warnings', 'rejected' => true],
            ['title' => 'Toy safety on Amazon', 'rejected' => false],
        ])
        ->and($history->titles())->toHaveCount(4);
});
