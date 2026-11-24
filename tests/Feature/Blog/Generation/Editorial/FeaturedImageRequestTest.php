<?php

declare(strict_types=1);

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\RequestBlogFeaturedImageAction;
use App\Ai\Agents\Blog\ImageBriefAgent;
use App\Ai\Agents\Blog\PhotoCheckAgent;
use App\Data\Blog\Generation\BlogFeaturedImageResult;
use App\Data\Blog\Generation\RequestBlogFeaturedImageData;
use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogEditorialException;
use App\Livewire\Admin\Blog\PostFeaturedImage;
use App\Livewire\Admin\Blog\PostForm;
use App\Mail\BlogGenerationBatchFinished;
use App\Models\BlogGeneration;
use App\Models\BlogImage;
use App\Models\User;
use App\Services\Admin\Blog\BlogFeaturedImageResultService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Ai\Image;
use Livewire\Livewire;
use Tests\Support\BlogContent;
use Tests\Support\BlogImageFiles;
use Tests\Support\EditorialFixtures as Fixtures;

use function Pest\Laravel\actingAs;

/*
 | Image principale demandee depuis un article (demande de Micha du 24/11/2026) : une nouvelle recherche dans la
 | banque d'images, ou une image creee par l'IA, traitee par le worker comme une traduction assistee. L'image revient
 | libre au formulaire, qui la pose ; l'enregistrement de l'article la rattache.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    Fixtures::publicHosts();
    config(['services.pixabay.key' => 'test-key', 'blog.ai.pipeline' => 'editorial']);
});

afterEach(fn () => BlogImageFiles::cleanup());

function requestFeaturedImage(string $mode, array $titles = ['en' => 'Labels for toys sold in the EU'], array $excerpts = ['en' => 'What a toy label must show.'], ?int $currentImageId = null, ?int $postId = null): int
{
    return app(RequestBlogFeaturedImageAction::class)->execute(new RequestBlogFeaturedImageData(
        BlogAiImageMode::from($mode), $titles, $excerpts, $currentImageId, $postId,
    ));
}

function fakeImageBrief(): void
{
    ImageBriefAgent::fake([[
        'description' => 'A wooden toy car on a table',
        'search_query' => 'wooden toy',
        'generation_prompt' => 'A wooden toy car on a light wooden table, soft daylight.',
        'alt_fr' => 'Une petite voiture en bois sur une table',
        'alt_es' => 'Un coche de madera sobre una mesa',
        'report_fr' => 'Une voiture en bois, pour un article sur les jouets.',
    ]])->preventStrayPrompts();
}

/** La banque simulee : la photo actuelle de l'article, puis une autre, toutes deux sur le CDN. */
function fakeStockBank(bool $checkAccepts = true): void
{
    $photo = file_get_contents(BlogImageFiles::jpeg(1280, 853));
    Http::fake([
        'https://pixabay.com/api/*' => Http::response(['hits' => [
            ['id' => 1, 'pageURL' => 'https://pixabay.com/photos/tools-1/', 'previewURL' => 'https://cdn.pixabay.com/photo/2020/01/01/00/00/tools-1_150.jpg', 'imageWidth' => 4000, 'imageHeight' => 2667, 'user' => 'Old'],
            ['id' => 2, 'pageURL' => 'https://pixabay.com/photos/toy-2/', 'previewURL' => 'https://cdn.pixabay.com/photo/2020/01/01/00/00/toy-2_150.jpg', 'imageWidth' => 4000, 'imageHeight' => 2667, 'user' => 'Anna'],
        ]]),
        'https://cdn.pixabay.com/*' => Http::response($photo, 200, ['Content-Type' => 'image/jpeg']),
        '*' => Http::response('ok'),
    ]);
    PhotoCheckAgent::fake(fn (): array => [
        'fits' => $checkAccepts,
        'reason' => $checkAccepts ? 'A toy suits an article on toys.' : 'Nothing to do with toys.',
        'alt_en' => 'A wooden toy car on a table',
        'alt_fr' => 'Une petite voiture en bois sur une table',
        'alt_es' => 'Un coche de madera sobre una mesa',
    ])->preventStrayPrompts();
}

