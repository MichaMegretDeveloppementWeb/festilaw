<?php

use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\StyleAgent;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Ai\Agents\Blog\WriterAgent;
use App\Data\Blog\Generation\GenerationCostForecast;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogEffortLevel;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogEffortLevels;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Fourchette de cout d'un article, avant tout lancement : modeles choisis, longueur et images. Calcul pur
 | sur le profil `blog.ai.forecast`, cale sur les 17 articles du banc d'essai du 18/11. Aucun appel ; la base
 | ne sert qu'a lire les reglages par defaut.
 */

uses(RefreshDatabase::class);

/** Les modeles des essais du 17/11 : Haiku pour chercher et verifier, DeepSeek pour ecrire et traduire. */
function trialModels(): array
{
    return [
        'topic' => 'anthropic/claude-haiku-5.5',
        'research' => 'anthropic/claude-haiku-5.5',
        'writing' => 'deepseek/deepseek-v4.1-flash',
        'verification' => 'anthropic/claude-haiku-5.5',
        'translation' => 'deepseek/deepseek-v4.1-flash',
        'image' => 'openai/gpt-5.4-image-2',
    ];
}

function stockImage(): GenerationImageOptions
{
    return new GenerationImageOptions(BlogAiImageMode::Stock, 1);
}

it('brackets what each article of the model bench cost, the high staying under one and a half times the low', function (array $models, array $realCosts, float $width) {
    // Banc d'essai du 18/11 : sujet impose, 1 000 mots, sujet prepare compris, image a part, et les etapes que le
    // banc jouait alors (le controle des versions et la recherche des preuves sont venus ensuite). Sa reflexion est
    // celle du niveau Faible d'alors, et il faisait trois recherches de sources : ces conditions sont posees ici.
    config(['blog.ai.effort.levels.low' => ['reasoning' => ['default' => 'low', 'topic' => 'none', 'research' => 'none'], 'searches' => ['research_notes' => 3]]]);
    app()->forgetInstance(BlogEffortLevels::class);
    app()->forgetInstance(BlogGenerationCostForecaster::class);
    $forecast = app(BlogGenerationCostForecaster::class)->forecast([
        'topic' => 'anthropic/claude-haiku-5.5', 'research' => 'anthropic/claude-haiku-5.5', 'verification' => 'anthropic/claude-haiku-5.5', ...$models,
    ], 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['topic', 'research_notes', 'research', 'writing', 'verification', 'style', 'translation_fr', 'translation_es'], effort: BlogEffortLevel::Low);
    $low = $forecast->lowMicrousd;
    $high = $forecast->highMicrousd;

    expect($low)->toBeLessThanOrEqual(min($realCosts))
        ->and($high)->toBeGreaterThanOrEqual(max($realCosts))
        ->and($high)->toBeLessThanOrEqual((int) ($low * $width));
})->with([
    'Haiku + DeepSeek' => [['writing' => 'deepseek/deepseek-v4.1-flash', 'translation' => 'deepseek/deepseek-v4.1-flash'], [38_829, 42_477, 44_470, 44_579, 49_306], 1.5],
    'Haiku seul' => [['writing' => 'anthropic/claude-haiku-5.5', 'translation' => 'anthropic/claude-haiku-5.5'], [54_921, 57_277, 58_460, 59_380, 60_773], 1.5],
    'Haiku + Luna' => [['writing' => 'openai/gpt-6-luna', 'translation' => 'openai/gpt-6-luna'], [44_176, 44_677, 45_502, 45_830, 48_410], 1.5],
    'Haiku + Sonnet' => [['writing' => 'anthropic/claude-sonnet-5.5', 'translation' => 'anthropic/claude-sonnet-5.5'], [426_158, 434_544], 1.5],
]);

it('costs more for a longer article and with generated images, and less than a cent more for stock photos', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $none = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::None, 1));
    $short = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::None, 1));
    $long = $forecaster->forecast(trialModels(), 2_000, new GenerationImageOptions(BlogAiImageMode::None, 1));
    $stock = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::Stock, 3));
    $generated = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::Generated, 3));

    expect($long->lowMicrousd)->toBeGreaterThan($short->lowMicrousd)
        ->and($long->highMicrousd)->toBeGreaterThan($short->highMicrousd)
        // Une photo de la banque est gratuite ; le controle qui la regarde (remarque de Micha du 20/11) coute un
        // appel en bas et jusqu'a quatre par image en haut, moins d'un centime pour trois images.
        ->and($stock->lowMicrousd)->toBeGreaterThan($none->lowMicrousd)
        ->and($stock->highMicrousd - $none->highMicrousd)->toBeLessThan(10_000)
        // GPT-5.4 Image 2, prix mesures le 19/11 : l'image principale a 0,0053 $ en bas, trois images a 0,0109 $ en haut.
        ->and($generated->lowMicrousd - $short->lowMicrousd)->toBe(5_300)
        ->and($generated->highMicrousd - $short->highMicrousd)->toBe(32_700);
});

