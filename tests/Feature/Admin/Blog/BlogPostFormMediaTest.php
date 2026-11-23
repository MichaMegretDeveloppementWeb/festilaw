<?php

declare(strict_types=1);

use App\Actions\Blog\Image\UpdateBlogImageAltAction;
use App\Exceptions\Blog\BlogImageException;
use App\Livewire\Admin\Blog\PostForm;
use App\Models\BlogImage;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\Support\BlogContent;
use Tests\Support\BlogImageFiles;

use function Pest\Laravel\actingAs;

/*
 | Formulaire d'article, etape B : images (envoi, image principale et de partage, descriptions) et
 | articles lies. L'editeur lui-meme vit dans le navigateur : il est rejoue par le parcours
 | captures/outil/admin-check.mjs, pas ici.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
    actingAs(User::factory()->create());
});

afterEach(function () {
    BlogImageFiles::cleanup();
});

/** Un fichier image tel que le navigateur l'envoie. */
function uploadedImage(int $width, int $height): UploadedFile
{
    return UploadedFile::fake()->createWithContent('photo de vacances.jpg', (string) file_get_contents(BlogImageFiles::jpeg($width, $height)));
}

describe('sending an image', function () {
    it('stores the main image, waiting for the article to be saved, and hands its preview to the browser', function () {
        $form = Livewire::test(PostForm::class)
            ->set('texts.fr.title', 'Étiquette produit')
            ->set('imageUpload', uploadedImage(1600, 900))
            ->call('storeImage', 'featured')
            ->assertSet('imageUpload', null);

        $image = BlogImage::query()->sole();

        $form->assertSet('featuredImageId', $image->id)
            ->assertSet('shareImageId', null)
            ->assertReturned(fn (array $summary): bool => $summary['id'] === $image->id
                && str_contains((string) $summary['url'], $image->uuid)
                && $summary['alt'] === ['en' => '', 'fr' => '', 'es' => '']);

        // En attente : c'est l'enregistrement de l'article qui la rattache. Le nom vient du titre, jamais du fichier.
        expect($image->blog_post_id)->toBeNull()
            ->and($image->basename)->toBe('etiquette-produit')
            ->and($image->hasShareVariant())->toBeTrue();
    });

    it('attaches the image to the article when the article is saved', function () {
        Livewire::test(PostForm::class)
            ->set('texts.fr.title', 'Un brouillon illustré')
            ->set('imageUpload', uploadedImage(1600, 900))
            ->call('storeImage', 'featured')
            ->call('save', ['en' => null, 'fr' => BlogContent::document(), 'es' => null]);

        $post = BlogPost::query()->sole();
        $image = BlogImage::query()->sole();

        expect($post->featured_image_id)->toBe($image->id)
            ->and($image->blog_post_id)->toBe($post->id);
    });

    it('stores the sharing image and an image of the text without touching the main one', function () {
        $post = BlogContent::draft('pending');
        $featuredId = $post->featured_image_id;

        $form = Livewire::test(PostForm::class, ['post' => $post->id])
            ->set('imageUpload', uploadedImage(1200, 630))
            ->call('storeImage', 'share');

        $share = BlogImage::query()->latest('id')->first();

        $form->assertSet('shareImageId', $share->id)
            ->assertSet('featuredImageId', $featuredId)
            ->set('imageUpload', uploadedImage(600, 400))
            ->call('storeImage', 'content')
            ->assertSet('shareImageId', $share->id)
            ->assertSet('featuredImageId', $featuredId)
            ->assertReturned(fn (array $summary): bool => $summary['id'] !== $share->id && $summary['url'] !== null);
    });

    it('refuses a main image that is too small, says why, and keeps nothing', function () {
        Log::spy();

        Livewire::test(PostForm::class)
            ->set('imageUpload', uploadedImage(800, 400))
            ->call('storeImage', 'featured')
            ->assertReturned(null)
            ->assertSet('featuredImageId', null)
            ->assertSet('imageUpload', null)
            ->assertDispatched('ui-toast', fn (string $event, array $params): bool => $params['type'] === 'error'
                && str_contains($params['title'], '800 × 400')
                && str_contains($params['title'], '1200 × 630')
                && $params['life'] > 4000);

        expect(BlogImage::query()->count())->toBe(0);
    });

    it('refuses a file that is not an image', function () {
        Log::spy();

        Livewire::test(PostForm::class)
            ->set('imageUpload', UploadedFile::fake()->createWithContent('faux.jpg', (string) file_get_contents(BlogImageFiles::fakeJpeg())))
            ->call('storeImage', 'content')
            ->assertReturned(null)
            ->assertDispatched('ui-toast', type: 'error');

        expect(BlogImage::query()->count())->toBe(0);
    });

    it('says so when no file arrived, or when the usage is unknown', function () {
        Livewire::test(PostForm::class)
            ->call('storeImage', 'featured')
            ->assertReturned(null)
            ->assertDispatched('ui-toast', type: 'error')
            ->set('imageUpload', uploadedImage(1600, 900))
            ->call('storeImage', 'wallpaper')
            ->assertReturned(null)
            ->assertSet('imageUpload', null);

        expect(BlogImage::query()->count())->toBe(0);
    });

    it('removes the main image or the sharing image from the article at the next save', function () {
        $post = BlogContent::draft('pending');
        $share = BlogImage::factory()->withShare()->create(['blog_post_id' => $post->id]);
        $post->update(['share_image_id' => $share->id]);

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->call('removeShareImage')
            ->assertSet('shareImageId', null)
            ->call('removeFeaturedImage')
            ->assertSet('featuredImageId', null)
            ->call('save', BlogContent::contentsOf($post))
            ->assertHasNoErrors();

        expect($post->fresh()->featured_image_id)->toBeNull()
            ->and($post->fresh()->share_image_id)->toBeNull()
            // Detachee : la purge planifiee la supprimera apres le delai de grace.
            ->and($share->fresh()->blog_post_id)->toBeNull();
    });

    it('hands the images of the article to the browser once, when the form opens', function () {
        $post = BlogContent::draft('pending');

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->assertViewHas('initialImages', fn (array $images): bool => array_keys($images) === [$post->featured_image_id]
                && $images[$post->featured_image_id]['url'] !== null
                && array_keys($images[$post->featured_image_id]['alt']) === ['en', 'fr', 'es'])
            ->call('saveSource', null, 'Source', 'https://example.org')
            ->assertViewHas('initialImages', null);
    });
});

