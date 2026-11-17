<?php

use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\ResearchAgent;
use App\Ai\Agents\Blog\StyleAgent;
use App\Ai\Agents\Blog\TopicAgent;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Ai\Agents\Blog\WriterAgent;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogTopicKind;
use App\Exceptions\Blog\BlogEditorialException;
use App\Models\BlogGeneration;
use App\Services\Blog\Generation\Editorial\Steps\ResearchStep;
use App\Services\Blog\Generation\Editorial\Steps\StyleStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicStep;
use App\Services\Blog\Generation\Editorial\Steps\TranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\VerificationStep;
use App\Services\Blog\Generation\Editorial\Steps\WritingStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Support\BlogAi;
use Tests\Support\BlogContent;
use Tests\Support\EditorialFixtures as Fixtures;

/*
 | Les etapes de texte du pipeline editorial : sujet, recherche, redaction, verification, style, traductions.
 | Agents simules par le SDK (aucun appel reel), liens testes sur un reseau simule. Chaque etape est jouee
 | seule, avec les sorties des etapes precedentes en entree.
 */

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    Fixtures::publicHosts();
});

// --- Sujet

it('chooses a topic from the settings, the request and every known title, and keeps it on the generation', function () {
    BlogAi::saveSettings(['priority_topics' => ['Etsy and the GPSR']]);
    BlogContent::published('label');
    BlogGeneration::factory()->create(['topic_title' => 'Candle warnings', 'topic_rejected_at' => now()]);
    TopicAgent::fake([[...Fixtures::topic(), 'report_fr' => 'Les vendeurs Etsy posent cette question chaque semaine.']])->preventStrayPrompts();

    $result = app(TopicStep::class)->execute(Fixtures::context(topicHint: 'Etsy economic operator'));

    expect($result->stopsGeneration())->toBeFalse()
        ->and($result->output['title'])->toBe('Adding an economic operator on Etsy')
        ->and($result->summary)->toBe('Les vendeurs Etsy posent cette question chaque semaine.')
        ->and($result->findings->topicTitle)->toBe('Adding an economic operator on Etsy')
        ->and($result->findings->topicKind)->toBe(BlogTopicKind::Evergreen)
        ->and($result->usage->model)->toBe('anthropic/claude-sonnet-5.5');
    TopicAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Etsy economic operator')
        && $prompt->contains('Etsy and the GPSR')
        && $prompt->contains('Label en')
        && $prompt->contains('Candle warnings'));
});

it('writes nothing when no topic is relevant enough', function () {
    TopicAgent::fake([['decision' => 'none', 'reason' => 'Nothing new this week.', 'report_fr' => 'Rien de nouveau cette semaine.']])->preventStrayPrompts();

    $result = app(TopicStep::class)->execute(Fixtures::context());

    expect($result->stopReason)->toBe(BlogGenerationOutcomeReason::NoRelevantTopic)
        ->and($result->summary)->toBe('Rien de nouveau cette semaine.');
});

it('turns down a topic that repeats a known title, even when the model did not see it', function () {
    $post = BlogContent::published('label');
    $post->translations()->where('locale', 'en')->update(['title' => 'How to add your economic operator on Etsy']);
    TopicAgent::fake([Fixtures::topic()])->preventStrayPrompts();

    $result = app(TopicStep::class)->execute(Fixtures::context());

    expect($result->stopReason)->toBe(BlogGenerationOutcomeReason::DuplicateTopic)
        ->and($result->output['duplicate_of'])->toBe('How to add your economic operator on Etsy')
        ->and($result->findings->topicTitle)->toBe('Adding an economic operator on Etsy');
});

it('fails the topic step on an unusable answer, so the engine tries again', function () {
    TopicAgent::fake([['reason' => 'No decision field.']])->preventStrayPrompts();

    expect(fn () => app(TopicStep::class)->execute(Fixtures::context()))->toThrow(BlogEditorialException::class, 'unusable answer');
});

// --- Recherche

