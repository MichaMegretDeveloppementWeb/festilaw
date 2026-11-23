<?php

use App\Exceptions\Blog\BlogGenerationException;
use App\Models\BlogGeneration;
use App\Models\BlogPostTranslation;
use App\Repositories\Blog\BlogTopicHistoryRepository;
use App\Services\Blog\Generation\Editorial\BlogInternalLinkResolver;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\BlogWritingSignalsInspector;
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
})->with(['festilaw-context', 'eu-product-rules', 'editorial-guidelines', 'step-adaptation-fr', 'step-adaptation-es', 'writing-voice-en', 'writing-voice-fr', 'writing-voice-es', 'lexicon-fr', 'lexicon-es', 'signals-en', 'signals-fr', 'signals-es']);

it('only lists signs whose patterns compile, so the check never breaks a generation', function (string $locale) {
    $inspector = app(BlogWritingSignalsInspector::class);
    [, $patterns] = $inspector->split(app(BlogPromptLibrary::class)->lines('signals-'.$locale));

    $broken = array_column(array_filter($patterns, static fn (array $pattern): bool => @preg_match($pattern['pattern'], 'A plain sentence.') === false), 'name');

    expect($broken)->toBe([]);
})->with(['en', 'fr', 'es']);

it('flags the signs of the first real draft and none in the edited examples of the voice rules', function () {
    $inspector = app(BlogWritingSignalsInspector::class);
    $signs = app(BlogPromptLibrary::class)->lines('signals-en');
    $voice = app(BlogPromptLibrary::class)->get('writing-voice-en');
    preg_match_all('~<generated>(.*?)</generated>~s', $voice, $generated);
    preg_match_all('~<edited>(.*?)</edited>~s', $voice, $edited);

    expect(count($inspector->inspect(implode("\n\n", $generated[1]), $signs)['flagged']))->toBeGreaterThanOrEqual(6)
        ->and($inspector->inspect(implode("\n\n", $edited[1]), $signs)['flagged'])->toBe([]);
});

it('states the decisions of 16/11 in the editorial guidelines, the limits of the company in its context, and forbids invented material', function () {
    $library = app(BlogPromptLibrary::class);

    expect($library->get('editorial-guidelines'))
        ->toContain('End on what the seller does next')
        ->toContain('Never end with a paragraph that repeats')
        ->and($library->get('festilaw-context'))
        ->toContain('Festilaw is not a law firm and does not give legal advice')
        ->toContain('Never write a price, a fee or a discount')
        ->toContain('EU Market Access & Compliance Advisor')
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

it('lists every known title for the topic step, in three queries, english first, rejected topics included and stopped ones left out', function () {
    BlogContent::published('label');
    $french = BlogContent::draft('bougies');
    BlogPostTranslation::query()->where('blog_post_id', $french->id)->whereIn('locale', ['en', 'es'])->update(['title' => null, 'excerpt' => null]);
    BlogGeneration::factory()->create(['topic_title' => 'Toy safety on Amazon']);
    BlogGeneration::factory()->create(['topic_title' => 'Candle warnings', 'topic_rejected_at' => now()]);
    BlogGeneration::factory()->create(['topic_title' => null]);
    // Arretee a la main, la generation libere son sujet ; en echec, elle le garde (elle peut etre relancee).
    BlogGeneration::factory()->cancelled()->create(['topic_title' => 'Product liability from December']);
    BlogGeneration::factory()->failed()->create(['topic_title' => 'Jewellery nickel limits']);

    DB::flushQueryLog();
    DB::enableQueryLog();
    $history = app(BlogTopicHistoryRepository::class)->load();
    // Les articles recents avec leur chapo, tous les titres, tous les sujets proposes (controle du 20/11).
    expect(count(DB::getQueryLog()))->toBe(3);
    DB::disableQueryLog();

    expect(collect($history->posts)->pluck('title')->sort()->values()->all())->toBe(['Bougies fr', 'Label en'])
        ->and(collect($history->proposedTopics)->sortBy('title')->values()->all())->toBe([
            ['title' => 'Candle warnings', 'rejected' => true],
            ['title' => 'Jewellery nickel limits', 'rejected' => false],
            ['title' => 'Toy safety on Amazon', 'rejected' => false],
        ])
        ->and($history->titles())->toHaveCount(5);
});

it('bounds what the topic step reads, but checks duplicates against every title and every refused topic', function () {
    // Controle du 20/11 : au-dela de 300 sujets, un sujet refuse pouvait revenir, et la demande au modele grandissait sans fin.
    BlogContent::seedPublished(450);
    BlogGeneration::factory()->create(['topic_title' => 'Oldest refused topic', 'topic_rejected_at' => now()]);
    BlogGeneration::factory()->count(250)->create(['topic_title' => 'Refused topic', 'topic_rejected_at' => now()]);

    $history = app(BlogTopicHistoryRepository::class)->load();

    expect($history->posts)->toHaveCount(BlogTopicHistoryRepository::RECENT_POSTS)
        ->and($history->posts[0]['excerpt'])->not->toBeNull()
        ->and($history->olderTitles)->toHaveCount(BlogTopicHistoryRepository::OLDER_TITLES)
        ->and($history->proposedTopics)->toHaveCount(BlogTopicHistoryRepository::PROPOSED_TOPICS)
        ->and($history->titles())->toHaveCount(450 + 251)
        ->and($history->titles())->toContain('Oldest refused topic');
});
