<?php

use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\ResearchAgent;
use App\Ai\Agents\Blog\StyleAgent;
use App\Ai\Agents\Blog\TopicAgent;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Ai\Agents\Blog\WebSearchAgent;
use App\Ai\Agents\Blog\WriterAgent;
use App\Enums\Blog\Generation\BlogGenerationOutcomeReason;
use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Enums\Blog\Generation\BlogTopicKind;
use App\Exceptions\Blog\BlogAiProviderException;
use App\Models\BlogGeneration;
use App\Services\Blog\Generation\Editorial\Steps\ResearchNotesStep;
use App\Services\Blog\Generation\Editorial\Steps\ResearchStep;
use App\Services\Blog\Generation\Editorial\Steps\StyleStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicScanStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicStep;
use App\Services\Blog\Generation\Editorial\Steps\TranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\VerificationStep;
use App\Services\Blog\Generation\Editorial\Steps\WritingStep;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Sleep;
use Laravel\Ai\Responses\Data\Meta;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Responses\TextResponse;
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
    WebSearchAgent::fake()->preventStrayPrompts();
    TopicAgent::fake([[...Fixtures::topic(), 'report_fr' => 'Les vendeurs Etsy posent cette question chaque semaine.']])->preventStrayPrompts();

    $result = app(TopicStep::class)->execute(Fixtures::context(topicHint: 'Etsy economic operator'));

    expect($result->stopsGeneration())->toBeFalse()
        ->and($result->output['title'])->toBe('Adding an economic operator on Etsy')
        ->and($result->summary)->toBe('Les vendeurs Etsy posent cette question chaque semaine.')
        ->and($result->findings->topicTitle)->toBe('Adding an economic operator on Etsy')
        ->and($result->findings->topicKind)->toBe(BlogTopicKind::Evergreen)
        ->and($result->usage->model)->toBe('anthropic/claude-haiku-5.5');
    TopicAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Etsy economic operator')
        && $prompt->contains('Etsy and the GPSR')
        && $prompt->contains('Label en')
        && $prompt->contains('Candle warnings')
        && $prompt->contains('No web scan'));
    // Un sujet impose se passe de veille : la recherche de l'article cherchera sur le web.
    WebSearchAgent::assertNeverPrompted();
});

it('scans the web for a free topic with two bounded searches, and keeps the notes for the editor', function () {
    BlogAi::saveSettings(['priority_topics' => ['Etsy and the GPSR']]);
    WebSearchAgent::fake(['Notes from the web scan.'])->preventStrayPrompts();

    $scan = app(TopicScanStep::class)->execute(Fixtures::context());

    expect($scan->output['notes'])->toBe('Notes from the web scan.');
    // Des extraits courts du moteur Exa : la recherche native d'Haiku rendait des pages entieres (17/11).
    WebSearchAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Etsy and the GPSR')
        && str_contains($prompt->agent->instructions(), 'at most 2 web searches')
        && collect($prompt->agent->tools())->sole()->maxSearches === 2
        && collect($prompt->agent->tools())->sole()->providerOptions('openrouter') === ['engine' => 'exa', 'max_results' => 5, 'max_characters' => 1_500, 'max_total_results' => 10]);
});

it('chooses the topic from the stored notes of the web scan, without searching again', function () {
    WebSearchAgent::fake()->preventStrayPrompts();
    TopicAgent::fake([Fixtures::topic()])->preventStrayPrompts();

    app(TopicStep::class)->execute(Fixtures::context(['topic_scan' => ['notes' => 'Notes from the web scan.', 'citations' => [], 'cut_off' => false]]));

    TopicAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Notes from the web scan.'));
    WebSearchAgent::assertNeverPrompted();
});

it('skips the web scan when the topic is imposed', function () {
    WebSearchAgent::fake()->preventStrayPrompts();

    $scan = app(TopicScanStep::class)->execute(Fixtures::context(topicHint: 'Etsy economic operator'));

    expect($scan->output['skipped'])->toBeTrue()
        ->and($scan->usage->costMicrousd)->toBe(0);
    WebSearchAgent::assertNeverPrompted();
});