it('keeps the live sources of the research file and drops a dead link with the facts that relied on it', function () {
    $research = Fixtures::research();
    $research['sources'][] = ['id' => 'S3', 'title' => 'Old page', 'url' => 'https://old.example/gone', 'publisher' => 'Blog', 'type' => 'professional', 'date' => null];
    $research['facts'][] = ['id' => 'F3', 'claim' => 'Something from a dead page.', 'source_ids' => ['S3'], 'date' => null];
    ResearchAgent::fake([[...$research, 'report_fr' => 'Trois sources consultées.']])->preventStrayPrompts();
    Http::fake(['https://old.example/*' => Http::response('', 404), '*' => Http::response('ok')]);

    $result = app(ResearchStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic()]));

    expect(array_column($result->output['sources'], 'id'))->toBe(['S1', 'S2'])
        ->and(array_column($result->output['facts'], 'id'))->toBe(['F1', 'F2'])
        ->and($result->output['removed_links'])->toBe(['https://old.example/gone'])
        ->and($result->summary)->toBe('Trois sources consultées.');
    ResearchAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Adding an economic operator on Etsy'));
});

it('stops when no official or professional source is left', function () {
    ResearchAgent::fake([[
        'sources' => [['id' => 'S1', 'title' => 'A forum', 'url' => 'https://forum.example/t/1', 'publisher' => 'Forum', 'type' => 'forum']],
        'facts' => [['id' => 'F1', 'claim' => 'Sellers say so.', 'source_ids' => ['S1']]],
        'report_fr' => 'Un forum consulté.',
    ]])->preventStrayPrompts();
    Http::fake(['*' => Http::response('ok')]);

    $result = app(ResearchStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic()]));

    expect($result->stopReason)->toBe(BlogGenerationOutcomeReason::InsufficientSources);
});

// --- Redaction

