<?php

use App\Actions\Blog\Category\DeleteBlogCategoryAction;
use App\Actions\Blog\Category\SaveBlogCategoryAction;
use App\Data\Blog\BlogCategoryTranslationData;
use App\Data\Blog\SaveBlogCategoryData;
use App\Exceptions\Blog\BlogCategoryException;
use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogCategory;
use App\Models\BlogCategoryRetiredSlug;
use App\Models\BlogCategoryTranslation;
use App\Models\BlogPost;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/** @param array<string, array<string, mixed>> $overrides */
function blogCategoryInput(?int $categoryId = null, array $overrides = []): SaveBlogCategoryData
{
    $names = ['en' => 'GPSR regulation', 'fr' => 'Réglementation GPSR', 'es' => 'Normativa GPSR'];

    return new SaveBlogCategoryData($categoryId, array_map(
        fn (string $locale): BlogCategoryTranslationData => new BlogCategoryTranslationData(...array_merge(['name' => $names[$locale]], $overrides[$locale] ?? [])),
        array_combine(array_keys($names), array_keys($names)),
    ));
}

it('creates a category with a generated slug in each language, placed last', function () {
    $first = app(SaveBlogCategoryAction::class)->execute(blogCategoryInput());
    $second = app(SaveBlogCategoryAction::class)->execute(blogCategoryInput(overrides: [
        'en' => ['name' => 'Platforms'], 'fr' => ['name' => 'Plateformes'], 'es' => ['name' => 'Plataformas'],
    ]));

    expect($first->translations)->toHaveCount(3)
        ->and($first->translationFor('fr')->slug)->toBe('reglementation-gpsr')
        ->and($first->translationFor('es')->name)->toBe('Normativa GPSR')
        ->and($second->position)->toBe($first->position + 1);
});

it('requires a name in the three languages and creates nothing otherwise', function () {
    expect(fn () => app(SaveBlogCategoryAction::class)->execute(blogCategoryInput(overrides: ['es' => ['name' => '   ']])))
        ->toThrow(fn (BlogCategoryException $e) => expect($e->getUserMessage())->toContain('espagnol'));

    expect(BlogCategory::query()->count())->toBe(0);
});

it('renames a category, keeps its other texts and redirects its old address', function () {
    $category = app(SaveBlogCategoryAction::class)->execute(blogCategoryInput());

    $saved = app(SaveBlogCategoryAction::class)->execute(blogCategoryInput($category->id, [
        'fr' => ['name' => 'Règles GPSR', 'slug' => 'Règles GPSR', 'description' => '  Tout sur   le règlement. ', 'metaTitle' => 'Règles GPSR · Festilaw'],
        'en' => ['slug' => 'gpsr-regulation'],
        'es' => ['slug' => 'normativa-gpsr'],
    ]));

    $french = $saved->translationFor('fr');

    expect($french->slug)->toBe('regles-gpsr')
        ->and($french->description)->toBe('Tout sur le règlement.')
        ->and($french->meta_title)->toBe('Règles GPSR · Festilaw')
        ->and($saved->translationFor('en')->slug)->toBe('gpsr-regulation')
        ->and(BlogCategoryRetiredSlug::query()->get(['locale', 'slug'])->map->only(['locale', 'slug'])->all())
        ->toBe([['locale' => 'fr', 'slug' => 'reglementation-gpsr']])
        ->and(BlogCategoryTranslation::query()->count())->toBe(3);
});

it('refuses an address already used by another category and changes nothing', function () {
    app(SaveBlogCategoryAction::class)->execute(blogCategoryInput());
    $other = app(SaveBlogCategoryAction::class)->execute(blogCategoryInput(overrides: [
        'en' => ['name' => 'Platforms'], 'fr' => ['name' => 'Plateformes'], 'es' => ['name' => 'Plataformas'],
    ]));

    expect(fn () => app(SaveBlogCategoryAction::class)->execute(blogCategoryInput($other->id, [
        'en' => ['name' => 'Marketplaces'],
        'fr' => ['name' => 'Places de marché', 'slug' => 'reglementation-gpsr'],
        'es' => ['name' => 'Plataformas'],
    ])))->toThrow(BlogSlugException::class);

    expect($other->translations()->where('locale', 'en')->value('name'))->toBe('Platforms');
});

it('reports a category that no longer exists', function () {
    expect(fn () => app(SaveBlogCategoryAction::class)->execute(blogCategoryInput(999)))
        ->toThrow(BlogCategoryException::class, 'not found');
});

it('deletes an empty category with its texts and old addresses', function () {
    $category = app(SaveBlogCategoryAction::class)->execute(blogCategoryInput());
    BlogCategoryRetiredSlug::factory()->create(['blog_category_id' => $category->id]);

    expect(app(DeleteBlogCategoryAction::class)->execute($category->id))->toBeTrue()
        ->and(BlogCategory::query()->count())->toBe(0)
        ->and(BlogCategoryTranslation::query()->count())->toBe(0)
        ->and(BlogCategoryRetiredSlug::query()->count())->toBe(0)
        ->and(app(DeleteBlogCategoryAction::class)->execute($category->id))->toBeFalse();
});

it('refuses to delete a category that still holds posts, drafts included, and says how many', function () {
    $category = app(SaveBlogCategoryAction::class)->execute(blogCategoryInput());
    BlogPost::factory()->count(2)->create(['blog_category_id' => $category->id]);

    expect(fn () => app(DeleteBlogCategoryAction::class)->execute($category->id))
        ->toThrow(fn (BlogCategoryException $e) => expect($e->getUserMessage())->toContain('2 articles'));

    expect(BlogCategory::query()->count())->toBe(1);
});
