<?php

use App\Exceptions\Blog\BlogSlugException;
use App\Models\BlogCategory;
use App\Models\BlogCategoryRetiredSlug;
use App\Models\BlogCategoryTranslation;
use App\Repositories\Blog\BlogCategorySlugRepository;
use App\Services\Blog\Slug\BlogCategorySlugAssigner;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function blogCategoryWithSlug(string $locale, string $slug): BlogCategory
{
    $category = BlogCategory::factory()->create();
    BlogCategoryTranslation::factory()->for($category, 'category')->create(['locale' => $locale, 'slug' => $slug]);

    return $category;
}

it('generates a category slug from its name and numbers it when it is taken', function () {
    blogCategoryWithSlug('fr', 'reglementation-gpsr');
    $assigner = app(BlogCategorySlugAssigner::class);

    expect($assigner->resolve(null, 'fr', null, 'Réglementation GPSR'))->toBe('reglementation-gpsr-2')
        ->and($assigner->resolve(null, 'es', null, 'Réglementation GPSR'))->toBe('reglementation-gpsr')
        ->and($assigner->resolve(null, 'fr', null, ''))->toBeNull();
});

it('refuses a typed category slug used by another category but lets a category keep its own', function () {
    $category = blogCategoryWithSlug('fr', 'plateformes');
    $assigner = app(BlogCategorySlugAssigner::class);

    expect(fn () => $assigner->resolve(null, 'fr', 'Plateformes', 'Autre nom'))->toThrow(BlogSlugException::class)
        ->and($assigner->resolve($category->id, 'fr', 'plateformes', 'Plateformes'))->toBe('plateformes');
});

it('accepts for a category a word that is reserved for posts', function () {
    expect(app(BlogCategorySlugAssigner::class)->resolve(null, 'en', 'page', 'Page'))->toBe('page');
});

it('retires every old category slug and frees it when the category takes it back', function () {
    $category = blogCategoryWithSlug('fr', 'douane');
    $assigner = app(BlogCategorySlugAssigner::class);

    $assigner->record($category, 'fr', 'douane', 'douane-et-tva');

    expect(BlogCategoryRetiredSlug::query()->sole()->slug)->toBe('douane')
        ->and(app(BlogCategorySlugRepository::class)->findRetiredCategoryId('fr', 'douane'))->toBe($category->id);

    $assigner->record($category, 'fr', 'douane-et-tva', 'douane');

    expect(BlogCategoryRetiredSlug::query()->pluck('slug')->all())->toBe(['douane-et-tva']);
});

it('protects the old slug of another category', function () {
    $category = blogCategoryWithSlug('fr', 'actualites');
    BlogCategoryRetiredSlug::factory()->create(['blog_category_id' => $category->id, 'locale' => 'fr', 'slug' => 'news']);

    expect(fn () => app(BlogCategorySlugAssigner::class)->resolve(null, 'fr', 'news', 'News'))
        ->toThrow(BlogSlugException::class);
});

it('finds a category by its live slug within a language', function () {
    $category = blogCategoryWithSlug('fr', 'etiquetage');
    $slugs = app(BlogCategorySlugRepository::class);

    expect($slugs->findCategoryId('fr', 'etiquetage'))->toBe($category->id)
        ->and($slugs->findCategoryId('en', 'etiquetage'))->toBeNull()
        ->and($slugs->findRetiredCategoryId('fr', 'etiquetage'))->toBeNull();
});

it('forgets the old slugs of a deleted category', function () {
    $category = blogCategoryWithSlug('fr', 'actualites');
    BlogCategoryRetiredSlug::factory()->create(['blog_category_id' => $category->id, 'locale' => 'fr', 'slug' => 'news']);

    $category->delete();

    expect(BlogCategoryRetiredSlug::query()->count())->toBe(0);
});
