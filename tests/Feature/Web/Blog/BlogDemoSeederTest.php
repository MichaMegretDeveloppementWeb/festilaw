<?php

use App\Enums\Blog\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\BlogPostRetiredSlug;
use App\Models\BlogPostTranslation;
use Database\Seeders\BlogDemo\BlogDemoImages;
use Database\Seeders\BlogDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BlogContent;
use Tests\Support\BlogImageFiles;

use function Pest\Laravel\get;

/*
 | Articles de demonstration : crees par les vraies Actions, en local seulement, re-executables sans
 | doublon et sans toucher aux autres articles.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    // Dossier de photos vide : le test ne depend pas de ce que le developpeur a depose en local.
    $this->photoDirectory = storage_path('framework/testing/blog-demo-photos-'.uniqid());
    File::ensureDirectoryExists($this->photoDirectory);
    $this->app->when(BlogDemoSeeder::class)->needs('$photoDirectory')->give($this->photoDirectory);
});

afterEach(function () {
    File::deleteDirectory($this->photoDirectory);
    BlogImageFiles::cleanup();
});

it('publishes the demonstration posts through the real actions, and can be run again without duplicates', function () {
    $mine = BlogContent::published('mine');
    Storage::fake('public');

    $this->seed(BlogDemoSeeder::class);

    $firstRun = BlogPostTranslation::query()->where('slug', '!=', 'mine-en')->where('locale', 'en')->orderBy('slug')->pluck('slug')->all();

    expect(BlogPost::query()->where('status', BlogPostStatus::Published)->count())->toBe(15)
        ->and($firstRun)->toHaveCount(14)
        ->and(BlogCategory::query()->count())->toBe(6)
        ->and(BlogPostTranslation::query()->where('word_count', 0)->count())->toBe(0)
        ->and(Storage::disk('public')->directories('blog'))->toHaveCount(17);

    // Le plus recent en tete du hub, dans chaque langue, avec la mention de demonstration dans l'article.
    get('/blog')->assertOk()->assertSeeInOrder(['post-featured', 'The GPSR in plain language: where to start'], false);
    get('/fr/blog/le-gpsr-en-clair')->assertOk()
        ->assertSee('Contenu de démonstration.')
        ->assertSee('<figure', false)
        ->assertSee('href="/fr/pricing"', false);
    get('/es/blog/categoria/etiquetado-y-documentos')->assertOk()->assertSee('Redactar advertencias de seguridad');

    $this->seed(BlogDemoSeeder::class);

    // Memes adresses, aucun doublon, aucune marque "n'existe plus", aucun dossier d'image en trop.
    expect(BlogPostTranslation::query()->where('slug', '!=', 'mine-en')->where('locale', 'en')->orderBy('slug')->pluck('slug')->all())->toBe($firstRun)
        ->and(BlogPost::query()->count())->toBe(15)
        ->and(BlogCategory::query()->count())->toBe(6)
        ->and(BlogPostRetiredSlug::query()->count())->toBe(0)
        ->and(BlogImage::query()->count())->toBe(18)
        ->and(Storage::disk('public')->directories('blog'))->toHaveCount(17)
        ->and($mine->fresh()->status)->toBe(BlogPostStatus::Published);

    get('/blog/gpsr-in-plain-language')->assertOk();
});

it('refuses to run outside a local environment', function () {
    $this->app->detectEnvironment(fn (): string => 'production');

    expect(fn () => app(BlogDemoSeeder::class)->run())->toThrow(RuntimeException::class, 'only runs in a local environment');

    expect(BlogPost::query()->count())->toBe(0)
        ->and(BlogCategory::query()->count())->toBe(0);
});

it('uses a photo dropped in the local folder, with its credit, and a generated visual otherwise', function () {
    File::copy(BlogImageFiles::jpeg(1600, 900), $this->photoDirectory.'/label.jpg');
    File::put($this->photoDirectory.'/credits.json', json_encode([
        'label' => ['author' => 'Ada Example', 'url' => 'https://example.org/photo/1', 'license' => 'CC0'],
    ]));
    $images = new BlogDemoImages($this->photoDirectory);

    $photo = $images->for('label', 0);
    $generated = $images->for('unknown', 3);

    expect($photo['path'])->toBe($this->photoDirectory.DIRECTORY_SEPARATOR.'label.jpg')
        ->and($photo['credit'])->toBe(['author' => 'Ada Example', 'url' => 'https://example.org/photo/1', 'license' => 'CC0'])
        ->and($generated['credit'])->toBeNull()
        ->and(array_slice(getimagesize($generated['path']), 0, 2))->toBe([1200, 675]);

    $images->cleanup();

    // Le visuel fabrique est retire, la photo deposee n'est jamais touchee.
    expect(is_file($generated['path']))->toBeFalse()
        ->and(is_file($photo['path']))->toBeTrue();
});