it('writes nothing when no topic is relevant enough', function () {
    WebSearchAgent::fake(['Notes from the web scan.']);
    TopicAgent::fake([['decision' => 'none', 'reason' => 'Nothing new this week.', 'report_fr' => 'Rien de nouveau cette semaine.']])->preventStrayPrompts();

    $result = app(TopicStep::class)->execute(Fixtures::context());

    expect($result->stopReason)->toBe(BlogGenerationOutcomeReason::NoRelevantTopic)
        ->and($result->summary)->toBe('Rien de nouveau cette semaine.');
});

it('turns down a topic that repeats a known title, even when the model did not see it', function () {
    $post = BlogContent::published('label');
    $post->translations()->where('locale', 'en')->update(['title' => 'How to add your economic operator on Etsy']);
    WebSearchAgent::fake(['Notes from the web scan.']);
    TopicAgent::fake([Fixtures::topic()])->preventStrayPrompts();

    $result = app(TopicStep::class)->execute(Fixtures::context());

    expect($result->stopReason)->toBe(BlogGenerationOutcomeReason::DuplicateTopic)
        ->and($result->output['duplicate_of'])->toBe('How to add your economic operator on Etsy')
        ->and($result->findings->topicTitle)->toBe('Adding an economic operator on Etsy');
});

it('fails the topic step on an unusable answer, so the engine tries again', function () {
    WebSearchAgent::fake(['Notes from the web scan.']);
    TopicAgent::fake([['reason' => 'No decision field.']])->preventStrayPrompts();

    expect(fn () => app(TopicStep::class)->execute(Fixtures::context()))->toThrow(BlogAiProviderException::class, 'unusable answer');
});

it('keeps the paid web scan when the topic choice fails, and never pays it again on a new attempt', function () {
    Sleep::fake();
    Mail::fake();
    config(['blog.ai.pipeline' => 'editorial']);
    $raw = new Response(new Psr7Response(200, [], (string) json_encode(['usage' => [
        'prompt_tokens' => 4_000, 'completion_tokens' => 900, 'cost' => 0.012, 'server_tool_use_details' => ['web_search_requests' => 2],
    ]])));
    $scans = 0;
    WebSearchAgent::fake(function () use (&$scans, $raw) {
        $scans++;

        return (new TextResponse('Notes.', new TextUsage(4_000, 900), new Meta('openrouter', 'm')))->withRawResponse($raw);
    });
    TopicAgent::fake(fn () => throw new RuntimeException('Provider returned error'));
    $generation = BlogGeneration::factory()->create();

    app(ProcessNextBlogGenerationAction::class)->execute('worker-test');

    $steps = $generation->steps()->get()->keyBy('step');
    // Au 17/11, chaque nouvel essai de la recherche refaisait ses recherches web : trois essais, huit recherches.
    expect($generation->refresh()->status)->toBe(BlogGenerationStatus::Failed)
        ->and($steps['topic']->attempt)->toBe(3)
        ->and($scans)->toBe(1)
        ->and($steps['topic_scan']->cost_microusd)->toBe(12_000)
        ->and($generation->cost_microusd)->toBe(12_000);
});

// --- Recherche

it('searches the web for the chosen topic with three bounded searches, and keeps the notes', function () {
    WebSearchAgent::fake([Fixtures::notesCiting()])->preventStrayPrompts();

    $result = app(ResearchNotesStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic()]));

    expect($result->output['notes'])->toBe(Fixtures::notesCiting())
        ->and($result->output['cut_off'])->toBeFalse();
    WebSearchAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Adding an economic operator on Etsy')
        && str_contains($prompt->agent->instructions(), 'at most 3 web searches')
        && collect($prompt->agent->tools())->sole()->maxSearches === 3
        && collect($prompt->agent->tools())->sole()->providerOptions('openrouter')['engine'] === 'exa');
});

it('fails the web search step when it brings back no notes, so the engine tries again', function () {
    WebSearchAgent::fake(['  '])->preventStrayPrompts();

    expect(fn () => app(ResearchNotesStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic()])))
        ->toThrow(BlogAiProviderException::class, 'no research notes');
});

