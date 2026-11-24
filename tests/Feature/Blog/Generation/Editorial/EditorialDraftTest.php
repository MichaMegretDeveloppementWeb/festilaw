<?php

use App\Actions\Blog\Generation\Editorial\AssembleGeneratedBlogDraftAction;
use App\Actions\Blog\Generation\Editorial\GenerateBlogDraftImagesAction;
use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Post\DeleteBlogPostAction;
use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\PhotoCheckAgent;
use App\Ai\Agents\Blog\ResearchAgent;
use App\Ai\Agents\Blog\StyleAgent;
use App\Ai\Agents\Blog\TopicAgent;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Ai\Agents\Blog\VersionCheckAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Ai\Agents\Blog\WriterAgent;
use App\Contracts\Blog\Generation\StockImageProvider;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\GenerationStepResult;
use App\Enums\Blog\BlogImageCreditSource;
use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Exceptions\Blog\BlogEditorialException;
use App\Exceptions\Blog\BlogImageException;
use App\Mail\BlogGenerationBatchFinished;
use App\Mail\BlogMonthlySpendWarning;
use App\Models\BlogCategory;
use App\Models\BlogGeneration;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Repositories\Blog\BlogGeneratedDraftRepository;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\Editorial\Steps\StyleStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Sleep;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Image;
use Tests\Support\BlogAi;
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
    // Les sources non decoupees vont sur le disque prive (audit du 23/11).
    Storage::fake('local');
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

/**
 * La banque d'images simulee, et le controle des photos : par defaut, il accepte chaque photo et la decrit.
 *
 * @param  array<string, mixed>|null  $hits  la reponse de l'API, celle de pixabayHits() par defaut
 */
function fakePixabay(?array $hits = null, bool $checkAccepts = true): void
{
    $photo = file_get_contents(BlogImageFiles::jpeg(1280, 853));

    Http::fake([
        'https://pixabay.com/api/*' => Http::response($hits ?? pixabayHits()),
        'https://pixabay.com/get/*' => Http::response($photo, 200, ['Content-Type' => 'image/jpeg']),
        '*' => Http::response('ok'),
    ]);

    if ($checkAccepts) {
        PhotoCheckAgent::fake(fn (): array => photoVerdict(true))->preventStrayPrompts();
    }
}

/** @return array<string, mixed> la reponse du controle des photos */
function photoVerdict(bool $fits, string $alt = 'A brown parcel on a wooden table'): array
{
    return [
        'fits' => $fits,
        'reason' => $fits ? 'A parcel suits an article on shipping.' : 'Tools do not show the subject.',
        'alt_en' => $alt,
        'alt_fr' => 'Un colis brun sur une table en bois',
        'alt_es' => 'Un paquete marrón sobre una mesa de madera',
    ];
}

