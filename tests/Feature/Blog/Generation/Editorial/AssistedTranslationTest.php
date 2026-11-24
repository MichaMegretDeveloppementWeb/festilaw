<?php

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\RequestBlogTranslationAction;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Data\Blog\Generation\GenerationStepContext;
use App\Data\Blog\Generation\RequestBlogTranslationData;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogGenerationType;
use App\Exceptions\Blog\BlogEditorialException;
use App\Livewire\Admin\Blog\PostTranslation;
use App\Mail\BlogGenerationBatchFinished;
use App\Models\BlogGeneration;
use App\Models\User;
use App\Services\Admin\Blog\BlogTranslationResultService;
use App\Services\Blog\Content\BlogContentNormalizer;
use App\Services\Blog\Content\BlogDocumentMarkdownWriter;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\Editorial\EditorialDocumentBuilder;
use App\Services\Blog\Generation\Editorial\Steps\AssistedTranslationStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Livewire\Livewire;
use Tests\Support\BlogAi;
use Tests\Support\BlogContent;

use function Pest\Laravel\actingAs;

/*
 | Traduction assistee d'un article ecrit a la main (tache 6.14) : le texte du formulaire part en generation,
 | le worker le traduit, le formulaire reprend le resultat sans rien enregistrer. Agents simules, aucun appel.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Mail::fake();
    Sleep::fake();
    Http::preventStrayRequests();
});

/** Un document de l'editeur avec tout ce qu'un article peut contenir. */
function richDocument(int $imageId = 41): array
{
    $text = static fn (string $value, array $marks = []): array => ['type' => 'text', 'text' => $value, ...($marks === [] ? [] : ['marks' => $marks])];

    return ['type' => 'doc', 'content' => [
        ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [$text('What Etsy asks for')]],
        ['type' => 'paragraph', 'content' => [
            $text('Since 13 December 2024, a '),
            $text('Responsible Person', [['type' => 'bold']]),
            $text(' is '),
            $text('required', [['type' => 'italic']]),
            $text('. See '),
            $text('our prices', [['type' => 'link', 'attrs' => ['href' => '/pricing']]]),
            $text('. A star * stays a star.'),
            ['type' => 'hardBreak'],
            $text('Second line.'),
        ]],
        ['type' => 'blogImageText', 'attrs' => ['imageId' => $imageId, 'width' => 'medium', 'side' => 'left'], 'content' => [
            ['type' => 'paragraph', 'content' => [$text('Beside the image, '), $text('in bold', [['type' => 'bold']]), $text('.')]],
            ['type' => 'heading', 'attrs' => ['level' => 3], 'content' => [$text('Next to it')]],
            ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('A point beside')]]]]]],
        ]],
        ['type' => 'bulletList', 'content' => [
            ['type' => 'listItem', 'content' => [
                ['type' => 'paragraph', 'content' => [$text('Company name')]],
                ['type' => 'bulletList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('Postal address')]]]]]],
            ]],
        ]],
        ['type' => 'orderedList', 'content' => [['type' => 'listItem', 'content' => [['type' => 'paragraph', 'content' => [$text('Open the listing')]]]]]],
        ['type' => 'blockquote', 'content' => [['type' => 'paragraph', 'content' => [$text('Quoted text.')]]]],
    ]];
}

it('writes a document as Markdown and builds it back identical, images and their layout included', function () {
    ['markdown' => $markdown, 'images' => $images] = app(BlogDocumentMarkdownWriter::class)->write(richDocument());

    expect($markdown)->toContain("## What Etsy asks for\n\nSince 13 December 2024, a **Responsible Person** is *required*. See [our prices](/pricing). A star \\* stays a star.")
        // Le texte a cote de l'image se traduit avec le reste, entre les deux reperes de son image.
        ->toContain("[[image:1]]\n\nBeside the image, **in bold**.\n\n### Next to it\n\n- A point beside\n\n[[/image:1]]")
        ->and($images)->toBe([1 => ['imageId' => 41, 'width' => 'medium', 'side' => 'left']]);

    $rebuilt = app(EditorialDocumentBuilder::class)->build($markdown, $images);
    $normalizer = app(BlogContentNormalizer::class);

    expect($normalizer->normalize($rebuilt)->document)->toBe($normalizer->normalize(richDocument())->document);
});