it('keeps the live sources of the research file and drops a dead link with the facts that relied on it', function () {
    $research = Fixtures::research();
    $research['sources'][] = ['id' => 'S3', 'title' => 'Old page', 'url' => 'https://old.example/gone', 'publisher' => 'Blog', 'type' => 'professional', 'date' => null];
    $research['facts'][] = ['id' => 'F3', 'claim' => 'Something from a dead page.', 'source_ids' => ['S3'], 'date' => null];
    WebSearchAgent::fake()->preventStrayPrompts();
    ResearchAgent::fake([[...$research, 'report_fr' => 'Trois sources consultées.']])->preventStrayPrompts();
    Http::fake(['https://old.example/*' => Http::response('', 404), '*' => Http::response('ok')]);

    $result = app(ResearchStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research_notes' => Fixtures::researchNotes($research['sources'])]));

    expect(array_column($result->output['sources'], 'id'))->toBe(['S1', 'S2'])
        ->and(array_column($result->output['facts'], 'id'))->toBe(['F1', 'F2'])
        ->and($result->output['removed_links'])->toBe(['https://old.example/gone'])
        ->and($result->summary)->toBe('Trois sources consultées.');
    ResearchAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Adding an economic operator on Etsy') && $prompt->contains('Old page'));
    // Le dossier se tire des notes enregistrees : un nouvel essai ne repaie aucune recherche.
    WebSearchAgent::assertNeverPrompted();
});

it('leaves out a source whose address the search never met, so an invented address never enters the file', function () {
    $research = Fixtures::research();
    ResearchAgent::fake([[...$research, 'report_fr' => 'x']])->preventStrayPrompts();
    Http::fake(['*' => Http::response('ok')]);

    $result = app(ResearchStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research_notes' => Fixtures::researchNotes([$research['sources'][0]])]));

    // L'adresse d'Etsy n'etait pas dans les notes : la source sort, et le fait qui ne reposait que sur elle aussi.
    expect(array_column($result->output['sources'], 'id'))->toBe(['S1'])
        ->and(array_column($result->output['facts'], 'id'))->toBe(['F1']);
});

it('stops when no official or professional source is left', function () {
    ResearchAgent::fake([[
        'sources' => [['id' => 'S1', 'title' => 'A forum', 'url' => 'https://forum.example/t/1', 'publisher' => 'Forum', 'type' => 'forum']],
        'facts' => [['id' => 'F1', 'claim' => 'Sellers say so.', 'source_ids' => ['S1']]],
        'report_fr' => 'Un forum consulté.',
    ]])->preventStrayPrompts();
    Http::fake(['*' => Http::response('ok')]);

    $result = app(ResearchStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research_notes' => Fixtures::researchNotes(notes: 'Seen on https://forum.example/t/1')]));

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

it('asks the writer for the length of the generation, and once for a correction when the body is outside the accepted range', function () {
    WriterAgent::fake([Fixtures::writerAnswer(), Fixtures::writerAnswer()])->preventStrayPrompts();

    $result = app(WritingStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research' => Fixtures::research()], words: 1_000));

    // Au premier article reel, 600 a 800 mots demandes et 522 ecrits : la consigne seule ne tient pas la longueur.
    WriterAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->agent->instructions(), 'about 1000 words, between 900 and 1100')
        && ! $prompt->contains('Fix these problems'));
    WriterAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('It must have between 900 and 1100 words'));
    // La correction reste trop courte : l'article est garde, l'ecart reste au journal de l'etape.
    expect(collect($result->output['warnings'])->contains(fn (string $warning): bool => str_starts_with($warning, 'The body has')))->toBeTrue();
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
        ->toThrow(BlogAiProviderException::class, 'unusable answer');
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

it('sends back once a check that adds a figure or drops an image, then refuses it', function (string $body) {
    $answer = [...Fixtures::article(['body_markdown' => $body]), 'removed_claims' => [], 'report_fr' => 'Rien.'];
    FactCheckAgent::fake([$answer, $answer])->preventStrayPrompts();

    expect(fn () => app(VerificationStep::class)->execute(Fixtures::context(['research' => Fixtures::research(), 'writing' => Fixtures::articleOutput()])))
        ->toThrow(BlogAiProviderException::class, 'adds material or loses an image');
    FactCheckAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Your answer added material the article to check does not contain'));
})->with([
    'a new figure' => fn () => Fixtures::body().' About 30,000 sellers are concerned.',
    'a lost image' => fn () => str_replace('[[image:1]]', '', Fixtures::body()),
]);

it('keeps the check corrected after being told what it added', function () {
    // Generation 79 du 18/11 : la verification ajoutait les numeros d'articles de la fiche des regles de l'UE,
    // et trois nouveaux essais a l'aveugle ont refait la meme faute.
    FactCheckAgent::fake([
        [...Fixtures::article(['body_markdown' => Fixtures::body().' Article 19 of the GPSR applies.']), 'removed_claims' => [], 'report_fr' => 'x'],
        [...Fixtures::article(), 'removed_claims' => [], 'report_fr' => 'Rien à retirer.'],
    ])->preventStrayPrompts();

    $result = app(VerificationStep::class)->execute(Fixtures::context(['research' => Fixtures::research(), 'writing' => Fixtures::articleOutput()]));

    expect($result->output['article']['body'])->toBe(Fixtures::body())
        ->and($result->summary)->toBe('Rien à retirer.');
    FactCheckAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('numbers changed: missing [], added [19]'));
});

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

it('sends the sentences that still show signs back once, and keeps that second pass when it changed no fact', function () {
    $marked = Fixtures::body()."\n\nThere are four things to do. What you can see is the status of your shop. Where those pages stop, this guide stops too.";
    StyleAgent::fake([
        [...Fixtures::article(['body_markdown' => $marked]), 'report_fr' => 'Style revu.'],
        [...Fixtures::article(), 'report_fr' => 'Trois phrases reprises.'],
    ])->preventStrayPrompts();

    $result = app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]));

    expect($result->output['article']['body'])->toBe(Fixtures::body())
        ->and($result->output['second_pass'])->toBe(['flagged_before' => 3, 'applied' => true, 'differences' => []])
        ->and($result->output['signals']['flagged'])->toBe([]);
    StyleAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Sentences that still show signs of generated text')
        && $prompt->contains('- There are four things to do. (list announced by its count)'));
});

