<?php

use App\Actions\Blog\Image\RecropBlogImageAction;
use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Data\Blog\BlogImageCrop;
use App\Data\Blog\StoreBlogImageData;
use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogImageUsage;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\Image\BlogImagePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BlogImageFiles;

/*
 | Recadrage d'une image du texte : le serveur decoupe le rectangle garde avant de fabriquer les variantes, garde
 | la source non decoupee, et sait refaire un autre cadrage a partir d'elle, en creant une nouvelle image.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    // Les sources non decoupees vont sur le disque prive (audit du 23/11).
    Storage::fake('local');
});

afterEach(function () {
    BlogImageFiles::cleanup();
    BlogImage::flushEventListeners();
});

/** @param array<string, mixed> $extra */
function storeCroppedBlogImage(string $path, ?BlogImageCrop $crop, array $extra = []): BlogImage
{
    return app(StoreBlogImageAction::class)->execute(new StoreBlogImageData($path, BlogImageUsage::Content, ...[...$extra, 'crop' => $crop, 'keepSource' => true]));
}

/** @return array{0: int, 1: int, 2: string} largeur, hauteur, type mime du fichier stocke */
function croppedBlogFile(string $path): array
{
    // La source non decoupee est sur le disque prive, les tailles servies sur le disque public (audit du 23/11).
    $info = getimagesizefromstring(Storage::disk(str_ends_with($path, '-source.webp') ? 'local' : 'public')->get($path));

    return [$info[0], $info[1], $info['mime']];
}

it('cuts the image to the kept rectangle before making its variants, and keeps the uncut source', function () {
    $image = storeCroppedBlogImage(BlogImageFiles::jpeg(2000, 1000), new BlogImageCrop(500, 100, 1000, 800));
    $paths = app(BlogImagePaths::class);

    expect([$image->width, $image->height])->toBe([1000, 800])
        ->and($image->widths())->toBe([480, 768, 1000])
        ->and($image->hasSource())->toBeTrue()
        ->and($image->crop)->toBe(['x' => 500, 'y' => 100, 'width' => 1000, 'height' => 800])
        ->and(croppedBlogFile($paths->variantOf($image, 1000)))->toBe([1000, 800, 'image/webp'])
        ->and(croppedBlogFile($paths->variantOf($image, 480)))->toBe([480, 384, 'image/webp'])
        // La source est l'image entiere, non decoupee.
        ->and(croppedBlogFile($paths->sourceOf($image)))->toBe([2000, 1000, 'image/webp'])
        ->and($paths->sourceOf($image))->toBe("blog/{$image->uuid}/{$image->basename}-source.webp")
        ->and(Storage::disk('public')->allFiles('blog'))->toHaveCount(3)
        ->and(Storage::disk('local')->allFiles('blog'))->toBe([$paths->sourceOf($image)]);
});

it('keeps a source no wider than 2400 px, with the framing expressed in its pixels', function () {
    $image = storeCroppedBlogImage(BlogImageFiles::jpeg(3000, 1500), new BlogImageCrop(1500, 0, 1500, 1500));

    // La decoupe se fait dans le fichier envoye (1500 px gardes), le cadrage est enregistre a l'echelle de la source.
    expect([$image->width, $image->height])->toBe([1500, 1500])
        ->and(croppedBlogFile(app(BlogImagePaths::class)->sourceOf($image)))->toBe([2400, 1200, 'image/webp'])
        ->and($image->crop)->toBe(['x' => 1200, 'y' => 0, 'width' => 1200, 'height' => 1200]);
});

it('keeps the whole image, and still its source, when no rectangle is given', function () {
    $image = storeCroppedBlogImage(BlogImageFiles::jpeg(1200, 800), null);

    expect([$image->width, $image->height])->toBe([1200, 800])
        ->and($image->hasSource())->toBeTrue()
        ->and($image->crop)->toBe(['x' => 0, 'y' => 0, 'width' => 1200, 'height' => 800]);
});

it('keeps no source for an image that is not meant to be framed again', function () {
    $image = app(StoreBlogImageAction::class)->execute(new StoreBlogImageData(BlogImageFiles::jpeg(1600, 900), BlogImageUsage::Featured));

    expect($image->hasSource())->toBeFalse()
        ->and($image->crop)->toBeNull()
        ->and(Storage::disk('local')->exists(app(BlogImagePaths::class)->sourceOf($image)))->toBeFalse();
});