/** Les sorties des etapes de texte, comme le journal les garde a l'arrivee des images. */
function textOutputs(): array
{
    $french = str_replace(['The details Etsy asks for', 'Gather the details first'], ['Les informations demandées par Etsy', 'Les informations à rassembler'], Fixtures::body());

    return [
        'topic' => Fixtures::topic(),
        'research' => Fixtures::research(),
        'writing' => Fixtures::writingOutput(),
        'style' => Fixtures::articleOutput(),
        'translation_fr' => ['article' => [...Fixtures::articleOutput($french)['article'], 'title' => 'Ajouter un opérateur économique sur Etsy'], 'image_alts' => ['0' => 'Un colis sur un établi', '1' => 'Une étiquette sur un carton']],
        'translation_es' => ['article' => [...Fixtures::articleOutput()['article'], 'title' => 'Añadir un operador económico en Etsy'], 'image_alts' => ['0' => 'Un paquete sobre un banco']],
        'version_check_fr' => ['article' => [...Fixtures::articleOutput($french)['article'], 'title' => 'Ajouter un opérateur économique sur Etsy'], 'corrections' => []],
        'version_check_es' => ['article' => [...Fixtures::articleOutput()['article'], 'title' => 'Añadir un operador económico en Etsy'], 'corrections' => []],
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
        // Le texte alternatif decrit la photo trouvee, tel que le controle l'a vue, et non l'image voulue (Micha, 20/11).
        ->and($images[0]->alt)->toBe(['en' => 'A brown parcel on a wooden table', 'fr' => 'Un colis brun sur une table en bois', 'es' => 'Un paquete marrón sobre una mesa de madera'])
        ->and($images[0]->hasShareVariant())->toBeTrue()
        // La photo trop petite pour l'image principale convient dans le texte, et ce n'est pas la meme photo.
        ->and($images[1]->credit_author)->toBe('Small')
        ->and($images[1]->blog_post_id)->toBeNull()
        ->and($result->output['images'])->toHaveCount(2)
        ->and($result->summary)->toBe('2 photos de banque ont été choisies, avec le nom de leur auteur.');
    // La recherche est gardee 24 heures : la meme requete ne repart pas chez Pixabay.
    Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://pixabay.com/api/') && $request['safesearch'] === 'true' && $request['orientation'] === 'horizontal');
});

it('downloads a stock photo from the Pixabay CDN, which the hosting can reach, rather than the address Cloudflare limits', function () {
    // Production, 24/11 : pixabay.com/get/ repondait 429 a l'hebergement, quelle que soit la requete ; le meme
    // fichier en 1280 px se trouve a cote de l'apercu, sur cdn.pixabay.com.
    $photo = file_get_contents(BlogImageFiles::jpeg(1280, 853));
    Http::fake([
        'https://pixabay.com/api/*' => Http::response(['hits' => [[
            'id' => 2, 'pageURL' => 'https://pixabay.com/photos/parcel-2/', 'user' => 'Anna', 'imageWidth' => 4000, 'imageHeight' => 2667,
            'previewURL' => 'https://cdn.pixabay.com/photo/2017/01/01/00/00/parcel-2_150.jpg',
            'largeImageURL' => 'https://pixabay.com/get/parcel_1280.jpg',
        ]]]),
        'https://cdn.pixabay.com/*' => Http::response($photo, 200, ['Content-Type' => 'image/jpeg']),
        'https://pixabay.com/get/*' => Http::response('Rate limit exceeded', 429),
        '*' => Http::response('ok'),
    ]);
    PhotoCheckAgent::fake(fn (): array => photoVerdict(true))->preventStrayPrompts();

    app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    expect(BlogImage::query()->first()?->credit_author)->toBe('Anna');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://cdn.pixabay.com/photo/2017/01/01/00/00/parcel-2_1280.jpg');
    Http::assertNotSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://pixabay.com/get/'));
});

it('reports as an error, for the morning report, an image for which no stock photo could be downloaded', function () {
    // Le 24/11, chaque telechargement repondait 429 et seuls des avertissements le disaient.
    Log::spy();
    Http::fake([
        'https://pixabay.com/api/*' => Http::response(pixabayHits()),
        'https://pixabay.com/get/*' => Http::response('Rate limit exceeded', 429),
        '*' => Http::response('ok'),
    ]);
    PhotoCheckAgent::fake(fn (): array => photoVerdict(true))->preventStrayPrompts();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    expect(BlogImage::query()->count())->toBe(0)
        ->and($result->summary)->toBe('La banque de photos n\'a pas répondu, aucune photo n\'a pu être choisie. Il manque l\'image principale, à ajouter avant de publier.');
    Log::shouldHaveReceived('error')->with('Blog generation stock photos could not be downloaded.', Mockery::any())->twice();
    PhotoCheckAgent::assertNeverPrompted();
});

