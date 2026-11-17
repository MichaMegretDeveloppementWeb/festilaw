<?php

use App\Actions\Blog\Generation\Editorial\AssembleGeneratedBlogDraftAction;
use App\Actions\Blog\Generation\Editorial\GenerateBlogDraftImagesAction;
use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\ResearchAgent;
use App\Ai\Agents\Blog\StyleAgent;
use App\Ai\Agents\Blog\TopicAgent;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Ai\Agents\Blog\WriterAgent;
use App\Contracts\Blog\Generation\StockImageProvider;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Mail\BlogGenerationBatchFinished;
use App\Models\BlogGeneration;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Image;
use Tests\Support\BlogContent;
use Tests\Support\BlogImageFiles;
use Tests\Support\EditorialFixtures as Fixtures;

/*
 | Images, assemblage du brouillon et pipeline editorial complet. Agents, banque d'images, generation
 | d'image et reseau simules ; disque de test. Aucun appel reel.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
    Fixtures::publicHosts();
    config(['services.pixabay.key' => 'test-key']);
});

afterEach(fn () => BlogImageFiles::cleanup());

/** Une reponse de l'API Pixabay : une photo trop petite, puis une qui convient. */
function pixabayHits(): array
{
    return ['hits' => [
        ['id' => 1, 'pageURL' => 'https://pixabay.com/photos/tiny-1/', 'largeImageURL' => 'https://pixabay.com/get/tiny_1280.jpg', 'imageWidth' => 900, 'imageHeight' => 500, 'user' => 'Small'],
        ['id' => 2, 'pageURL' => 'https://pixabay.com/photos/parcel-2/', 'largeImageURL' => 'https://pixabay.com/get/parcel_1280.jpg', 'imageWidth' => 4000, 'imageHeight' => 2667, 'user' => 'Anna'],
        ['id' => 3, 'pageURL' => 'https://pixabay.com/photos/label-3/', 'largeImageURL' => 'https://pixabay.com/get/label_1280.jpg', 'imageWidth' => 3000, 'imageHeight' => 2000, 'user' => 'Ben'],
    ]];
}

function fakePixabay(): void
{
    $photo = file_get_contents(BlogImageFiles::jpeg(1280, 853));

    Http::fake([
        'https://pixabay.com/api/*' => Http::response(pixabayHits()),
        'https://pixabay.com/get/*' => Http::response($photo, 200, ['Content-Type' => 'image/jpeg']),
        '*' => Http::response('ok'),
    ]);
}

/** Les sorties des etapes de texte, comme le journal les garde a l'arrivee des images. */
function textOutputs(): array
{
    $french = str_replace(['What Etsy asks for', 'What to do next'], ['Ce que demande Etsy', 'La suite'], Fixtures::body());

    return [
        'topic' => Fixtures::topic(),
        'research' => Fixtures::research(),
        'writing' => Fixtures::writingOutput(),
        'style' => Fixtures::articleOutput(),
        'translation_fr' => ['article' => [...Fixtures::articleOutput($french)['article'], 'title' => 'Ajouter un opérateur économique sur Etsy'], 'image_alts' => ['0' => 'Un colis sur un établi', '1' => 'Une étiquette sur un carton']],
        'translation_es' => ['article' => [...Fixtures::articleOutput()['article'], 'title' => 'Añadir un operador económico en Etsy'], 'image_alts' => ['0' => 'Un paquete sobre un banco']],
    ];
}

// --- Images

it('takes a stock photo for the main image and one for the text, downloaded and stored with their credit and three descriptions', function () {
    fakePixabay();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    $images = BlogImage::query()->orderBy('id')->get();
    expect($images)->toHaveCount(2)
        ->and($images[0]->credit_source)->toBe(BlogImageCreditSource::Stock)
        ->and($images[0]->credit_author)->toBe('Anna')
        ->and($images[0]->credit_url)->toBe('https://pixabay.com/photos/parcel-2/')
        ->and($images[0]->credit_license)->toBe('Pixabay Content License')
        ->and($images[0]->alt)->toBe(['en' => 'A parcel on a workbench', 'fr' => 'Un colis sur un établi', 'es' => 'Un paquete sobre un banco'])
        ->and($images[0]->hasShareVariant())->toBeTrue()
        // La photo trop petite pour l'image principale convient dans le texte, et ce n'est pas la meme photo.
        ->and($images[1]->credit_author)->toBe('Small')
        ->and($images[1]->blog_post_id)->toBeNull()
        ->and($result->output['images'])->toHaveCount(2)
        ->and($result->summary)->toBe('2 photos de banque ont été choisies, avec le nom de leur auteur.');
    // La recherche est gardee 24 heures : la meme requete ne repart pas chez Pixabay.
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://pixabay.com/api/') && $request['safesearch'] === 'true' && $request['orientation'] === 'horizontal');
});