it('writes the English article from the research file, with sources, category and related articles taken from the blog', function () {
    $category = BlogContent::category('platforms');
    $related = BlogContent::published('label', $category);
    WriterAgent::fake([Fixtures::writerAnswer(['category_id' => $category->id, 'related_post_ids' => [$related->id, 9_999]])])->preventStrayPrompts();

    $result = app(WritingStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research' => Fixtures::research()]));

    expect($result->output['article']['title'])->toBe('Adding an economic operator on Etsy')
        ->and($result->output['article']['body'])->toBe(Fixtures::body())
        ->and($result->output['source_ids'])->toBe(['S1', 'S2'])
        ->and($result->output['category_id'])->toBe($category->id)
        // Un article inconnu n'est jamais propose en lecture associee.
        ->and($result->output['related_post_ids'])->toBe([$related->id])
        ->and($result->output['inline_images'][0]['number'])->toBe(1)
        ->and($result->output['warnings'])->toBe([]);
    WriterAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Platforms en') && $prompt->contains('Label en') && $prompt->contains('The GPSR applies since 13 December 2024.'));
});

it('asks once for a correction when the article breaks the rules, then keeps the corrected one', function () {
    WriterAgent::fake([
        Fixtures::writerAnswer(['meta_description' => 'Too short.', 'body_markdown' => Fixtures::body().' [More](festilaw:page/unknown) [Elsewhere](https://random.example/page)']),
        Fixtures::writerAnswer(),
    ])->preventStrayPrompts();

    $result = app(WritingStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research' => Fixtures::research()]));

    expect($result->output['article']['body'])->toBe(Fixtures::body())
        ->and($result->output['warnings'])->toBe([]);
    WriterAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Fix these problems')
        && $prompt->contains('meta_description has 10 characters')
        && $prompt->contains('festilaw:page/unknown does not match')
        && $prompt->contains('https://random.example/page is not a source'));
});

it('limits the images inside the text to the number set for the generation', function () {
    WriterAgent::fake([Fixtures::writerAnswer(), Fixtures::writerAnswer()])->preventStrayPrompts();

    // Une seule image en tout : l'image principale, aucune dans le texte.
    $result = app(WritingStep::class)->execute(Fixtures::context(
        ['topic' => Fixtures::topic(), 'research' => Fixtures::research()],
        payload: ['images' => ['mode' => 'stock', 'max' => 1]],
    ));

    expect($result->output['warnings'])->toContain('The body has more than 0 inline image placeholders.');
    WriterAgent::assertPrompted(fn ($prompt): bool => $prompt->contains("## Inline images allowed\n\n0"));
});

it('fails the writing step when no usable article comes back, even after a correction', function () {
    WriterAgent::fake([['title' => '', 'body_markdown' => ''], ['title' => 'Only a title']])->preventStrayPrompts();

    expect(fn () => app(WritingStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research' => Fixtures::research()])))
        ->toThrow(BlogEditorialException::class);
});

// --- Verification

it('removes the statements the research file does not support, and keeps the trace in the step journal only', function () {
    $written = Fixtures::body()."\n\nFines reach 4% of turnover.";
    FactCheckAgent::fake([[
        ...Fixtures::article(),
        'removed_claims' => [['claim' => 'Fines reach 4% of turnover.', 'reason' => 'Not in the research file.']],
        'report_fr' => 'Une affirmation non appuyée a été retirée.',
    ]])->preventStrayPrompts();

    $result = app(VerificationStep::class)->execute(Fixtures::context(['research' => Fixtures::research(), 'writing' => ['article' => Fixtures::articleOutput($written)['article']]]));

    expect($result->output['article']['body'])->toBe(Fixtures::body())
        ->and($result->output['removed_claims'])->toBe([['claim' => 'Fines reach 4% of turnover.', 'reason' => 'Not in the research file.']])
        ->and($result->summary)->toBe('Une affirmation non appuyée a été retirée.');
});

it('refuses a check that adds a figure, or drops an image', function (string $body) {
    FactCheckAgent::fake([[...Fixtures::article(['body_markdown' => $body]), 'removed_claims' => [], 'report_fr' => 'Rien.']])->preventStrayPrompts();

    expect(fn () => app(VerificationStep::class)->execute(Fixtures::context(['research' => Fixtures::research(), 'writing' => Fixtures::articleOutput()])))
        ->toThrow(BlogEditorialException::class);
})->with([
    'a new figure' => fn () => Fixtures::body().' About 30,000 sellers are concerned.',
    'a lost image' => fn () => str_replace('[[image:1]]', '', Fixtures::body()),
]);

// --- Style

it('keeps the style edit when it changed no fact, and counts the signs of the final text', function () {
    $edited = str_replace('Gather the details before you edit your listings.', 'Have the details at hand before you open your listings.', Fixtures::body());
    StyleAgent::fake([[...Fixtures::article(['body_markdown' => $edited]), 'report_fr' => 'Deux phrases ont été reprises.']])->preventStrayPrompts();

    $result = app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]));

    expect($result->output['style_applied'])->toBeTrue()
        ->and($result->output['article']['body'])->toBe($edited)
        ->and($result->output['signals'])->toHaveKeys(['dashes', 'phrases', 'sentence_length_variation'])
        ->and($result->summary)->toBe('Deux phrases ont été reprises.');
});

it('discards a style edit that changed a figure or a link, and keeps the checked text', function () {
    StyleAgent::fake([[...Fixtures::article(['body_markdown' => str_replace('13 December 2024', '13 December 2025', Fixtures::body())]), 'report_fr' => 'Style revu.']])->preventStrayPrompts();

    $result = app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]));

    expect($result->output['style_applied'])->toBeFalse()
        ->and($result->output['article']['body'])->toBe(Fixtures::body())
        ->and($result->output['differences'])->toBe(['numbers changed: missing [2024], added [2025]'])
        ->and($result->summary)->toBe('La relecture du style a été écartée, car elle modifiait un chiffre ou un lien. Le texte vérifié est gardé.');
});

// --- Traductions