it('follows the price of the models chosen, and leaves the web scan out when the topic is imposed', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $cheap = $forecaster->forecast(trialModels(), 1_000, stockImage());
    $dear = $forecaster->forecast([...trialModels(), 'writing' => 'anthropic/claude-sonnet-5.5'], 1_000, stockImage());
    $imposed = $forecaster->forecast(trialModels(), 1_000, stockImage(), topicImposed: true);

    expect($dear->highMicrousd)->toBeGreaterThan($cheap->highMicrousd)
        ->and($imposed->lowMicrousd)->toBeLessThan($cheap->lowMicrousd);
});

it('keeps the default settings around thirty cents an article, a model billed at its catalog price counting at that price', function () {
    $settings = app(BlogAiSettingsService::class)->current();
    $forecast = app(BlogGenerationCostForecaster::class)->forSettings($settings);

    // Une premiere version appliquait a tous les modeles l'ecart de prix mesure sur DeepSeek (2,8 fois le
    // catalogue). Depuis le 20/11, le niveau Moyen vise un haut de fourchette vers 0,30 $ (decision de Micha) : 0,33 $
    // pour un sujet libre, avec sa recherche de pistes.
    expect($settings->articleWords)->toBe(1_000)
        ->and($forecast->highMicrousd)->toBeLessThan(350_000);
});

it('brackets what each effort level cost with the recommended models, at the calibration of 20/11', function (BlogEffortLevel $effort, BlogAiImageMode $images, bool $withEvidence, array $realCosts) {
    // Niveaux du 20/11, sujets imposes (personne responsable, jouets), 1 000 mots. Moyen : generations 96 et 97,
    // une photo de la banque controlee. Eleve : generation 98, sans le premier essai de sa recherche des preuves,
    // coupe par la limite de 8 000 jetons relevee depuis (11,3 centimes). Faible n'a pas ete rejoue : hors
    // recherche des preuves, il reprend tel quel le Moyen du 19/11 (generations 94 et 95, photo prise sans
    // controle), et ses preuves restent estimees.
    // La description d'une image demandee depuis un article (24/11) n'est pas une etape d'article.
    $steps = $withEvidence ? null : array_values(array_diff(array_keys(config('blog.ai.forecast.steps')), ['fact_search', 'image_brief']));
    $forecast = app(BlogGenerationCostForecaster::class)->forecast(config('blog.ai.defaults.models'), 1_000, new GenerationImageOptions($images, 1), topicImposed: true, onlySteps: $steps, effort: $effort);

    expect($forecast->lowMicrousd)->toBeLessThanOrEqual(min($realCosts))
        ->and($forecast->highMicrousd)->toBeGreaterThanOrEqual(max($realCosts))
        ->and($forecast->highMicrousd)->toBeLessThanOrEqual((int) ($forecast->lowMicrousd * 1.6));
})->with([
    'Faible' => [BlogEffortLevel::Low, BlogAiImageMode::None, false, [77_354, 89_765]],
    'Moyen' => [BlogEffortLevel::Medium, BlogAiImageMode::Stock, true, [224_840, 233_748]],
    'Eleve' => [BlogEffortLevel::High, BlogAiImageMode::Stock, true, [325_434]],
]);

it('keeps the description of a requested image out of the estimate of an article', function () {
    // Demande d'image depuis un article (24/11) : son etape ne compte que pour une demande d'image.
    $forecaster = app(BlogGenerationCostForecaster::class);
    $models = config('blog.ai.defaults.models');
    $images = new GenerationImageOptions(BlogAiImageMode::Stock, 1);

    $article = $forecaster->forecast($models, 1_000, $images);
    $withBrief = $forecaster->forecast($models, 1_000, $images, onlySteps: array_keys(config('blog.ai.forecast.steps')));

    expect($withBrief->highMicrousd)->toBeGreaterThan($article->highMicrousd);
});

it('counts one web search more than a limit above five at the top of the range, as OpenRouter let one pass', function () {
    // Etalonnage du 20/11 : 11 recherches pour 10, 9 pour 8, 19 pour 18, 16 pour 15.
    $forecaster = app(BlogGenerationCostForecaster::class);
    $search = config('blog.ai.web_search.price_microusd');
    $models = config('blog.ai.defaults.models');
    $research = $forecaster->forecast($models, 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['research_notes'], effort: BlogEffortLevel::Medium);
    $dossier = $forecaster->forecast($models, 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['research'], effort: BlogEffortLevel::Medium);

    expect($research->lowMicrousd)->toBeGreaterThan(10 * $search)
        ->and($research->highMicrousd)->toBeGreaterThan(11 * $search)
        ->and($dossier->highMicrousd)->toBeLessThan($search);
});