it('shows each stock photo to the check with the article and the wanted image, and sets aside the ones that do not fit', function () {
    // Remarque de Micha du 20/11 : des outils sur un etabli illustraient un article sur les jouets.
    fakePixabay(checkAccepts: false);
    $answers = [photoVerdict(false), photoVerdict(true, 'A wooden toy car beside a cardboard box'), photoVerdict(true)];
    PhotoCheckAgent::fake(function () use (&$answers): array {
        return array_shift($answers);
    })->preventStrayPrompts();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    $images = BlogImage::query()->orderBy('id')->get();
    // La premiere photo assez grande (Anna) est ecartee, la suivante (Ben) illustre l'article, decrite telle qu'elle est.
    expect($images[0]->credit_author)->toBe('Ben')
        ->and($images[0]->alt['en'])->toBe('A wooden toy car beside a cardboard box')
        ->and($images->pluck('credit_author')->all())->not->toContain('Anna')
        ->and($result->output['images'])->toHaveCount(2);
    PhotoCheckAgent::assertPrompted(fn ($prompt): bool => $prompt->attachments->count() === 1
        && $prompt->contains('A parcel on a workbench')
        && $prompt->contains('Adding an economic operator on Etsy')
        && str_contains($prompt->agent->instructions(), 'Look at the photo itself, not at what was asked for'));
});

it('looks at four photos at most per search, and delivers the draft without a main image when none fits', function () {
    $hits = ['hits' => array_map(static fn (int $id): array => [
        'id' => $id, 'pageURL' => "https://pixabay.com/photos/tools-{$id}/", 'largeImageURL' => "https://pixabay.com/get/tools_{$id}_1280.jpg", 'imageWidth' => 4000, 'imageHeight' => 2667, 'user' => "Author {$id}",
    ], range(1, 6))];
    fakePixabay($hits, checkAccepts: false);
    $checked = 0;
    PhotoCheckAgent::fake(function () use (&$checked): array {
        $checked++;

        return photoVerdict(false);
    })->preventStrayPrompts();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    // Quatre photos pour la recherche de la redaction, les deux restantes pour les mots-cles du sujet ; aucune
    // nouvelle pour l'image du texte, toutes ayant deja ete regardees.
    expect($checked)->toBe(6)
        ->and(BlogImage::query()->count())->toBe(0)
        ->and($result->output['warnings'])->toBe(['no main image', 'no image 1'])
        ->and($result->summary)->toBe('Aucune photo de banque ne convenait. Il manque l\'image principale, à ajouter avant de publier.');
});

it('says the server could not store the images, and reports it, instead of blaming the photo bank', function () {
    // Audit du 23/11 : un disque plein se faisait passer pour « aucune photo ne convenait », en warning.
    fakePixabay();
    BlogImageFiles::failingDisk(failWrites: true);
    Log::spy();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    expect(BlogImage::query()->count())->toBe(0)
        ->and($result->output['causes'][0])->toBe('storage_failed')
        ->and($result->summary)->toBe("Les images n'ont pas pu être enregistrées sur le serveur. Il manque l'image principale, à ajouter avant de publier.");
    Log::shouldHaveReceived('log')->withArgs(fn (string $level, string $message): bool => $level === 'error' && $message === 'Blog generation image could not be stored.');
});

it('does not take a photo it could not check, goes on with the draft, and stops everything when the credit runs out', function () {
    fakePixabay(checkAccepts: false);
    Log::spy();
    // Une reponse vide : le controle n'a pas pu se faire.
    PhotoCheckAgent::fake([[], [], []])->preventStrayPrompts();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    expect(BlogImage::query()->count())->toBe(0)
        ->and($result->output['warnings'])->toBe(['no main image', 'no image 1'])
        // Controle du 20/11 : le resume dit pourquoi la photo manque.
        ->and($result->output['causes'])->toBe([0 => 'check_failed', 1 => 'check_failed'])
        ->and($result->summary)->toBe('Les photos trouvées n\'ont pas pu être contrôlées, aucune n\'a été prise. Il manque l\'image principale, à ajouter avant de publier.');
    Log::shouldHaveReceived('warning')->with('Blog generation stock photo could not be checked, so it is not used.', Mockery::any())->atLeast()->once();

    PhotoCheckAgent::fake(fn () => throw InsufficientCreditsException::forProvider('openrouter', 402));

    expect(fn () => app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs())))
        ->toThrow(fn (BlogAiProviderException $e) => expect($e->creditExhausted)->toBeTrue());
});