describe('framing an image of the text', function () {
    it('cuts the image of the text to the chosen rectangle, describes it, and hands its source to the browser', function () {
        Livewire::test(PostForm::class)
            ->set('imageUpload', uploadedImage(2000, 1000))
            ->call('storeImage', 'content', ['x' => 500, 'y' => 100, 'width' => 1000, 'height' => 800], ['fr' => 'Une étiquette CE', 'de' => 'ignoriert'])
            ->assertSet('imageUpload', null)
            ->assertReturned(fn (array $summary): bool => $summary['url'] !== null
                && str_ends_with((string) $summary['source'], '-source.webp')
                && $summary['crop'] === ['x' => 500, 'y' => 100, 'width' => 1000, 'height' => 800]
                && $summary['alt']['fr'] === 'Une étiquette CE');

        $image = BlogImage::query()->sole();

        expect([$image->width, $image->height])->toBe([1000, 800])
            ->and($image->alt)->toBe(['fr' => 'Une étiquette CE'])
            ->and($image->hasSource())->toBeTrue();
    });

    it('never cuts the main image, whatever the browser sends', function () {
        Livewire::test(PostForm::class)
            ->set('imageUpload', uploadedImage(1600, 900))
            ->call('storeImage', 'featured', ['x' => 0, 'y' => 0, 'width' => 1300, 'height' => 700])
            ->assertReturned(fn (array $summary): bool => $summary['crop'] === null);

        $image = BlogImage::query()->sole();

        expect([$image->width, $image->height])->toBe([1600, 900])->and($image->hasSource())->toBeFalse();
    });

    it('refuses a kept area that is too small, says why, and keeps nothing', function () {
        Log::spy();

        Livewire::test(PostForm::class)
            ->set('imageUpload', uploadedImage(2000, 1000))
            ->call('storeImage', 'content', ['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100])
            ->assertReturned(null)
            ->assertSet('imageUpload', null)
            ->assertDispatched('ui-toast', fn (string $event, array $params): bool => $params['type'] === 'error'
                && str_contains($params['title'], '200 × 120'));

        expect(BlogImage::query()->count())->toBe(0);
    });

    it('frames a placed image again by handing a new image to the browser, the first one staying as it is', function () {
        $form = Livewire::test(PostForm::class)
            ->set('imageUpload', uploadedImage(2000, 1000))
            ->call('storeImage', 'content', null, ['en' => 'A CE label']);

        $first = BlogImage::query()->sole();

        $form->call('recropImage', $first->id, ['x' => 1000, 'y' => 200, 'width' => 800, 'height' => 600])
            ->assertReturned(fn (array $summary): bool => $summary['id'] !== $first->id
                && $summary['crop'] === ['x' => 1000, 'y' => 200, 'width' => 800, 'height' => 600]
                && $summary['alt']['en'] === 'A CE label'
                && $summary['source'] !== null);

        expect(BlogImage::query()->count())->toBe(2)
            // La premiere image garde sa taille (sa plus grande variante fait 1600 px) et son cadrage entier.
            ->and([$first->fresh()->width, $first->fresh()->height])->toBe([1600, 800])
            ->and($first->fresh()->crop)->toBe(['x' => 0, 'y' => 0, 'width' => 2000, 'height' => 1000]);
    });

    it('refuses to frame again without a rectangle, an image of another article, or an image whose file is gone', function () {
        Log::spy();
        $post = BlogContent::draft('pending');
        $foreign = BlogImage::factory()->create(['blog_post_id' => BlogContent::draft('other')->id, 'variants' => ['widths' => [480], 'share' => false, 'source' => true]]);
        $withoutSource = BlogImage::factory()->create(['blog_post_id' => $post->id]);
        $rectangle = ['x' => 0, 'y' => 0, 'width' => 800, 'height' => 600];
        $count = BlogImage::query()->count();

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->call('recropImage', $withoutSource->id, ['x' => 0, 'y' => 0])
            ->assertReturned(null)
            ->assertDispatched('ui-toast', type: 'error')
            ->call('recropImage', $foreign->id, $rectangle)
            ->assertReturned(null)
            ->call('recropImage', $withoutSource->id, $rectangle)
            ->assertReturned(null)
            ->call('recropImage', 999_999, $rectangle)
            ->assertReturned(null);

        expect(BlogImage::query()->count())->toBe($count);
    });

    it('hands the source and the framing of the images of the text when the form opens, in the same three queries', function () {
        $post = BlogContent::draft('pending');
        $image = BlogImage::factory()->create([
            'blog_post_id' => $post->id,
            'variants' => ['widths' => [480, 768], 'share' => false, 'source' => true],
            'crop' => ['x' => 10, 'y' => 20, 'width' => 800, 'height' => 600],
        ]);

        // Une image sans source gardee se recadre a partir de sa plus grande variante, cadre sur toute l'image.
        $older = BlogImage::factory()->create(['blog_post_id' => $post->id]);

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->assertViewHas('initialImages', fn (array $images): bool => str_ends_with((string) $images[$image->id]['source'], '-source.webp')
                && $images[$image->id]['crop'] === ['x' => 10, 'y' => 20, 'width' => 800, 'height' => 600]
                && str_ends_with((string) $images[$older->id]['source'], '-1600.webp')
                && $images[$older->id]['crop'] === null);
    });
});

describe('describing an image', function () {
    it('saves the three descriptions at once and keeps the credit of the image', function () {
        $post = BlogContent::draft('pending');
        $image = BlogImage::query()->findOrFail($post->featured_image_id);
        $image->update(['credit_author' => 'Jane Doe', 'credit_url' => 'https://example.com/photo']);

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->call('saveImageAlt', $image->id, ['en' => '  A label on a box ', 'fr' => 'Une étiquette sur un carton', 'es' => '', 'de' => 'Ein Etikett'])
            ->assertReturned(['en' => 'A label on a box', 'fr' => 'Une étiquette sur un carton', 'es' => ''])
            ->assertDispatched('ui-toast', type: 'success');

        expect($image->fresh()->alt)->toBe(['en' => 'A label on a box', 'fr' => 'Une étiquette sur un carton'])
            ->and($image->fresh()->credit_author)->toBe('Jane Doe')
            ->and($image->fresh()->credit_url)->toBe('https://example.com/photo');
    });

    it('rewrites the saved page of the article when the described image sits in its text', function () {
        $post = BlogContent::draft('pending');
        $inline = BlogImage::factory()->create();
        $contents = BlogContent::contentsOf($post);
        $contents['fr']['content'][] = ['type' => 'blogImage', 'attrs' => ['imageId' => $inline->id]];

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->call('save', $contents)
            ->assertHasNoErrors()
            ->call('saveImageAlt', $inline->id, ['en' => '', 'fr' => 'Un schéma du parcours', 'es' => ''])
            ->assertReturned(['en' => '', 'fr' => 'Un schéma du parcours', 'es' => '']);

        expect($inline->fresh()->blog_post_id)->toBe($post->id)
            ->and($post->translations()->where('locale', 'fr')->value('content_html'))->toContain('alt="Un schéma du parcours"');
    });

    it('describes an image that waits for the article to be created', function () {
        $pending = BlogImage::factory()->create(['alt' => null]);

        Livewire::test(PostForm::class)
            ->call('saveImageAlt', $pending->id, ['en' => 'A candle', 'fr' => '', 'es' => ''])
            ->assertReturned(['en' => 'A candle', 'fr' => '', 'es' => '']);

        expect($pending->fresh()->alt)->toBe(['en' => 'A candle']);
    });

    it('refuses to describe the image of another article, or an image that is gone', function () {
        Log::spy();
        $post = BlogContent::draft('pending');
        $other = BlogContent::draft('other');
        $foreign = BlogImage::query()->findOrFail($other->featured_image_id);
        $before = $foreign->alt;

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->call('saveImageAlt', $foreign->id, ['en' => 'Hijacked', 'fr' => '', 'es' => ''])
            ->assertReturned(null)
            ->assertDispatched('ui-toast', type: 'error')
            ->call('saveImageAlt', 999_999, ['en' => 'Nothing', 'fr' => '', 'es' => ''])
            ->assertReturned(null);

        expect($foreign->fresh()->alt)->toBe($before);

        expect(fn () => app(UpdateBlogImageAltAction::class)->execute($foreign->id, ['en' => 'Hijacked'], $post->id))
            ->toThrow(BlogImageException::class);
    });
});

describe('choosing the related articles', function () {
    it('lists the other articles only while the list is open, whatever their status, in one query', function () {
        $post = BlogContent::draft('pending');
        $published = BlogContent::published('label');
        $draft = BlogContent::draft('other');
        $chosen = BlogContent::published('candles');
        $post->relatedPosts()->attach($chosen->id, ['position' => 0]);

        $form = Livewire::test(PostForm::class, ['post' => $post->id])
            ->assertViewHas('relatedCandidates', [])
            ->assertSee('Ajouter un article');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $form->call('openRelatedPicker');

        // Les 6 lectures d'un aller-retour (traduction en cours comprise, controle du 20/11), plus celle de la liste a proposer.
        expect(count(DB::getQueryLog()))->toBe(7);
        DB::disableQueryLog();

        // Ni l'article lui-meme, ni celui deja choisi ; le statut de ce qui n'est pas publie est dit.
        $form->assertSet('pickingRelated', true)
            ->assertViewHas('relatedCandidates', [$draft->id => 'Other fr · Brouillon', $published->id => 'Label fr'])
            ->call('closeRelatedPicker')
            ->assertSet('pickingRelated', false)
            ->assertViewHas('relatedCandidates', []);
    });

    it('adds, removes and reorders the related articles, and saves their order', function () {
        $post = BlogContent::draft('pending');
        $first = BlogContent::published('label');
        $second = BlogContent::draft('other');
        $third = BlogContent::published('candles');

        $titles = fn (array $related): array => array_column($related, 'title');

        Livewire::test(PostForm::class, ['post' => $post->id])
            ->call('openRelatedPicker')
            ->call('addRelated', (string) $first->id)
            // La fenetre de choix reste ouverte : on peut en ajouter plusieurs de suite.
            ->assertSet('pickingRelated', true)
            ->call('addRelated', $second->id)
            ->call('addRelated', $third->id)
            // Un doublon, l'article lui-meme ou un identifiant sans valeur ne changent rien.
            ->call('addRelated', $first->id)
            ->call('addRelated', $post->id)
            ->call('addRelated', 'abc')
            ->assertSet('relatedIds', [$first->id, $second->id, $third->id])
            // L'ecran suit l'etat du formulaire, avant tout enregistrement.
            ->assertViewHas('related', fn (array $related): bool => $titles($related) === ['Label fr', 'Other fr', 'Candles fr'])
            ->call('reorderRelated', $third->id, 0)
            ->assertSet('relatedIds', [$third->id, $first->id, $second->id])
            ->call('reorderRelated', 999, 0)
            ->call('removeRelated', $first->id)
            ->assertSet('relatedIds', [$third->id, $second->id])
            ->call('save', BlogContent::contentsOf($post))
            ->assertHasNoErrors();

        expect($post->relatedPosts()->orderBy('blog_post_related.position')->pluck('blog_posts.id')->all())->toBe([$third->id, $second->id]);
    });

    it('shows the chosen related articles at the top of their window, each removable from there', function () {
        $post = BlogContent::draft('pending');
        $first = BlogContent::published('label');
        $second = BlogContent::draft('other');

        // Seule la liste des articles choisis, dans la fenetre (la section de l'article montre les memes titres).
        $chosen = fn (string $html): string => Str::betweenFirst($html, 'data-related-chosen', '</ul>');

        $form = Livewire::test(PostForm::class, ['post' => $post->id])
            ->assertDontSeeHtml('data-related-chosen')
            ->call('openRelatedPicker')
            ->call('addRelated', $first->id)
            ->call('addRelated', $second->id)
            ->assertSee('Articles liés');

        expect($chosen($form->html()))->toContain('Label fr')
            ->toContain('Other fr')
            ->toContain('$wire.removeRelated('.$first->id.')');

        $form->call('removeRelated', $first->id);

        expect($chosen($form->html()))->not->toContain('Label fr')->toContain('Other fr');
    });

    it('stops at six related articles', function () {
        $post = BlogContent::draft('pending');
        $ids = BlogContent::seedPublished(7);

        $form = Livewire::test(PostForm::class, ['post' => $post->id]);

        foreach ($ids as $id) {
            $form->call('addRelated', $id);
        }

        // Le bouton d'ajout disparait (le titre de la fenetre, lui, reste dans la page).
        $form->assertCount('relatedIds', 6)
            ->assertDontSeeHtml('data-add-related')
            ->assertSee('Un article garde 6 articles liés au plus.');
    });

    it('lets a new article choose its related articles before it is created', function () {
        $label = BlogContent::published('label');

        DB::flushQueryLog();
        DB::enableQueryLog();

        $form = Livewire::test(PostForm::class);

        // Creation : seulement les categories.
        expect(count(DB::getQueryLog()))->toBe(1);
        DB::flushQueryLog();

        $form->call('addRelated', $label->id);

        // Les categories, et les articles lies choisis.
        expect(count(DB::getQueryLog()))->toBe(2);
        DB::disableQueryLog();

        $form->assertViewHas('related', fn (array $related): bool => array_column($related, 'id') === [$label->id])
            ->set('texts.fr.title', 'Avec un article lié')
            ->call('save', ['en' => null, 'fr' => BlogContent::document(), 'es' => null]);

        expect(BlogPost::query()->latest('id')->first()->relatedPosts()->pluck('blog_posts.id')->all())->toBe([$label->id]);
    });

    it('reads one query less on a round trip when the article has no related article', function () {
        $post = BlogContent::draft('pending');
        $form = Livewire::test(PostForm::class, ['post' => $post->id]);

        DB::flushQueryLog();
        DB::enableQueryLog();

        $form->call('saveSource', null, 'Source', 'https://example.org');

        // Article, textes, images, categories, traduction en cours (controle du 20/11).
        expect(count(DB::getQueryLog()))->toBe(5);
        DB::disableQueryLog();
    });
});