it('brings a rectangle that overflows by a pixel back inside the image', function () {
    $image = storeCroppedBlogImage(BlogImageFiles::jpeg(1000, 600), new BlogImageCrop(-1, 0, 1002, 601));

    expect([$image->width, $image->height])->toBe([1000, 600]);
});

it('turns a photo taken sideways the right way up before cutting it', function () {
    // 1200 x 800 sur le disque, a tourner d'un quart de tour : l'image redressee fait 800 x 1200.
    $image = storeCroppedBlogImage(BlogImageFiles::jpegWithOrientation(1200, 800, 6), new BlogImageCrop(0, 600, 800, 600));

    expect([$image->width, $image->height])->toBe([800, 600])
        ->and(croppedBlogFile(app(BlogImagePaths::class)->sourceOf($image)))->toBe([800, 1200, 'image/webp']);
});

it('refuses a kept area that is too small or outside the image, and stores nothing', function (BlogImageCrop $crop) {
    expect(fn () => storeCroppedBlogImage(BlogImageFiles::jpeg(2000, 1000), $crop))
        ->toThrow(BlogImageException::class, 'kept area');

    expect(BlogImage::query()->count())->toBe(0)
        ->and(Storage::disk('public')->allFiles('blog'))->toBe([]);
})->with([
    'too narrow' => [new BlogImageCrop(0, 0, 150, 600)],
    'too flat' => [new BlogImageCrop(0, 0, 600, 100)],
    'outside the image' => [new BlogImageCrop(5000, 5000, 600, 400)],
]);

it('says in plain words that the kept area is too small', function () {
    try {
        storeCroppedBlogImage(BlogImageFiles::jpeg(2000, 1000), new BlogImageCrop(0, 0, 100, 100));
    } catch (BlogImageException $e) {
        expect($e->getUserMessage())->toBe('La partie gardée est trop petite. Gardez au moins 200 × 120 pixels de l\'image.');

        return;
    }

    $this->fail('The crop should have been refused.');
});

// --- Nouveau cadrage d'une image deja enregistree ------------------------------------------------

it('makes a new pending image from the kept source, with the same texts, and leaves the first one untouched', function () {
    $first = storeCroppedBlogImage(BlogImageFiles::jpeg(2000, 1000), new BlogImageCrop(0, 0, 1000, 1000), [
        'alt' => ['en' => 'A CE label', 'fr' => 'Une étiquette CE'],
        'nameHint' => 'ce label',
        'creditSource' => BlogImageCreditSource::Stock,
        'creditAuthor' => 'Jane Doe',
    ]);
    $paths = app(BlogImagePaths::class);
    $filesOfFirst = Storage::disk('public')->allFiles("blog/{$first->uuid}");

    $second = app(RecropBlogImageAction::class)->execute($first->id, new BlogImageCrop(1000, 200, 800, 600), null);

    expect($second->id)->not->toBe($first->id)
        ->and($second->uuid)->not->toBe($first->uuid)
        ->and($second->blog_post_id)->toBeNull()
        ->and($second->basename)->toBe($first->basename)
        ->and($second->alt)->toBe(['en' => 'A CE label', 'fr' => 'Une étiquette CE'])
        ->and($second->credit_source)->toBe(BlogImageCreditSource::Stock)
        ->and($second->credit_author)->toBe('Jane Doe')
        ->and([$second->width, $second->height])->toBe([800, 600])
        ->and($second->crop)->toBe(['x' => 1000, 'y' => 200, 'width' => 800, 'height' => 600])
        ->and($second->hasSource())->toBeTrue()
        // La source est reprise telle quelle, jamais reencodee d'un cadrage a l'autre.
        ->and(Storage::disk('local')->get($paths->sourceOf($second)))->toBe(Storage::disk('local')->get($paths->sourceOf($first)))
        // La premiere image n'a pas bouge : un article en ligne qui la montre ne perd rien.
        ->and(Storage::disk('public')->allFiles("blog/{$first->uuid}"))->toBe($filesOfFirst)
        ->and($first->fresh()->crop)->toBe(['x' => 0, 'y' => 0, 'width' => 1000, 'height' => 1000])
        ->and([$first->fresh()->width, $first->fresh()->height])->toBe([1000, 1000]);
});

