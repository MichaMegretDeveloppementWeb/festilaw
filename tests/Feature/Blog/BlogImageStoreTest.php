<?php

use App\Actions\Blog\Image\StoreBlogImageAction;
use App\Data\Blog\StoreBlogImageData;
use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogImageUsage;
use App\Exceptions\Blog\BlogImageException;
use App\Models\BlogImage;
use App\Services\Blog\Image\BlogImagePaths;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BlogImageFiles;

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
function storeBlogImage(string $path, BlogImageUsage $usage = BlogImageUsage::Content, array $extra = []): BlogImage
{
    return app(StoreBlogImageAction::class)->execute(new StoreBlogImageData($path, $usage, ...$extra));
}

/** @return array{0: int, 1: int, 2: string} largeur, hauteur, type mime du fichier stocke */
function storedBlogFile(string $path): array
{
    $info = getimagesizefromstring(Storage::disk('public')->get($path));

    return [$info[0], $info[1], $info['mime']];
}

function blogFilesOnDisk(): array
{
    return Storage::disk('public')->allFiles('blog');
}

it('makes the public disk raise its failures instead of returning false', function () {
    expect(config('filesystems.disks.public.throw'))->toBeTrue();
});

it('stores a content image as webp variants in its own folder, without a share file', function () {
    $image = storeBlogImage(BlogImageFiles::jpeg(2000, 1000), extra: [
        'alt' => ['en' => '  A CE   label ', 'fr' => 'Une étiquette CE', 'de' => 'ignored', 'es' => ''],
        'nameHint' => 'L\'étiquette CE : mode d\'emploi',
    ]);
    $paths = app(BlogImagePaths::class);

    expect($image->blog_post_id)->toBeNull()
        ->and($image->basename)->toBe('l-etiquette-ce-mode-d-emploi')
        ->and($image->widths())->toBe([480, 768, 1200, 1600])
        ->and($image->hasShareVariant())->toBeFalse()
        ->and([$image->width, $image->height])->toBe([1600, 800])
        ->and($image->alt)->toBe(['en' => 'A CE label', 'fr' => 'Une étiquette CE'])
        ->and($image->credit_source)->toBe(BlogImageCreditSource::Upload)
        ->and(blogFilesOnDisk())->toHaveCount(4);

    foreach ([480 => 240, 768 => 384, 1200 => 600, 1600 => 800] as $width => $height) {
        expect(storedBlogFile($paths->variantOf($image, $width)))->toBe([$width, $height, 'image/webp']);
    }

    expect($paths->variantOf($image, 480))->toBe("blog/{$image->uuid}/l-etiquette-ce-mode-d-emploi-480.webp");
});

it('never enlarges an image: variants stop at its own width', function (int $width, int $height, array $expectedWidths) {
    $image = storeBlogImage(BlogImageFiles::jpeg($width, $height));

    expect($image->widths())->toBe($expectedWidths)
        ->and($image->width)->toBe(max($expectedWidths))
        ->and(blogFilesOnDisk())->toHaveCount(count($expectedWidths));
})->with([
    'between two sizes' => [1000, 500, [480, 768, 1000]],
    'exactly a configured size' => [1200, 800, [480, 768, 1200]],
    'smaller than every size' => [300, 200, [300]],
]);

it('adds a 1200 x 630 jpeg for a featured or share image', function (BlogImageUsage $usage) {
    $image = storeBlogImage(BlogImageFiles::png(2400, 1800, transparent: true), $usage);

    expect($image->hasShareVariant())->toBeTrue()
        ->and(storedBlogFile(app(BlogImagePaths::class)->shareOf($image)))->toBe([1200, 630, 'image/jpeg'])
        ->and(blogFilesOnDisk())->toHaveCount(5);
})->with([BlogImageUsage::Featured, BlogImageUsage::Share]);

it('refuses a featured image smaller than the share size, and stores nothing', function () {
    expect(fn () => storeBlogImage(BlogImageFiles::jpeg(1000, 600), BlogImageUsage::Featured))
        ->toThrow(BlogImageException::class, 'too small');

    expect(BlogImage::query()->count())->toBe(0)->and(blogFilesOnDisk())->toBe([]);
});

it('accepts as content the same small image it refuses as featured', function () {
    expect(storeBlogImage(BlogImageFiles::jpeg(1000, 600))->widths())->toBe([480, 768, 1000]);
});

it('turns a photo taken sideways the right way up', function () {
    $image = storeBlogImage(BlogImageFiles::jpegWithOrientation(1600, 900, 6));

    expect([$image->width, $image->height])->toBe([900, 1600])
        ->and($image->widths())->toBe([480, 768, 900])
        ->and(storedBlogFile(app(BlogImagePaths::class)->variantOf($image, 480)))->toBe([480, 853, 'image/webp']);
});