it('delivers the draft without a main image when no stock photo fits, and says so', function () {
    Http::fake(['https://pixabay.com/api/*' => Http::response(['hits' => []]), '*' => Http::response('ok')]);

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    expect(BlogImage::query()->count())->toBe(0)
        ->and($result->output['warnings'])->toBe(['no main image', 'no image 1'])
        ->and($result->output['causes'])->toBe([0 => 'none_fit', 1 => 'none_fit'])
        ->and($result->summary)->toBe('Aucune photo de banque ne convenait. Il manque l\'image principale, à ajouter avant de publier.');
});

it('says when the image bank did not answer, logs it as an error for the morning report, and delivers the draft without photo', function () {
    // Controle du 20/11 : une cle Pixabay revoquee passait pour « aucune photo ne convenait », sans alerte.
    Http::fake(['https://pixabay.com/api/*' => Http::response(['error' => '[ERROR 400] Invalid or missing API key'], 400), '*' => Http::response('ok')]);
    Log::spy();

    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs()));

    expect(BlogImage::query()->count())->toBe(0)
        ->and($result->output['causes'])->toBe([0 => 'bank_unavailable', 1 => 'bank_unavailable'])
        ->and($result->summary)->toBe('La banque de photos n\'a pas répondu, aucune photo n\'a pu être choisie. Il manque l\'image principale, à ajouter avant de publier.');
    Log::shouldHaveReceived('error')->with('Blog generation stock photos could not be searched.', Mockery::any())->twice();
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

it('does not ask for a generated image whose reserve no longer fits before the safety stop, and delivers the draft without it', function () {
    Image::fake()->preventStrayImages();

    // GPT-5.4 Image 2 reserve 0,06 $ ; il n'en reste que 0,02 $.
    $result = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context(textOutputs(), payload: ['images' => ['mode' => 'generated', 'max' => 1]], budgetLeft: 20_000));

    expect(BlogImage::query()->count())->toBe(0)
        ->and($result->output['warnings'])->toBe(['no main image']);
    Image::assertNothingGenerated();
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

/** Ce que l'assemblage recoit quand la redaction a propose une categorie nouvelle, nommee en francais seulement. */
function outputsWithNewCategory(string $name = 'Product safety'): array
{
    $outputs = textOutputs();
    $outputs['writing'] = [...$outputs['writing'], 'category_id' => null, 'new_category' => $name];
    $outputs['translation_fr']['category_name'] = 'Sécurité des produits';
    $outputs['translation_es']['category_name'] = null;
    $outputs['images'] = app(GenerateBlogDraftImagesAction::class)->execute(Fixtures::context($outputs))->output;

    return $outputs;
}

it('creates the category the writer proposed, in the three languages, marked as created by the generation, and says so', function () {
    fakePixabay();
    $generation = BlogGeneration::factory()->running()->create();
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, outputsWithNewCategory(), [], 1);

    $result = app(AssembleGeneratedBlogDraftAction::class)->execute($context);

    $post = BlogPost::query()->findOrFail($result->output['post_id']);
    $category = BlogCategory::query()->with('translations')->findOrFail($post->blog_category_id);
    expect($category->generation_id)->toBe($generation->id)
        ->and($category->translationFor('en')->name)->toBe('Product safety')
        ->and($category->translationFor('fr')->name)->toBe('Sécurité des produits')
        ->and($category->translationFor('fr')->slug)->toBe('securite-des-produits')
        // Sans nom espagnol, le nom anglais le remplace : Laetitia pourra le renommer.
        ->and($category->translationFor('es')->name)->toBe('Product safety')
        ->and($result->summary)->toBe('Le brouillon est prêt à relire, dans les trois langues. Nouvelle catégorie « Sécurité des produits » créée pour cet article.')
        ->and(app(BlogGeneratedDraftRepository::class)->summariesOf([$post->id])[$post->id]['newCategory'])->toBe('Sécurité des produits');

    // Rejouee, l'etape rend le meme brouillon, sans seconde categorie.
    app(AssembleGeneratedBlogDraftAction::class)->execute($context);
    expect(BlogCategory::query()->count())->toBe(1);
});