it('queues an image request with the text of the first written language and an estimate of its own', function () {
    $stock = BlogGeneration::query()->findOrFail(requestFeaturedImage('stock', ['en' => '', 'fr' => 'Étiquettes des jouets'], ['fr' => 'Un extrait.']));
    $generated = BlogGeneration::query()->findOrFail(requestFeaturedImage('generated', ['en' => 'Labels for toys sold in the EU', 'fr' => 'Les étiquettes des jouets']));

    expect($stock->type)->toBe(BlogGenerationType::Image)
        ->and($stock->payload['image_request'])->toMatchArray(['locale' => 'fr', 'title' => 'Étiquettes des jouets', 'excerpt' => 'Un extrait.'])
        ->and($stock->payload['images'])->toBe(['mode' => 'stock', 'max' => 1])
        ->and($stock->topic_hint)->toBe('Image principale de « Étiquettes des jouets », dans la banque d\'images')
        // Nommee dans l'historique par le titre francais, comme les articles (retour de Micha du 24/11), meme quand
        // l'anglais decrit l'article au modele.
        ->and($generated->topic_hint)->toBe('Image principale de « Les étiquettes des jouets », créée par l\'IA')
        ->and($generated->payload['image_request']['title'])->toBe('Labels for toys sold in the EU')
        // La description et quatre controles de photo au plus : quelques dixiemes de centime, loin d'un article.
        ->and($stock->forecast_high_microusd)->toBeGreaterThan(0)->toBeLessThan(20_000)
        // Une image creee compte en plus son prix.
        ->and($generated->forecast_high_microusd)->toBeGreaterThan($stock->forecast_high_microusd);
});

it('refuses a request without any title, or an AI image without an image model', function (string $mode, array $titles) {
    config(['blog.ai.defaults.models.image' => null]);

    expect(fn () => requestFeaturedImage($mode, $titles, []))->toThrow(BlogEditorialException::class);
    expect(BlogGeneration::query()->count())->toBe(0);
})->with([
    'no title in any language' => ['stock', ['en' => '', 'fr' => ' ']],
    'no image model' => ['generated', ['en' => 'Labels']],
]);

it('finds another stock photo than the current one, credited and described, free until the article is saved', function () {
    $post = BlogContent::draft('label');
    $current = BlogImage::query()->findOrFail($post->featured_image_id);
    $current->update(['credit_url' => 'https://pixabay.com/photos/tools-1/', 'alt' => ['en' => 'Tools on a workbench']]);
    fakeImageBrief();
    fakeStockBank();

    $id = requestFeaturedImage('stock', currentImageId: $current->id, postId: $post->id);
    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $result = app(BlogFeaturedImageResultService::class)->find($id);
    $image = BlogImage::query()->findOrFail($result->imageId);
    expect($result->isReady())->toBeTrue()
        ->and($image->blog_post_id)->toBeNull()
        ->and($image->credit_source)->toBe(BlogImageCreditSource::Stock)
        ->and($image->credit_author)->toBe('Anna')
        ->and($image->alt)->toBe(['en' => 'A wooden toy car on a table', 'fr' => 'Une petite voiture en bois sur une table', 'es' => 'Un coche de madera sobre una mesa'])
        ->and($post->refresh()->featured_image_id)->toBe($current->id);
    // La photo actuelle n'est pas reprise, et la description demande une autre image.
    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'tools-1'));
    ImageBriefAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Current main image') && $prompt->contains('Tools on a workbench'));
    // Une demande d'image se suit dans le formulaire : aucun e-mail.
    Mail::assertNotSent(BlogGenerationBatchFinished::class);
});