it('keeps the text after an image in its place when the translation lost the end of the text beside it', function () {
    $images = [1 => ['imageId' => 41, 'width' => 'small', 'side' => 'right'], 2 => ['imageId' => 42]];
    $markdown = "Before.\n\n[[image:1]]\n\nNo end marker after this.\n\n[[image:2]]\n\n[[/image:9]]\n\nAfter.";

    $content = app(EditorialDocumentBuilder::class)->build($markdown, $images)['content'];

    // Le bloc garde un texte vide, le texte suivant reste hors de lui, et un repere egare disparait.
    expect(array_column($content, 'type'))->toBe(['paragraph', 'blogImageText', 'paragraph', 'blogImage', 'paragraph'])
        ->and($content[1]['content'])->toBe([['type' => 'paragraph']])
        ->and($content[1]['attrs'])->toBe($images[1]);
});

it('queues the translation of the text in the form as a generation, without saving the article', function () {
    $user = User::factory()->create();

    $id = app(RequestBlogTranslationAction::class)->execute(new RequestBlogTranslationData(
        from: 'fr', to: 'en', title: 'Ajouter un opérateur économique', excerpt: 'Un extrait.', metaTitle: '', metaDescription: '',
        content: richDocument(), postId: null, requestedBy: $user->id,
    ));

    $generation = BlogGeneration::query()->findOrFail($id);
    expect($generation->type)->toBe(BlogGenerationType::Translation)
        ->and($generation->status)->toBe(BlogGenerationStatus::Queued)
        ->and($generation->topic_hint)->toBe('Traduction en anglais de « Ajouter un opérateur économique »')
        ->and($generation->payload['translation']['from'])->toBe('fr')
        ->and($generation->payload['translation']['article']['body'])->toContain('[[image:1]]')
        ->and($generation->payload['translation']['images'][1])->toBe(['imageId' => 41, 'width' => 'medium', 'side' => 'left']);
});

it('refuses a translation into the same language, from an unknown language, or of an empty text', function (string $from, string $to, string $title) {
    expect(fn () => app(RequestBlogTranslationAction::class)->execute(new RequestBlogTranslationData($from, $to, $title, '', '', '', null)))
        ->toThrow(BlogEditorialException::class);
    expect(BlogGeneration::query()->count())->toBe(0);
})->with([
    'same language' => ['fr', 'fr', 'Titre'],
    'unknown language' => ['de', 'en', 'Titel'],
    'nothing to translate' => ['fr', 'en', ' '],
]);

it('still queues a translation above the spend cap of the month, which only warns', function () {
    BlogAi::saveSettings(['monthly_spend_cap_cents' => 100]);
    BlogGeneration::factory()->completed(2_000_000)->create();

    $id = app(RequestBlogTranslationAction::class)->execute(new RequestBlogTranslationData('fr', 'en', 'Titre', '', '', '', null));

    expect(BlogGeneration::query()->findOrFail($id)->type)->toBe(BlogGenerationType::Translation);
});

it('translates with the rules and lexicon of the target language and keeps links and images', function () {
    $source = "Le GPSR s'applique. [Nos tarifs](/fr/pricing)\n\n[[image:1]]";
    TranslatorAgent::fake([[
        'title' => 'Adding an economic operator', 'excerpt' => 'An excerpt.', 'meta_title' => '', 'meta_description' => '',
        'body_markdown' => "The GPSR applies. [Our prices](/fr/pricing)\n\n[[image:1]]", 'report_fr' => 'Traduit.',
    ]])->preventStrayPrompts();
    $context = new GenerationStepContext(5, BlogGenerationType::Translation, null, null, app(BlogAiSettingsService::class)->current(), [], ['translation' => [
        'from' => 'fr', 'to' => 'en', 'article' => ['title' => 'Ajouter un opérateur économique', 'excerpt' => 'Un extrait.', 'body' => $source], 'images' => [],
    ]], 1);

    $result = app(AssistedTranslationStep::class)->execute($context);

    expect($result->output['locale'])->toBe('en')
        ->and($result->output['article']['title'])->toBe('Adding an economic operator')
        ->and($result->summary)->toBe('Traduit.');
    TranslatorAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('French')
        && str_contains($prompt->agent->instructions(), '# Voice and style (English)')
        && ! str_contains($prompt->agent->instructions(), '# Domain lexicon'));
});