it('judges the minimum size of a featured image after orientation', function () {
    // 630 x 1200 une fois redressee : trop etroite pour une image principale.
    expect(fn () => storeBlogImage(BlogImageFiles::jpegWithOrientation(1200, 630, 6), BlogImageUsage::Featured))
        ->toThrow(BlogImageException::class, 'too small');
});

it('accepts png and webp sources', function (string $format) {
    $image = storeBlogImage(BlogImageFiles::{$format}(900, 600));

    expect($image->widths())->toBe([480, 768, 900]);
})->with(['png', 'webp']);

it('refuses a file that is not an accepted image, whatever its name', function (Closure $file, string $reason) {
    expect(fn () => storeBlogImage($file()))->toThrow(BlogImageException::class, $reason);

    expect(BlogImage::query()->count())->toBe(0)->and(blogFilesOnDisk())->toBe([]);
})->with([
    'text renamed .jpg' => [fn () => BlogImageFiles::fakeJpeg(), 'not an image'],
    'gif' => [fn () => BlogImageFiles::gif(600, 400), 'not supported'],
    'animated webp' => [fn () => BlogImageFiles::animatedWebp(), 'animated'],
    'missing file' => [fn () => sys_get_temp_dir().'/does-not-exist.jpg', 'not an image'],
]);

it('refuses an image above the weight limit', function () {
    config(['blog.images.max_upload_kilobytes' => 1]);

    expect(fn () => storeBlogImage(BlogImageFiles::jpeg(1200, 800)))->toThrow(BlogImageException::class, 'too heavy');
});

it('refuses an image with too many pixels before decoding it', function () {
    config(['blog.images.max_pixels' => 10_000]);

    expect(fn () => storeBlogImage(BlogImageFiles::jpeg(200, 100)))->toThrow(BlogImageException::class, 'too many pixels');
});

it('names the files from the description when no name is suggested, and falls back to a neutral name', function () {
    expect(storeBlogImage(BlogImageFiles::jpeg(600, 400), extra: ['alt' => ['fr' => 'Étiquette d\'un jouet']])->basename)->toBe('etiquette-d-un-jouet')
        ->and(storeBlogImage(BlogImageFiles::jpeg(600, 400), extra: ['nameHint' => '¿?'])->basename)->toBe('image');
});

it('keeps the credit of a stock image and drops a link that is not a web address', function () {
    $image = storeBlogImage(BlogImageFiles::jpeg(600, 400), extra: [
        'creditSource' => BlogImageCreditSource::Stock,
        'creditAuthor' => "  Jane \n Doe ",
        'creditUrl' => 'javascript:alert(1)',
        'creditLicense' => 'Pixabay Content License',
    ]);

    $withLink = storeBlogImage(BlogImageFiles::jpeg(600, 400), extra: ['creditUrl' => ' https://pixabay.com/photos/label-123/ ']);

    expect($image->credit_source)->toBe(BlogImageCreditSource::Stock)
        ->and($image->credit_author)->toBe('Jane Doe')
        ->and($image->credit_url)->toBeNull()
        ->and($image->credit_license)->toBe('Pixabay Content License')
        ->and($withLink->credit_url)->toBe('https://pixabay.com/photos/label-123/');
});

it('creates no row and removes the files already written when the disk fails', function () {
    BlogImageFiles::failingDisk(failWrites: true);

    expect(fn () => storeBlogImage(BlogImageFiles::jpeg(2000, 1000)))
        ->toThrow(BlogImageException::class, 'Failed to write');

    expect(BlogImage::query()->count())->toBe(0)->and(blogFilesOnDisk())->toBe([]);
});

it('removes the files when the row cannot be created', function () {
    BlogImage::creating(function (): void {
        throw new RuntimeException('database is down (test)');
    });

    expect(fn () => storeBlogImage(BlogImageFiles::jpeg(2000, 1000)))->toThrow(RuntimeException::class, 'database is down');

    expect(blogFilesOnDisk())->toBe([]);
});

it('logs the folder left behind when even the cleanup fails, without hiding the original error', function () {
    BlogImageFiles::failingDisk(failWrites: true, failDirectoryDeletes: true);
    Log::spy();

    expect(fn () => storeBlogImage(BlogImageFiles::jpeg(2000, 1000)))
        ->toThrow(BlogImageException::class, 'Failed to write');

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message, array $context): bool => str_contains($message, 'left behind') && str_starts_with($context['directory'], 'blog/'))
        ->once();
});