it('costs less at a low effort and more at a high one, both in searches and in reasoning', function () {
    // Regles de Micha du 19/11 et du 20/11 : chaque niveau change les recherches (pistes, sources et preuves :
    // 2 + 3 + 3, 4 + 10 + 8, 6 + 18 + 15) et la reflexion, Moyen par defaut.
    $forecaster = app(BlogGenerationCostForecaster::class);
    $at = fn (BlogEffortLevel $effort): GenerationCostForecast => $forecaster->forecast(trialModels(), 1_000, stockImage(), effort: $effort);
    $search = config('blog.ai.web_search.price_microusd');

    expect($forecaster->forecast(trialModels(), 1_000, stockImage()))->toEqual($at(BlogEffortLevel::Medium))
        // Quatorze recherches de moins, et moins de reflexion par-dessus.
        ->and($at(BlogEffortLevel::Medium)->lowMicrousd - $at(BlogEffortLevel::Low)->lowMicrousd)->toBeGreaterThan(14 * $search)
        ->and($at(BlogEffortLevel::Medium)->highMicrousd - $at(BlogEffortLevel::Low)->highMicrousd)->toBeGreaterThan(14 * $search)
        // Dix-sept recherches de plus, et plus de reflexion par-dessus.
        ->and($at(BlogEffortLevel::High)->lowMicrousd - $at(BlogEffortLevel::Medium)->lowMicrousd)->toBeGreaterThan(17 * $search)
        ->and($at(BlogEffortLevel::High)->highMicrousd - $at(BlogEffortLevel::Medium)->highMicrousd)->toBeGreaterThan(17 * $search);
});

it('counts a model never measured at its catalog price at the bottom, and with a margin at the top', function () {
    config(['blog.ai.models' => [
        ...config('blog.ai.models'),
        'test/measured' => ['name' => 'Measured', 'provider' => 'Test', 'input' => 10, 'output' => 50, 'price_margin_percent' => 100],
        'test/never-measured' => ['name' => 'Never measured', 'provider' => 'Test', 'input' => 10, 'output' => 50],
    ]]);
    $forecaster = app(BlogGenerationCostForecaster::class);
    $never = $forecaster->forecast([...trialModels(), 'writing' => 'test/never-measured', 'translation' => 'test/never-measured'], 1_000, stockImage());
    $measured = $forecaster->forecast([...trialModels(), 'writing' => 'test/measured', 'translation' => 'test/measured'], 1_000, stockImage());

    // Meme prix au catalogue : seule la borne haute du modele jamais mesure porte une marge.
    expect($never->lowMicrousd)->toBe($measured->lowMicrousd)
        ->and($never->highMicrousd)->toBeGreaterThan($measured->highMicrousd);
});

it('counts the tokens of an Anthropic model one and a half times, as the bench measured them', function () {
    config(['blog.ai.models' => [
        ...config('blog.ai.models'),
        'test/reference' => ['name' => 'Reference', 'provider' => 'Test', 'input' => 10, 'output' => 50, 'price_margin_percent' => 100],
        'test/anthropic-like' => ['name' => 'Anthropic like', 'provider' => 'Test', 'input' => 10, 'output' => 50, 'price_margin_percent' => 100, 'token_factor_percent' => 145],
    ]]);
    // Sans reflexion au dossier de faits (reglage pose pour ce test : aucun niveau ne la retire plus).
    config(['blog.ai.effort.levels.low.reasoning.research' => 'none']);
    app()->forgetInstance(BlogEffortLevels::class);
    app()->forgetInstance(BlogGenerationCostForecaster::class);
    $forecaster = app(BlogGenerationCostForecaster::class);
    $models = static fn (string $model): array => ['topic' => $model, 'research' => $model, 'writing' => $model, 'verification' => $model, 'translation' => $model];

    $reference = $forecaster->forecast($models('test/reference'), 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['research'], effort: BlogEffortLevel::Low);
    $anthropic = $forecaster->forecast($models('test/anthropic-like'), 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['research'], effort: BlogEffortLevel::Low);

    // Le dossier de faits ne reflechit pas : son cout suit les seuls jetons.
    expect($anthropic->lowMicrousd)->toBe(intdiv($reference->lowMicrousd * 145, 100));
});

it('gives the agents that write the article a token limit that grows with its length', function () {
    // DeepSeek a reflechi jusqu'a 11 200 jetons le 18/11 : la limite garde 14 000 jetons, plus deux fois la
    // reponse. Sans longueur connue, celle de l'article le plus long (2 000 mots).
    expect((new WriterAgent('', 1_200))->maxOutputTokens())->toBe(20_000)
        ->and((new StyleAgent('', 2_400))->maxOutputTokens())->toBe(26_000)
        ->and((new FactCheckAgent('', 480))->maxOutputTokens())->toBe(16_400)
        ->and((new TranslatorAgent)->maxOutputTokens())->toBe(24_000);
});