it('lets the form follow a translation, then hands back the texts and the document of the target language', function () {
    actingAs(User::factory()->create());

    $component = Livewire::test(PostTranslation::class)
        ->dispatch('request-post-translation', from: 'en', to: 'fr', source: ['title' => 'Etsy labels', 'excerpt' => 'Short.', 'metaTitle' => '', 'metaDescription' => '', 'content' => richDocument()]);

    $generationId = $component->get('generationId');
    expect($generationId)->not->toBeNull();

    // La generation attend le worker : le releve ne rend rien.
    $component->call('check')->assertNotDispatched('post-translation-ready')->assertSet('generationId', $generationId);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $component->call('check')
        ->assertDispatched('post-translation-ready', function (string $event, array $params): bool {
            return $params['locale'] === 'fr'
                && $params['texts']['title'] === '[FR] Etsy labels'
                && $params['content']['content'][2]['type'] === 'blogImageText'
                && $params['content']['content'][2]['attrs'] === ['imageId' => 41, 'width' => 'medium', 'side' => 'left']
                && $params['content']['content'][2]['content'] === richDocument()['content'][2]['content']
                && $params['content']['content'][3]['type'] === 'bulletList';
        })
        ->assertSet('generationId', null);

    // Une traduction se suit dans le formulaire : aucun e-mail de fin de lot.
    Mail::assertNotSent(BlogGenerationBatchFinished::class);
});

it('translates one field alone, sending only that field, and hands it back for that field only', function () {
    // Demande de Micha du 24/11 : apres une retouche ciblee, retraduire toute la langue couterait pour rien.
    actingAs(User::factory()->create());

    $component = Livewire::test(PostTranslation::class)
        ->dispatch('request-post-translation', from: 'en', to: 'fr', field: 'excerpt', source: ['title' => 'Etsy labels', 'excerpt' => 'Short.', 'metaTitle' => 'Meta', 'metaDescription' => '', 'content' => richDocument()]);

    $generation = BlogGeneration::query()->findOrFail($component->get('generationId'));
    expect($generation->payload['translation']['field'])->toBe('excerpt')
        ->and($generation->payload['translation']['article'])->toBe(['title' => '', 'excerpt' => 'Short.', 'meta_title' => '', 'meta_description' => '', 'body' => ''])
        ->and($generation->payload['translation']['images'])->toBe([])
        ->and($generation->topic_hint)->toBe('Traduction en français du champ « extrait » de « Etsy labels »');

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $component->call('check')
        ->assertDispatched('post-translation-ready', fn (string $event, array $params): bool => $params['field'] === 'excerpt'
            && $params['locale'] === 'fr'
            && $params['texts']['excerpt'] !== '');
});

it('refuses to translate an empty field, or a field it does not know', function (string $field, string $metaTitle) {
    expect(fn () => app(RequestBlogTranslationAction::class)->execute(new RequestBlogTranslationData('en', 'fr', 'Etsy labels', '', $metaTitle, '', null, field: $field)))
        ->toThrow(BlogEditorialException::class);
    expect(BlogGeneration::query()->count())->toBe(0);
})->with([
    'empty field' => ['metaTitle', ''],
    'unknown field' => ['slug', 'Meta'],
]);