it('frames again an image placed before sources were kept, from its largest file', function () {
    // Une image du texte telle que l'ancien envoi la laissait : ses variantes, sans source ni cadrage.
    $older = app(StoreBlogImageAction::class)->execute(new StoreBlogImageData(BlogImageFiles::jpeg(2000, 1000), BlogImageUsage::Content));
    $paths = app(BlogImagePaths::class);

    expect($older->hasSource())->toBeFalse()
        ->and($paths->recropSourceOf($older))->toBe($paths->variantOf($older, 1600));

    // Le rectangle se lit dans sa plus grande variante (1600 x 800).
    $framed = app(RecropBlogImageAction::class)->execute($older->id, new BlogImageCrop(400, 100, 800, 600), null);

    expect($framed->id)->not->toBe($older->id)
        ->and([$framed->width, $framed->height])->toBe([800, 600])
        ->and($framed->crop)->toBe(['x' => 400, 'y' => 100, 'width' => 800, 'height' => 600])
        // La nouvelle image garde ce fichier comme source : elle se recadrera encore, sans perte de plus.
        ->and($framed->hasSource())->toBeTrue()
        ->and(Storage::disk('local')->get($paths->sourceOf($framed)))->toBe(Storage::disk('public')->get($paths->variantOf($older, 1600)))
        ->and(croppedBlogFile($paths->sourceOf($framed)))->toBe([1600, 800, 'image/webp'])
        // L'ancienne image n'a pas bouge.
        ->and($older->fresh()->widths())->toBe([480, 768, 1200, 1600]);
});

it('refuses to frame again an image that has no file at all', function () {
    $empty = BlogImage::factory()->create(['variants' => ['widths' => [], 'share' => false]]);

    expect(fn () => app(RecropBlogImageAction::class)->execute($empty->id, new BlogImageCrop(0, 0, 800, 600), null))
        ->toThrow(BlogImageException::class, 'no kept source');

    expect(BlogImage::query()->count())->toBe(1);
});

it('refuses to frame again an image that belongs to another article, or that does not exist', function () {
    $image = storeCroppedBlogImage(BlogImageFiles::jpeg(2000, 1000), null);
    $image->update(['blog_post_id' => BlogPost::factory()->create()->id]);
    $other = BlogPost::factory()->create();

    expect(fn () => app(RecropBlogImageAction::class)->execute($image->id, new BlogImageCrop(0, 0, 800, 600), $other->id))
        ->toThrow(BlogImageException::class)
        ->and(fn () => app(RecropBlogImageAction::class)->execute(999_999, new BlogImageCrop(0, 0, 800, 600), null))
        ->toThrow(BlogImageException::class);

    // Depuis son propre article, le nouveau cadrage est accepte.
    expect(app(RecropBlogImageAction::class)->execute($image->id, new BlogImageCrop(0, 0, 800, 600), $image->blog_post_id)->blog_post_id)->toBeNull();
});

it('refuses a new framing that is too small, and creates nothing', function () {
    $image = storeCroppedBlogImage(BlogImageFiles::jpeg(2000, 1000), null);

    expect(fn () => app(RecropBlogImageAction::class)->execute($image->id, new BlogImageCrop(0, 0, 100, 100), null))
        ->toThrow(BlogImageException::class, 'kept area');

    expect(BlogImage::query()->count())->toBe(1)
        ->and(Storage::disk('public')->directories('blog'))->toHaveCount(1);
});

it('removes the files of the new framing when its row cannot be created', function () {
    $image = storeCroppedBlogImage(BlogImageFiles::jpeg(2000, 1000), null);

    BlogImage::creating(function (): void {
        throw new RuntimeException('database is down (test)');
    });

    expect(fn () => app(RecropBlogImageAction::class)->execute($image->id, new BlogImageCrop(0, 0, 800, 600), null))
        ->toThrow(RuntimeException::class, 'database is down');

    // Il ne reste que le dossier de la premiere image.
    expect(Storage::disk('public')->directories('blog'))->toBe(["blog/{$image->uuid}"]);
});
