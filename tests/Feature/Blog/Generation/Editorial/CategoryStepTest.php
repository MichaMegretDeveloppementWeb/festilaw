<?php

use App\Ai\Agents\Blog\CategoryMatchingAgent;
use App\Ai\Agents\Blog\CategoryNamingAgent;
use App\Exceptions\Blog\BlogEditorialException;
use App\Services\Blog\Generation\Editorial\Steps\CategoryStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\BlogContent;
use Tests\Support\EditorialFixtures as Fixtures;

/*
 | Le choix de la categorie, en deux temps, une fois l'article anglais termine (proposition de Micha du 27/11) :
 | le sujet nomme sans voir les categories du blog, puis compare aux categories existantes. Agents simules par le
 | SDK, aucun appel reel.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
});

/** Les sorties dont l'etape part : la redaction (sans categorie) et l'article final de la relecture du style. */
function categoryContext(array $outputs = []): mixed
{
    return Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput(), ...$outputs]);
}

function namedSubject(string $name): void
{
    CategoryNamingAgent::fake([['name' => $name, 'reason' => 'The article explains it.']])->preventStrayPrompts();
}

it('names the subject without seeing the categories, and creates it when the blog has none yet, without comparing', function () {
    namedSubject('  "online   marketplaces".');
    CategoryMatchingAgent::fake()->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBeNull()
        ->and($result->output['new_category'])->toBe('Online marketplaces')
        ->and($result->output['proposed_name'])->toBe('Online marketplaces')
        ->and($result->summary)->toBe('L\'IA a nommé le sujet de l\'article « Online marketplaces », sans voir les catégories du blog. Le blog n\'a encore aucune catégorie. Celle-ci sera créée avec le brouillon, nommée dans les trois langues.');
    CategoryNamingAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Adding an economic operator on Etsy') && $prompt->contains('The details Etsy asks for'));
    CategoryMatchingAgent::assertNeverPrompted();
});

it('never shows the categories of the blog when it names the subject', function () {
    BlogContent::category('labelling');
    namedSubject('Marketplaces');
    CategoryMatchingAgent::fake([['category_id' => null, 'reason' => 'None.']])->preventStrayPrompts();

    app(CategoryStep::class)->execute(categoryContext());

    CategoryNamingAgent::assertPrompted(fn ($prompt): bool => ! $prompt->contains('Labelling en') && ! $prompt->contains('Categories'));
});

it('takes back the category that already bears the name, without comparing', function () {
    $category = BlogContent::category('labelling');
    namedSubject('LABELLING EN');
    CategoryMatchingAgent::fake()->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBe($category->id)
        ->and($result->output['new_category'])->toBeNull()
        ->and($result->summary)->toEndWith('La catégorie « Labelling fr » porte déjà ce nom en anglais, et l\'article y est rangé.');
    CategoryMatchingAgent::assertNeverPrompted();
});

it('files the article in a category of the same subject under another name, judged on the titles it holds', function () {
    $labels = BlogContent::category('labels');
    $other = BlogContent::category('toys', position: 1);
    foreach (['first', 'second', 'third', 'fourth'] as $key) {
        BlogContent::published($key, $labels);
    }
    BlogContent::draft('toy', $other);
    namedSubject('Labelling');
    CategoryMatchingAgent::fake([['category_id' => $labels->id, 'reason' => 'Same subject.']])->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBe($labels->id)
        ->and($result->output['new_category'])->toBeNull()
        ->and($result->output['proposed_name'])->toBe('Labelling')
        ->and($result->output['reasons'])->toBe(['naming' => 'The article explains it.', 'matching' => 'Same subject.'])
        ->and($result->summary)->toBe('L\'IA a nommé le sujet de l\'article « Labelling », sans voir les catégories du blog. La catégorie « Labels fr » traite le même sujet, et l\'article y est rangé.');
    // Chaque categorie avec les titres de ses trois derniers articles, quel que soit leur statut.
    CategoryMatchingAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Labelling')
        && $prompt->contains('"latest_articles": [
            "Fourth en",
            "Third en",
            "Second en"
        ]') && ! $prompt->contains('First en')
        && $prompt->contains('Toy en')
        && ! $prompt->contains('No new category can be created'));
});