it('writes the French version with the same links and images, and the image descriptions in French', function () {
    $french = "Depuis le 13 décembre 2024, chaque produit vendu dans l'UE a besoin d'un opérateur économique. [Lire le règlement](https://eur-lex.europa.eu/eli/reg/2023/988/oj).\n\n"
        ."## Ce que demande Etsy\n\nEtsy le demande dans les réglages de l'annonce. [Vérifier votre cas](festilaw:page/quiz).\n\n[[image:1]]\n\n"
        ."## La suite\n\nRassemblez les informations avant de modifier vos annonces. [Voir nos tarifs](festilaw:page/pricing).";
    TranslatorAgent::fake([[
        ...Fixtures::article(['title' => 'Ajouter un opérateur économique sur Etsy', 'body_markdown' => $french]),
        'image_alts' => [['number' => 0, 'alt' => 'Un colis sur un établi'], ['number' => 1, 'alt' => 'Une étiquette sur un carton']],
        'report_fr' => 'Le libellé « economic operator » d\'Etsy est gardé tel quel.',
    ]])->preventStrayPrompts();

    $result = app()->make(TranslationStep::class, ['locale' => 'fr'])->execute(Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput()]));

    expect($result->output['locale'])->toBe('fr')
        ->and($result->output['article']['title'])->toBe('Ajouter un opérateur économique sur Etsy')
        ->and($result->output['image_alts'])->toBe(['0' => 'Un colis sur un établi', '1' => 'Une étiquette sur un carton'])
        ->and($result->output['number_warnings'])->toBe([])
        ->and($result->output['signals'])->toHaveKey('phrases');
    TranslatorAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('A parcel on a workbench'));
});

it('asks once for a correction when a translation loses a link or an image, then fails if it still does', function () {
    $lost = Fixtures::article(['body_markdown' => str_replace(['[[image:1]]', '(festilaw:page/quiz)'], ['', '(/quiz)'], Fixtures::body())]);
    TranslatorAgent::fake([[...$lost, 'report_fr' => 'x'], [...$lost, 'report_fr' => 'x']])->preventStrayPrompts();

    expect(fn () => app()->make(TranslationStep::class, ['locale' => 'es'])->execute(Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput()])))
        ->toThrow(BlogEditorialException::class, 'image placeholders changed');
    TranslatorAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Fix these problems'));
});

// --- Contexte de l'entreprise

it('gives every agent the company context first, then the editorial rules', function (string $step) {
    Http::fake(['*' => Http::response('ok')]);

    $agent = match ($step) {
        'topic' => TopicAgent::class,
        'research' => ResearchAgent::class,
        'writing' => WriterAgent::class,
        'verification' => FactCheckAgent::class,
        'style' => StyleAgent::class,
        'translation' => TranslatorAgent::class,
    };

    match ($step) {
        'topic' => [TopicAgent::fake([['decision' => 'none', 'reason' => 'x', 'report_fr' => 'x']]), app(TopicStep::class)->execute(Fixtures::context())],
        'research' => [ResearchAgent::fake([[...Fixtures::research(), 'report_fr' => 'x']]), app(ResearchStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic()]))],
        'writing' => [WriterAgent::fake([Fixtures::writerAnswer()]), app(WritingStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research' => Fixtures::research()]))],
        'verification' => [FactCheckAgent::fake([[...Fixtures::article(), 'removed_claims' => [], 'report_fr' => 'x']]), app(VerificationStep::class)->execute(Fixtures::context(['research' => Fixtures::research(), 'writing' => Fixtures::articleOutput()]))],
        'style' => [StyleAgent::fake([[...Fixtures::article(), 'report_fr' => 'x']]), app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]))],
        'translation' => [TranslatorAgent::fake([[...Fixtures::article(), 'image_alts' => [], 'report_fr' => 'x']]), app()->make(TranslationStep::class, ['locale' => 'fr'])->execute(Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput()]))],
    };

    $agent::assertPrompted(fn ($prompt): bool => str_starts_with($prompt->agent->instructions(), '# About Festilaw, the company you work for')
        && str_contains($prompt->agent->instructions(), '# Festilaw editorial guidelines'));
})->with(['topic', 'research', 'writing', 'verification', 'style', 'translation']);