it('delivers the draft without a main image when no stock photo fits, and says so', function () {
    Http::fake(['https://pixabay.com/api/*' => Http::response(['hits' => []]), '*' => Http::response('ok')]);

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    expect(BlogImage::query()->count())->toBe(0)
        ->and($result->output['warnings'])->toBe(['no main image', 'no image 1'])
        ->and($result->summary)->toBe('Aucune photo de banque ne convenait. Il manque l\'image principale, à ajouter avant de publier.');
});

it('never downloads a stock photo from another host than the image bank', function () {
    Http::fake([
        'https://pixabay.com/api/*' => Http::response(['hits' => [['id' => 9, 'pageURL' => 'https://pixabay.com/photos/x-9/', 'largeImageURL' => 'https://evil.example/x.jpg', 'imageWidth' => 4000, 'imageHeight' => 2600, 'user' => 'X']]]),
        '*' => Http::response('should not be requested'),
    ]);

    app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'evil.example'));
    expect(BlogImage::query()->count())->toBe(0);
});

it('keeps the image bank key out of the error when the bank cannot be reached', function () {
    Http::fake(['https://pixabay.com/api/*' => Http::failedConnection()]);

    expect(fn () => app(StockImageProvider::class)->search('a parcel on a workbench', 1200, 630))
        ->toThrow(function (BlogEditorialException $e) {
            expect($e->getMessage())->toContain('Pixabay did not answer')
                ->toContain('key=[PIXABAY_API_KEY]')
                ->not->toContain('test-key');
        });
});

it('generates the images with the image model of the settings when the generation asks for it, and credits the model', function () {
    Image::fake([base64_encode(file_get_contents(BlogImageFiles::jpeg(2048, 1152)))])->preventStrayImages();
    $model = Fixtures::context()->settings->modelFor('image');

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs(), payload: ['images' => ['mode' => 'generated', 'max' => 1]]));

    $image = BlogImage::query()->sole();
    expect($image->credit_source)->toBe(BlogImageCreditSource::Ai)
        ->and($image->credit_author)->toBe(app(BlogAiModelCatalog::class)->name($model))
        ->and($result->summary)->toBe('Une image a été générée par l\'IA.')
        ->and($result->usage->model)->toBe($model);
    Image::assertGenerated(fn ($prompt): bool => $prompt->contains('A parcel on a wooden workbench.') && $prompt->contains('No identifiable person'));
});

it('stops everything when the credit runs out while generating an image', function () {
    Image::fake(fn () => throw InsufficientCreditsException::forProvider('openrouter', 402));

    expect(fn () => app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs(), payload: ['images' => ['mode' => 'generated', 'max' => 1]])))
        ->toThrow(fn (BlogAiProviderException $e) => expect($e->creditExhausted)->toBeTrue());
});

it('adds no image when the generation asks for none', function () {
    Http::fake();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs(), payload: ['images' => ['mode' => 'none', 'max' => 1]]));

    expect($result->output['images'])->toBe([])
        ->and($result->summary)->toBe('Aucune image n\'était demandée pour cet article.');
    Http::assertNothingSent();
});

// --- Assemblage

it('assembles a draft in the three languages through the same path as the back-office, marked as generated', function () {
    fakePixabay();
    $category = BlogContent::category('platforms');
    $related = BlogContent::published('label', $category);
    $generation = BlogGeneration::factory()->running()->create();
    $outputs = textOutputs();
    $outputs['writing'] = [...$outputs['writing'], 'category_id' => $category->id, 'related_post_ids' => [$related->id]];
    $outputs['images'] = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context($outputs))->output;
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, $outputs, [], 1);

    $result = app(AssembleGeneratedBlogDraftAction::class)->execute($context);

    $post = BlogPost::query()->with('translations')->findOrFail($result->output['post_id']);
    $translations = $post->translations->keyBy('locale');
    expect($post->status)->toBe(BlogPostStatus::Draft)
        ->and($post->origin)->toBe(BlogPostOrigin::Ai)
        ->and($post->generation_id)->toBe($generation->id)
        ->and($post->blog_category_id)->toBe($category->id)
        ->and($post->featured_image_id)->toBe($outputs['images']['images'][0]['image_id'])
        ->and($post->relatedPosts()->pluck('blog_posts.id')->all())->toBe([$related->id])
        ->and(collect($post->sources)->pluck('url')->all())->toBe(['https://eur-lex.europa.eu/eli/reg/2023/988/oj', 'https://help.etsy.com/hc/en-gb/articles/economic-operator'])
        ->and($translations['fr']->title)->toBe('Ajouter un opérateur économique sur Etsy')
        ->and($translations['fr']->slug)->toBe('ajouter-un-operateur-economique-sur-etsy')
        ->and($translations['es']->slug)->toBe('anadir-un-operador-economico-en-etsy')
        // Liens internes dans la langue de chaque version, image placee a son emplacement.
        ->and($translations['fr']->content_html)->toContain('href="/fr#quiz"')->toContain('href="/fr/pricing"')->toContain('<figure')
        ->and($translations['en']->content_html)->toContain('href="/#quiz"')->not->toContain('[[image')
        ->and($result->findings->postId)->toBe($post->id)
        ->and($result->summary)->toBe('Le brouillon est prêt à relire, dans les trois langues.');
});