it('translates a single field with the instruction to leave the other fields empty', function () {
    TranslatorAgent::fake([[
        'title' => '', 'excerpt' => 'Un extrait court.', 'meta_title' => '', 'meta_description' => '', 'body_markdown' => '', 'report_fr' => 'Traduit.',
    ]])->preventStrayPrompts();
    $context = new GenerationStepContext(5, BlogGenerationType::Translation, null, null, app(BlogAiSettingsService::class)->current(), [], ['translation' => [
        'from' => 'en', 'to' => 'fr', 'field' => 'excerpt', 'article' => ['title' => '', 'excerpt' => 'A short excerpt.', 'body' => ''], 'images' => [],
    ]], 1);

    $result = app(AssistedTranslationStep::class)->execute($context);

    expect($result->output['article']['excerpt'])->toBe('Un extrait court.');
    TranslatorAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Only one part of the article is given'));
});

it('tells the form when a translation fails, without any technical detail', function () {
    actingAs(User::factory()->create());
    config(['blog.ai.simulation.fail_step' => 'translation']);
    $component = Livewire::test(PostTranslation::class)
        ->dispatch('request-post-translation', from: 'en', to: 'es', source: ['title' => 'Etsy labels', 'content' => richDocument()]);

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $component->call('check')
        ->assertDispatched('post-translation-failed', locale: 'es')
        ->assertDispatched('ui-toast', fn (string $event, array $params): bool => $params['type'] === 'error' && ! str_contains($params['title'], 'Exception'));
});

it('reports a translation still running as pending, and an unknown generation as failed', function () {
    $running = BlogGeneration::factory()->running()->create(['type' => BlogGenerationType::Translation]);
    $results = app(BlogTranslationResultService::class);

    expect($results->find($running->id)->isPending())->toBeTrue()
        ->and($results->find(999_999)->isReady())->toBeFalse()
        ->and($results->find(999_999)->isPending())->toBeFalse();
});

it('finds again, after the page was reloaded, a translation still running for the article', function () {
    // Controle du 20/11 : un rechargement de la page perdait la traduction attendue.
    actingAs(User::factory()->create());
    $post = BlogContent::published('label');
    Livewire::test(PostTranslation::class, ['postId' => $post->id])
        ->dispatch('request-post-translation', from: 'en', to: 'es', source: ['title' => 'Etsy labels', 'content' => richDocument()]);

    $reloaded = Livewire::test(PostTranslation::class, ['postId' => $post->id]);

    expect($reloaded->get('generationId'))->not->toBeNull()
        ->and($reloaded->get('target'))->toBe('es');
    expect(Livewire::test(PostTranslation::class, ['postId' => BlogContent::published('other')->id])->get('generationId'))->toBeNull();
});

it('stops waiting after twenty minutes, saying the translation goes on', function () {
    actingAs(User::factory()->create());
    $component = Livewire::test(PostTranslation::class)
        ->dispatch('request-post-translation', from: 'en', to: 'fr', source: ['title' => 'Etsy labels', 'content' => richDocument()]);

    $this->travel(19)->minutes();
    $component->call('check')->assertNotDispatched('post-translation-failed');

    $this->travel(2)->minutes();
    $component->call('check')
        ->assertSet('generationId', null)
        ->assertDispatched('post-translation-failed', locale: 'fr')
        ->assertDispatched('ui-toast', fn (string $event, array $params): bool => $params['type'] === 'warning' && str_contains($params['title'], 'Elle continue'));
});

it('stops following a translation after an error, instead of repeating the error every two seconds', function () {
    // Audit du 23/11 : la notification d'erreur revenait a chaque releve.
    actingAs(User::factory()->create());
    $component = Livewire::test(PostTranslation::class)
        ->dispatch('request-post-translation', from: 'en', to: 'fr', source: ['title' => 'Etsy labels', 'content' => richDocument()]);
    Schema::rename('blog_generations', 'blog_generations_unavailable');

    $component->call('check')
        ->assertSet('generationId', null)
        ->assertDispatched('post-translation-failed', locale: 'fr')
        ->assertDispatched('ui-toast', type: 'error');

    Schema::rename('blog_generations_unavailable', 'blog_generations');
    expect($component->html())->not->toContain('wire:poll');
});