it('takes back a category of the same name created in the meantime instead of a second one', function () {
    fakePixabay();
    $existing = BlogContent::category('safety');
    $generation = BlogGeneration::factory()->running()->create();
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, outputsWithNewCategory('SAFETY EN'), [], 1);

    $result = app(AssembleGeneratedBlogDraftAction::class)->execute($context);

    expect(BlogPost::query()->findOrFail($result->output['post_id'])->blog_category_id)->toBe($existing->id)
        ->and(BlogCategory::query()->count())->toBe(1)
        ->and($result->summary)->toBe('Le brouillon est prêt à relire, dans les trois langues.');
});

it('leaves no category behind when the draft cannot be saved', function () {
    fakePixabay();
    $generation = BlogGeneration::factory()->running()->create();
    $outputs = outputsWithNewCategory();
    $outputs['images'] = ['images' => [['number' => 0, 'image_id' => 999_999]]];
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, $outputs, [], 1);

    expect(fn () => app(AssembleGeneratedBlogDraftAction::class)->execute($context))->toThrow(BlogImageException::class)
        ->and(BlogCategory::query()->count())->toBe(0)
        ->and(BlogPost::query()->count())->toBe(0);
});

it('deletes with an AI draft the category its generation created for it, once no other article uses it, never a category created by hand', function () {
    $generation = BlogGeneration::factory()->create();
    $created = BlogCategory::factory()->withTranslations()->create(['generation_id' => $generation->id]);
    $byHand = BlogCategory::factory()->withTranslations()->create();
    $draft = BlogPost::factory()->fromAi()->create(['generation_id' => $generation->id, 'blog_category_id' => $created->id]);
    $other = BlogPost::factory()->fromAi()->create(['generation_id' => BlogGeneration::factory()->create()->id, 'blog_category_id' => $byHand->id]);

    app(DeleteBlogPostAction::class)->execute($draft->id);
    app(DeleteBlogPostAction::class)->execute($other->id);

    expect(BlogCategory::query()->pluck('id')->all())->toBe([$byHand->id]);

    // Employee aussi par un autre article, la categorie creee par la generation reste.
    $kept = BlogCategory::factory()->withTranslations()->create(['generation_id' => $generation->id]);
    $secondDraft = BlogPost::factory()->fromAi()->create(['generation_id' => $generation->id, 'blog_category_id' => $kept->id]);
    BlogPost::factory()->create(['blog_category_id' => $kept->id]);

    app(DeleteBlogPostAction::class)->execute($secondDraft->id);

    expect(BlogCategory::query()->whereKey($kept->id)->exists())->toBeTrue();
});

it('hands back the draft it already created when the step runs again, without a second draft', function () {
    $generation = BlogGeneration::factory()->running()->create();
    $post = BlogPost::factory()->fromAi()->create(['generation_id' => $generation->id]);
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, textOutputs(), [], 2);

    $result = app(AssembleGeneratedBlogDraftAction::class)->execute($context);

    expect($result->output['post_id'])->toBe($post->id)
        ->and(BlogPost::query()->count())->toBe(1);
});