it('hands back the draft it already created when the step runs again, without a second draft', function () {
    $generation = BlogGeneration::factory()->running()->create();
    $post = BlogPost::factory()->fromAi()->create(['generation_id' => $generation->id]);
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, textOutputs(), [], 2);

    $result = app(AssembleGeneratedBlogDraftAction::class)->execute($context);

    expect($result->output['post_id'])->toBe($post->id)
        ->and(BlogPost::query()->count())->toBe(1);
});

it('creates no draft when the final title repeats a known article', function () {
    $existing = BlogContent::published('label');
    $existing->translations()->where('locale', 'en')->update(['title' => 'Adding an economic operator on Etsy']);
    $generation = BlogGeneration::factory()->running()->create(['topic_title' => 'Adding an economic operator on Etsy']);
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, textOutputs(), [], 1);

    $result = app(AssembleGeneratedBlogDraftAction::class)->execute($context);

    expect($result->stopReason)->toBe(BlogGenerationOutcomeReason::DuplicateTopic)
        ->and(BlogPost::query()->count())->toBe(1);
});

// --- Suppression d'un brouillon genere

it('marks the topic as rejected when a generated draft is deleted, and only then', function () {
    $generation = BlogGeneration::factory()->completed()->create(['topic_title' => 'Etsy labels']);
    $generated = BlogPost::factory()->fromAi()->create(['generation_id' => $generation->id]);
    $generation->update(['blog_post_id' => $generated->id]);
    $manual = BlogPost::factory()->create();
    $other = BlogGeneration::factory()->completed()->create(['topic_title' => 'Toys']);

    app(DeleteBlogPostAction::class)->execute($manual->id);
    expect($generation->fresh()->topic_rejected_at)->toBeNull();

    app(DeleteBlogPostAction::class)->execute($generated->id);
    expect($generation->fresh()->topic_rejected_at)->not->toBeNull()
        ->and($generation->fresh()->blog_post_id)->toBeNull()
        ->and($other->fresh()->topic_rejected_at)->toBeNull();
});

// --- Pipeline complet

it('runs the whole editorial pipeline through the worker and delivers a draft to review', function () {
    config(['blog.ai.pipeline' => 'editorial']);
    fakePixabay();
    $french = str_replace(['What Etsy asks for', 'What to do next'], ['Ce que demande Etsy', 'La suite'], Fixtures::body());
    WebSearchAgent::fake(['Notes from the web scan.', Fixtures::notesCiting()])->preventStrayPrompts();
    TopicAgent::fake([[...Fixtures::topic(), 'report_fr' => 'Sujet demandé par les vendeurs.']])->preventStrayPrompts();
    ResearchAgent::fake([[...Fixtures::research(), 'report_fr' => 'Deux sources officielles.']])->preventStrayPrompts();
    WriterAgent::fake([Fixtures::writerAnswer()])->preventStrayPrompts();
    FactCheckAgent::fake([[...Fixtures::article(), 'removed_claims' => [], 'report_fr' => 'Tout est appuyé.']])->preventStrayPrompts();
    StyleAgent::fake([[...Fixtures::article(), 'report_fr' => 'Style relu.']])->preventStrayPrompts();
    TranslatorAgent::fake([
        [...Fixtures::article(['title' => 'Ajouter un opérateur économique sur Etsy', 'body_markdown' => $french]), 'image_alts' => [['number' => 0, 'alt' => 'Un colis']], 'report_fr' => 'Version française.'],
        [...Fixtures::article(['title' => 'Añadir un operador económico en Etsy']), 'image_alts' => [['number' => 0, 'alt' => 'Un paquete']], 'report_fr' => 'Versión española.'],
    ])->preventStrayPrompts();
    $generation = BlogGeneration::factory()->create(['payload' => ['images' => ['mode' => 'stock', 'max' => 2]]]);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $generation->refresh();
    $post = BlogPost::query()->sole();
    expect($generation->status)->toBe(BlogGenerationStatus::Completed)
        ->and($generation->steps()->pluck('step')->all())->toBe(['topic', 'research', 'writing', 'verification', 'style', 'translation_fr', 'translation_es', 'images', 'assembly'])
        ->and($generation->topic_title)->toBe('Adding an economic operator on Etsy')
        ->and($generation->blog_post_id)->toBe($post->id)
        ->and($post->generation_id)->toBe($generation->id)
        ->and($post->status)->toBe(BlogPostStatus::Draft)
        ->and($generation->steps()->where('step', 'topic')->value('summary'))->toBe('Sujet demandé par les vendeurs.');
    // L'e-mail de fin de lot donne l'extrait du brouillon et le nombre de ses sources.
    Mail::assertSent(BlogGenerationBatchFinished::class, fn (BlogGenerationBatchFinished $mail): bool => $mail->rows[0]['excerpt'] === Fixtures::article()['excerpt']
        && $mail->rows[0]['sources'] === 2
        && $mail->rows[0]['subject'] === 'Adding an economic operator on Etsy');
});