it('creates the category named when no category has its subject', function () {
    BlogContent::category('labelling');
    namedSubject('Technical documentation');
    CategoryMatchingAgent::fake([['category_id' => null, 'reason' => 'Another subject.']])->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBeNull()
        ->and($result->output['new_category'])->toBe('Technical documentation')
        ->and($result->summary)->toEndWith('Aucune catégorie existante ne traite ce sujet. Celle-ci sera créée avec le brouillon, nommée dans les trois langues.');
});

it('takes an unknown category as no category of the same subject', function () {
    BlogContent::category('labelling');
    namedSubject('Technical documentation');
    CategoryMatchingAgent::fake([['category_id' => 9_999, 'reason' => 'Made up.']])->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBeNull()
        ->and($result->output['new_category'])->toBe('Technical documentation');
});

it('files the article in the closest category once the blog has as many as allowed, so that its menu does not crumble', function () {
    config()->set('blog.ai.max_categories', 1);
    $category = BlogContent::category('labelling');
    namedSubject('Technical documentation');
    CategoryMatchingAgent::fake([['category_id' => $category->id, 'reason' => 'Closest.']])->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBe($category->id)
        ->and($result->output['new_category'])->toBeNull()
        ->and($result->summary)->toEndWith('Le blog a atteint son nombre maximal de catégories, et l\'article est rangé dans la plus proche, « Labelling fr ».');
    CategoryMatchingAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('No new category can be created'));
});

it('leaves the article without a category once the blog has as many as allowed and none fits', function () {
    config()->set('blog.ai.max_categories', 1);
    BlogContent::category('labelling');
    namedSubject('Technical documentation');
    CategoryMatchingAgent::fake([['category_id' => null, 'reason' => 'None fits.']])->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBeNull()
        ->and($result->output['new_category'])->toBeNull()
        ->and($result->summary)->toEndWith('Aucune catégorie ne convient et le blog a atteint son nombre maximal de catégories. Vous en choisirez une avant de publier.');
});

it('leaves the article without a category when no subject is named, without comparing', function () {
    BlogContent::category('labelling');
    namedSubject(' ". ');
    CategoryMatchingAgent::fake()->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext());

    expect($result->output['category_id'])->toBeNull()
        ->and($result->output['new_category'])->toBeNull()
        ->and($result->summary)->toBe('L\'IA n\'a pas su nommer le sujet de l\'article. Aucune catégorie n\'a été choisie, et vous en choisirez une avant de publier.');
    CategoryMatchingAgent::assertNeverPrompted();
});

it('keeps the category chosen by the writing of a generation written before this step, without any call', function () {
    // Sa version francaise a pu deja traduire le nom propose par la redaction.
    CategoryNamingAgent::fake()->preventStrayPrompts();
    CategoryMatchingAgent::fake()->preventStrayPrompts();

    $result = app(CategoryStep::class)->execute(categoryContext(['writing' => [...Fixtures::writingOutput(), 'category_id' => null, 'new_category' => 'Product safety']]));

    expect($result->output['category_id'])->toBeNull()
        ->and($result->output['new_category'])->toBe('Product safety')
        ->and($result->usage->costMicrousd)->toBe(0);
    CategoryNamingAgent::assertNeverPrompted();
    CategoryMatchingAgent::assertNeverPrompted();
});

it('needs the finished English article', function () {
    CategoryNamingAgent::fake()->preventStrayPrompts();

    expect(fn () => app(CategoryStep::class)->execute(Fixtures::context(['writing' => Fixtures::writingOutput()])))
        ->toThrow(BlogEditorialException::class);
    CategoryNamingAgent::assertNeverPrompted();
});