/**
 * L'etape du style sur l'article verifie des fixtures (« Adding an economic operator on Etsy »), pour une
 * generation donnee : depuis le controle du 20/11, c'est elle qui controle le titre final, avant les traductions
 * et les images.
 */
function styleStepFor(BlogGeneration $generation, string $kind = 'evergreen', ?string $topicHint = null): GenerationStepResult
{
    StyleAgent::fake(fn (): array => [...Fixtures::article(), 'report_fr' => 'Style revu.'])->preventStrayPrompts();
    $outputs = ['verification' => Fixtures::articleOutput(), 'topic' => [...Fixtures::topic(), 'kind' => $kind]];

    return app(StyleStep::class)->execute(new GenerationStepContext($generation->id, BlogGenerationType::Article, $topicHint, null, Fixtures::context()->settings, $outputs, [], 1));
}

it('stops before the translations and the images when the final title repeats a known article', function () {
    $existing = BlogContent::published('label');
    $existing->translations()->where('locale', 'en')->update(['title' => 'Adding an economic operator on Etsy']);
    $generation = BlogGeneration::factory()->running()->create(['topic_title' => 'Adding an economic operator on Etsy']);

    $result = styleStepFor($generation);

    expect($result->stopReason)->toBe(BlogGenerationOutcomeReason::DuplicateTopic)
        ->and($result->output['duplicate_of'])->toBe('Adding an economic operator on Etsy')
        ->and($result->summary)->toBe('Le titre final reprenait « Adding an economic operator on Etsy », déjà traité ou refusé, donc l\'article s\'arrête avant ses traductions et ses images.');
});

it('lets a news article whose title takes up a covered subject go on, to say what changed', function () {
    // Decision de Micha du 19/11 : l'ancien article reste tel quel, le nouveau annonce le changement.
    $existing = BlogContent::published('label');
    $existing->translations()->where('locale', 'en')->update(['title' => 'Economic operator on Etsy']);
    $generation = BlogGeneration::factory()->running()->create();

    expect(styleStepFor($generation, 'evergreen')->stopReason)->toBe(BlogGenerationOutcomeReason::DuplicateTopic)
        ->and(styleStepFor($generation, 'news')->stopsGeneration())->toBeFalse();
});

it('lets an imposed topic go on even when its final title repeats a known article', function () {
    $existing = BlogContent::published('label');
    $existing->translations()->where('locale', 'en')->update(['title' => 'Adding an economic operator on Etsy']);
    $generation = BlogGeneration::factory()->running()->create(['topic_hint' => 'Etsy economic operator']);

    expect(styleStepFor($generation, topicHint: 'Etsy economic operator')->stopsGeneration())->toBeFalse();
});