it('asks for no second pass when no sentence shows a sign', function () {
    StyleAgent::fake([[...Fixtures::article(), 'report_fr' => 'Style revu.']])->preventStrayPrompts();

    $result = app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]));

    // Aucune phrase relevee : un seul appel (preventStrayPrompts le garantit).
    expect($result->output['second_pass'])->toBeNull()
        ->and($result->output['signals']['flagged'])->toBe([]);
});

it('allows as many flagged sentences as the setting says before a second pass', function () {
    config(['blog.ai.style.max_flagged_sentences' => 1]);
    StyleAgent::fake([[...Fixtures::article(['body_markdown' => Fixtures::body()."\n\nThere are four things to do."]), 'report_fr' => 'Style revu.']])->preventStrayPrompts();

    $result = app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]));

    expect($result->output['second_pass'])->toBeNull()
        ->and($result->output['signals']['flagged'])->toHaveCount(1);
});

it('keeps the first style edit when the second pass changes a figure', function () {
    $marked = Fixtures::body()."\n\nThere are four things to do. What you can see is the status of your shop. Where those pages stop, this guide stops too.";
    StyleAgent::fake([
        [...Fixtures::article(['body_markdown' => $marked]), 'report_fr' => 'Style revu.'],
        [...Fixtures::article(['body_markdown' => str_replace('2024', '2025', Fixtures::body())]), 'report_fr' => 'x'],
    ])->preventStrayPrompts();

    $result = app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]));

    expect($result->output['article']['body'])->toBe($marked)
        ->and($result->output['second_pass']['applied'])->toBeFalse()
        ->and($result->output['second_pass']['differences'])->toBe(['numbers changed: missing [2024], added [2025]']);
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
        ."## Les informations demandées par Etsy\n\nEtsy le demande dans les réglages de l'annonce. [Vérifier votre cas](festilaw:page/quiz).\n\n[[image:1]]\n\n"
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