it('creates the main image with the image model of the settings and the descriptions of the brief', function () {
    fakeImageBrief();
    Image::fake([base64_encode(file_get_contents(BlogImageFiles::jpeg(2048, 1152)))])->preventStrayImages();

    $id = requestFeaturedImage('generated');
    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $image = BlogImage::query()->findOrFail(app(BlogFeaturedImageResultService::class)->find($id)->imageId);
    expect($image->credit_source)->toBe(BlogImageCreditSource::Ai)
        ->and($image->alt['fr'])->toBe('Une petite voiture en bois sur une table');
    Image::assertGenerated(fn ($prompt): bool => $prompt->contains('A wooden toy car on a light wooden table'));
});

it('says why no image came back, no photo that fits or a bank that does not answer', function (bool $bankAnswers, string $cause) {
    fakeImageBrief();

    if ($bankAnswers) {
        fakeStockBank(checkAccepts: false);
    } else {
        Http::fake(['https://pixabay.com/api/*' => Http::failedConnection()]);
    }

    $id = requestFeaturedImage('stock');
    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $result = app(BlogFeaturedImageResultService::class)->find($id);
    expect($result->isReady())->toBeFalse()
        ->and($result->cause)->toBe($cause)
        ->and(BlogImage::query()->whereNull('blog_post_id')->count())->toBe(0);
})->with([
    'no photo fits' => [true, BlogFeaturedImageResult::NONE_FIT],
    'the bank does not answer' => [false, BlogFeaturedImageResult::BANK_UNAVAILABLE],
]);

it('lets the form follow an image request, hand back the image, and find it again after a reload', function () {
    actingAs(User::factory()->create());
    $post = BlogContent::draft('label');
    fakeImageBrief();
    fakeStockBank();

    $component = Livewire::test(PostFeaturedImage::class, ['postId' => $post->id])
        ->dispatch('request-featured-image', mode: 'stock', titles: ['en' => 'Labels for toys'], excerpts: ['en' => 'An excerpt.']);
    $generationId = $component->get('generationId');
    expect($generationId)->not->toBeNull();

    // Rechargee avant la fin, la page retrouve la demande en cours.
    Livewire::test(PostFeaturedImage::class, ['postId' => $post->id])
        ->assertSet('generationId', $generationId)
        ->assertDispatched('featured-image-pending', mode: 'stock');

    $component->call('check')->assertNotDispatched('featured-image-ready');
    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $component->call('check')
        ->assertDispatched('featured-image-ready', fn (string $event, array $params): bool => BlogImage::query()->whereKey($params['imageId'])->whereNull('blog_post_id')->exists())
        ->assertSet('generationId', null);
});

it('proposes an image generated by the AI when no stock photo fits', function () {
    actingAs(User::factory()->create());
    fakeImageBrief();
    fakeStockBank(checkAccepts: false);

    $component = Livewire::test(PostFeaturedImage::class)
        ->dispatch('request-featured-image', mode: 'stock', titles: ['en' => 'Labels for toys'], excerpts: []);
    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $component->call('check')
        ->assertDispatched('featured-image-failed')
        ->assertDispatched('ui-toast', fn (string $event, array $params): bool => $params['type'] === 'warning' && str_contains($params['title'], 'choisissez « Générée par IA »'));
});

it('uses a found image as the main image of the form, attached when the article is saved, never one of another article', function () {
    actingAs(User::factory()->create());
    $post = BlogContent::draft('label');
    $free = BlogImage::factory()->withShare()->create(['blog_post_id' => null]);
    $foreign = BlogImage::query()->findOrFail(BlogContent::draft('other')->featured_image_id);

    $form = Livewire::test(PostForm::class, ['post' => $post->id]);

    // Montree sans etre posee, puis posee.
    expect($form->call('foundImage', $free->id)->get('featuredImageId'))->toBe($post->featured_image_id);
    $form->call('useFoundImage', $free->id)->assertSet('featuredImageId', $free->id);

    $form->call('useFoundImage', $foreign->id)
        ->assertSet('featuredImageId', $free->id)
        ->assertDispatched('ui-toast', fn (string $event, array $params): bool => $params['type'] === 'error');

    $form->call('save', BlogContent::contentsOf($post));
    expect($free->refresh()->blog_post_id)->toBe($post->id)
        ->and($post->refresh()->featured_image_id)->toBe($free->id);
});