it('no longer stops at the assembly on a repeated title, which the style step already checked', function () {
    $existing = BlogContent::published('label');
    $existing->translations()->where('locale', 'en')->update(['title' => 'Adding an economic operator on Etsy']);
    $generation = BlogGeneration::factory()->running()->create(['topic_title' => 'Adding an economic operator on Etsy']);
    $context = new GenerationStepContext($generation->id, BlogGenerationType::Article, null, null, Fixtures::context()->settings, textOutputs(), [], 1);

    $result = app(AssembleGeneratedBlogDraftAction::class)->execute($context);

    expect($result->stopsGeneration())->toBeFalse()
        ->and(BlogPost::query()->count())->toBe(2);
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

/** Chaque agent du pipeline editorial repond, comme pour un article qui va au bout. */
function fakeWholeEditorialPipeline(): void
{
    config(['blog.ai.pipeline' => 'editorial']);
    fakePixabay();
    $french = str_replace(['The details Etsy asks for', 'Gather the details first'], ['Les informations demandées par Etsy', 'Les informations à rassembler'], Fixtures::body());
    WebSearchAgent::fake(['Notes from the web scan.', Fixtures::notesCiting(), 'Etsy economic operator field : CONFIRMED, https://help.etsy.com/x'])->preventStrayPrompts();
    TopicAgent::fake([[...Fixtures::topic(), 'report_fr' => 'Sujet demandé par les vendeurs.']])->preventStrayPrompts();
    ResearchAgent::fake([[...Fixtures::research(), 'report_fr' => 'Deux sources officielles.']])->preventStrayPrompts();
    // Le texte d'exemple est plus court que les 1 000 mots par defaut : la redaction demande une correction.
    WriterAgent::fake([Fixtures::writerAnswer(), Fixtures::writerAnswer()])->preventStrayPrompts();
    FactCheckAgent::fake([[...Fixtures::article(), 'removed_claims' => [], 'report_fr' => 'Tout est appuyé.']])->preventStrayPrompts();
    StyleAgent::fake([[...Fixtures::article(), 'report_fr' => 'Style relu.']])->preventStrayPrompts();
    VersionCheckAgent::fake([['corrections' => [], 'report_fr' => 'Version conforme.'], ['corrections' => [], 'report_fr' => 'Versión conforme.']])->preventStrayPrompts();
    TranslatorAgent::fake([
        [...Fixtures::article(['title' => 'Ajouter un opérateur économique sur Etsy', 'body_markdown' => $french]), 'image_alts' => [['number' => 0, 'alt' => 'Un colis']], 'report_fr' => 'Version française.'],
        [...Fixtures::article(['title' => 'Añadir un operador económico en Etsy']), 'image_alts' => [['number' => 0, 'alt' => 'Un paquete']], 'report_fr' => 'Versión española.'],
    ])->preventStrayPrompts();
}

it('runs the whole editorial pipeline through the worker and delivers a draft to review', function () {
    fakeWholeEditorialPipeline();
    $generation = BlogGeneration::factory()->create(['payload' => ['images' => ['mode' => 'stock', 'max' => 2]]]);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $generation->refresh();
    $post = BlogPost::query()->sole();
    expect($generation->status)->toBe(BlogGenerationStatus::Completed)
        ->and($generation->steps()->pluck('step')->all())->toBe(['topic_scan', 'topic', 'research_notes', 'research', 'writing', 'fact_search', 'verification', 'style', 'translation_fr', 'version_check_fr', 'translation_es', 'version_check_es', 'images', 'assembly'])
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

it('stops by safety before a call that could pass twice the announced cost, without sending it', function () {
    // Sans plancher : celui de 2 centimes (19/11) laisserait passer la reserve de 0,014 $ de cet essai.
    config(['blog.ai.pipeline' => 'editorial', 'blog.ai.safety_stop_floor_microusd' => 0]);
    WebSearchAgent::fake()->preventStrayPrompts();
    $generation = BlogGeneration::factory()->create(['forecast_low_microusd' => 3_000, 'forecast_high_microusd' => 5_000]);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    // La veille reserve deux recherches a 0,007 $ : plus que l'arret de securite de 0,01 $. Au 17/11, la limite ne
    // se verifiait qu'entre deux essais, et un seul essai de recherche a coute 0,1078 $.
    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Abandoned)
        ->and($generation->outcome_reason)->toBe(BlogGenerationOutcomeReason::SafetyStopBeforeCall)
        ->and($generation->cost_microusd)->toBe(0);
    WebSearchAgent::assertNeverPrompted();
});

it('runs a generation to the end above the monthly cap, which only warns, then says the cap is passed', function () {
    fakeWholeEditorialPipeline();
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100, 'notification_email' => 'owner@example.com']);
    BlogGeneration::factory()->completed(costMicrousd: 1_200_000)->create();
    $generation = BlogGeneration::factory()->create(['payload' => ['images' => ['mode' => 'stock', 'max' => 2]]]);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Completed);
    Mail::assertSent(BlogMonthlySpendWarning::class, fn (BlogMonthlySpendWarning $mail): bool => $mail->over && $mail->hasTo('owner@example.com'));
});