it('sends the French sentences that still show signs back once, and keeps that second pass when links and images are intact', function () {
    $french = "Depuis le 13 décembre 2024, chaque produit vendu dans l'UE a besoin d'un opérateur économique. [Lire le règlement](https://eur-lex.europa.eu/eli/reg/2023/988/oj).\n\n"
        ."## Les informations demandées par Etsy\n\nEtsy le demande dans les réglages de l'annonce. [Vérifier votre cas](festilaw:page/quiz).\n\n[[image:1]]\n\n"
        ."## La suite\n\nRassemblez les informations avant de modifier vos annonces. [Voir nos tarifs](festilaw:page/pricing).";
    $marked = str_replace('Rassemblez les informations', 'Voici la suite. Il y a trois choses à faire. Rassemblez les informations', $french);
    TranslatorAgent::fake([
        [...Fixtures::article(['title' => 'Ajouter un opérateur économique sur Etsy', 'body_markdown' => $marked]), 'report_fr' => 'x'],
        [...Fixtures::article(['title' => 'Ajouter un opérateur économique sur Etsy', 'body_markdown' => $french]), 'report_fr' => 'Deux phrases reprises.'],
    ])->preventStrayPrompts();

    $result = app()->make(TranslationStep::class, ['locale' => 'fr'])->execute(Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput()]));

    expect($result->output['article']['body'])->toBe($french)
        ->and($result->output['second_pass'])->toBe(['flagged_before' => 2, 'applied' => true, 'differences' => []])
        ->and($result->output['signals']['flagged'])->toBe([]);
    TranslatorAgent::assertPrompted(fn ($prompt): bool => $prompt->contains('Sentences that still show signs of generated text')
        && $prompt->contains('- Il y a trois choses à faire. (liste annoncee par son nombre)'));
});

it('has the French version written from the English material with the French press charter, sends a formula title back, and sets French typography', function () {
    // Brouillon 99 du 18/11 : une traduction gardait le squelette anglais (titre « Ce qu'un acheteur UE… »).
    $body = "Depuis le 13 décembre 2024, chaque offre vise un opérateur économique. [Lire le règlement](https://eur-lex.europa.eu/eli/reg/2023/988/oj).\n\n"
        ."## Les informations demandées par Etsy\n\nEtsy demande le champ \"economic operator\". [Vérifier votre cas](festilaw:page/quiz).\n\n[[image:1]]\n\n"
        ."## La suite\n\nRassemblez les informations avant de modifier vos annonces. [Voir nos tarifs](festilaw:page/pricing).";
    TranslatorAgent::fake([
        [...Fixtures::article(['title' => "Ce qu'un acheteur UE doit voir sur Etsy", 'body_markdown' => $body]), 'report_fr' => 'x'],
        [...Fixtures::article(['title' => 'Les mentions obligatoires des annonces Etsy en Europe', 'body_markdown' => $body]), 'report_fr' => 'Titre repris.'],
    ])->preventStrayPrompts();

    $result = app()->make(TranslationStep::class, ['locale' => 'fr'])->execute(Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput()]));

    expect($result->output['article']['title'])->toBe('Les mentions obligatoires des annonces Etsy en Europe')
        ->and($result->output['second_pass']['applied'])->toBeTrue()
        ->and($result->output['article']['body'])->toContain("le champ «\u{00A0}economic operator\u{00A0}»")
        ->and($result->output['article']['body'])->toContain('(festilaw:page/quiz)');
    TranslatorAgent::assertPrompted(fn ($prompt): bool => str_contains($prompt->agent->instructions(), "# Votre tâche, l'article en français")
        && str_contains($prompt->agent->instructions(), '## Le titre')
        && ! str_contains($prompt->agent->instructions(), 'Your task: write the'));
    TranslatorAgent::assertPrompted(fn ($prompt): bool => $prompt->contains("- Ce qu'un acheteur UE doit voir sur Etsy (formule de titre « Ce que… », « UE » employe comme adjectif (calque))"));
});

it('asks once for a correction when a translation loses a link or an image, then fails if it still does', function () {
    $lost = Fixtures::article(['body_markdown' => str_replace(['[[image:1]]', '(festilaw:page/quiz)'], ['', '(/quiz)'], Fixtures::body())]);
    TranslatorAgent::fake([[...$lost, 'report_fr' => 'x'], [...$lost, 'report_fr' => 'x']])->preventStrayPrompts();

    expect(fn () => app()->make(TranslationStep::class, ['locale' => 'es'])->execute(Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput()])))
        ->toThrow(BlogAiProviderException::class, 'image placeholders changed');
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
        'research' => [ResearchAgent::fake([[...Fixtures::research(), 'report_fr' => 'x']]), app(ResearchStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research_notes' => Fixtures::researchNotes()]))],
        'writing' => [WriterAgent::fake([Fixtures::writerAnswer()]), app(WritingStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research' => Fixtures::research()]))],
        'verification' => [FactCheckAgent::fake([[...Fixtures::article(), 'removed_claims' => [], 'report_fr' => 'x']]), app(VerificationStep::class)->execute(Fixtures::context(['research' => Fixtures::research(), 'writing' => Fixtures::articleOutput()]))],
        'style' => [StyleAgent::fake([[...Fixtures::article(), 'report_fr' => 'x']]), app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]))],
        'translation' => [TranslatorAgent::fake([[...Fixtures::article(), 'image_alts' => [], 'report_fr' => 'x']]), app()->make(TranslationStep::class, ['locale' => 'fr'])->execute(Fixtures::context(['writing' => Fixtures::writingOutput(), 'style' => Fixtures::articleOutput()]))],
    };

    $agent::assertPrompted(fn ($prompt): bool => str_starts_with($prompt->agent->instructions(), '# About Festilaw, the company you work for')
        && str_contains($prompt->agent->instructions(), '# Festilaw editorial guidelines'));
})->with(['topic', 'research', 'writing', 'verification', 'style', 'translation']);

it('gives the reference of EU product rules to the writer and the fact-checker only, who may rely on it', function () {
    // Generation 78 du 18/11 : la verification retirait des regles exactes absentes du dossier de faits
    // (marquage CE des jouets, role de la personne responsable), et le texte perdait 160 mots.
    Http::fake(['*' => Http::response('ok')]);
    WriterAgent::fake([Fixtures::writerAnswer()]);
    FactCheckAgent::fake([[...Fixtures::article(), 'removed_claims' => [], 'report_fr' => 'x']]);
    StyleAgent::fake([[...Fixtures::article(), 'report_fr' => 'x']]);

    app(WritingStep::class)->execute(Fixtures::context(['topic' => Fixtures::topic(), 'research' => Fixtures::research()]));
    app(VerificationStep::class)->execute(Fixtures::context(['research' => Fixtures::research(), 'writing' => Fixtures::articleOutput()]));
    app(StyleStep::class)->execute(Fixtures::context(['verification' => Fixtures::articleOutput()]));

    $withReference = fn ($prompt): bool => str_contains($prompt->agent->instructions(), '# Reference of EU product rules')
        && str_contains($prompt->agent->instructions(), 'toys made available on the market bear the CE marking (Article 16(1))');

    WriterAgent::assertPrompted(fn ($prompt): bool => $withReference($prompt)
        && str_contains($prompt->agent->instructions(), 'Use only facts from the research file and from the reference of EU product rules.'));
    FactCheckAgent::assertPrompted(fn ($prompt): bool => $withReference($prompt)
        && str_contains($prompt->agent->instructions(), 'Keep a statement only if the research file or the reference supports it.'));
    StyleAgent::assertPrompted(fn ($prompt): bool => ! str_contains($prompt->agent->instructions(), '# Reference of EU product rules'));
});
