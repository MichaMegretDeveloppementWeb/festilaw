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
        'image' => 'openai/gpt-5-image-mini',
    ];
}

function stockImage(): GenerationImageOptions
{
    return new GenerationImageOptions(BlogAiImageMode::Stock, 1);
}

it('brackets what each article of the model bench cost, the high staying under one and a half times the low', function (array $models, array $realCosts, float $width) {
    // Banc d'essai du 18/11 : sujet impose, 1 000 mots, sujet prepare compris, image a part, et les etapes que le
    // banc jouait alors (le controle des versions est venu ensuite). Sa reflexion est celle du niveau Faible, mais
    // il faisait une recherche de sources de plus : elle s'ajoute a l'estimation de ce niveau.
    $forecast = app(BlogGenerationCostForecaster::class)->forecast([
        'topic' => 'anthropic/claude-haiku-5.5', 'research' => 'anthropic/claude-haiku-5.5', 'verification' => 'anthropic/claude-haiku-5.5', ...$models,
    ], 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['topic', 'research_notes', 'research', 'writing', 'verification', 'style', 'translation_fr', 'translation_es'], effort: BlogEffortLevel::Low);
    $low = $forecast->lowMicrousd + config('blog.ai.web_search.price_microusd');
    $high = $forecast->highMicrousd + config('blog.ai.web_search.price_microusd');

    expect($low)->toBeLessThanOrEqual(min($realCosts))
        ->and($high)->toBeGreaterThanOrEqual(max($realCosts))
        ->and($high)->toBeLessThanOrEqual((int) ($low * $width));
})->with([
    'Haiku + DeepSeek' => [['writing' => 'deepseek/deepseek-v4.1-flash', 'translation' => 'deepseek/deepseek-v4.1-flash'], [38_829, 42_477, 44_470, 44_579, 49_306], 1.5],
    'Haiku seul' => [['writing' => 'anthropic/claude-haiku-5.5', 'translation' => 'anthropic/claude-haiku-5.5'], [54_921, 57_277, 58_460, 59_380, 60_773], 1.5],
    'Haiku + Luna' => [['writing' => 'openai/gpt-6-luna', 'translation' => 'openai/gpt-6-luna'], [44_176, 44_677, 45_502, 45_830, 48_410], 1.5],
    'Haiku + Sonnet' => [['writing' => 'anthropic/claude-sonnet-5.5', 'translation' => 'anthropic/claude-sonnet-5.5'], [426_158, 434_544], 1.5],
]);

it('costs more for a longer article and with generated images, and nothing more for stock photos', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $short = $forecaster->forecast(trialModels(), 400, stockImage());
    $long = $forecaster->forecast(trialModels(), 2_000, stockImage());
    $generated = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::Generated, 3));
    $none = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::None, 1));

    expect($long->lowMicrousd)->toBeGreaterThan($short->lowMicrousd)
        ->and($long->highMicrousd)->toBeGreaterThan($short->highMicrousd)
        ->and($none->highMicrousd)->toBe($short->highMicrousd)
        // GPT-5 Image Mini, prix mesures le 18/11 : l'image principale a 0,0127 $ en bas, trois images a 0,0498 $ en haut.
        ->and($generated->lowMicrousd - $short->lowMicrousd)->toBe(12_700)
        ->and($generated->highMicrousd - $short->highMicrousd)->toBe(149_400);
});

it('follows the price of the models chosen, and leaves the web scan out when the topic is imposed', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $cheap = $forecaster->forecast(trialModels(), 1_000, stockImage());
    $dear = $forecaster->forecast([...trialModels(), 'writing' => 'anthropic/claude-sonnet-5.5'], 1_000, stockImage());
    $imposed = $forecaster->forecast(trialModels(), 1_000, stockImage(), topicImposed: true);

    expect($dear->highMicrousd)->toBeGreaterThan($cheap->highMicrousd)
        ->and($imposed->lowMicrousd)->toBeLessThan($cheap->lowMicrousd);
});

it('keeps the default settings at a few cents an article, a model billed at its catalog price counting at that price', function () {
    $settings = app(BlogAiSettingsService::class)->current();
    $forecast = app(BlogGenerationCostForecaster::class)->forSettings($settings);

    // Une premiere version appliquait a tous les modeles l'ecart de prix mesure sur DeepSeek (2,8 fois le
    // catalogue) : les reglages par defaut, tout en Haiku, depassaient alors 15 centimes.
    expect($settings->articleWords)->toBe(1_000)
        ->and($forecast->highMicrousd)->toBeLessThan(150_000);
});

it('brackets what each effort level cost with the recommended models, at the calibration of 19/11', function (BlogEffortLevel $effort, int $extraSearches, array $realCosts) {
    // Sujets imposes (personne responsable, jouets), 1 000 mots, une image de la banque. Le niveau Faible reprend
    // la reflexion du banc d'essai du 19/11 (2 articles), qui faisait une recherche de sources de plus, ajoutee a
    // l'estimation a son prix mesure. Les niveaux Moyen (generations 94 et 95) et Eleve (92 et 93) sont mesures tels
    // quels.
    $forecast = app(BlogGenerationCostForecaster::class)->forecast(config('blog.ai.defaults.models'), 1_000, stockImage(), topicImposed: true, effort: $effort);
    $extra = $extraSearches * config('blog.ai.web_search.price_microusd');

    expect($forecast->lowMicrousd + $extra)->toBeLessThanOrEqual(min($realCosts))
        ->and($forecast->highMicrousd + $extra)->toBeGreaterThanOrEqual(max($realCosts))
        ->and($forecast->highMicrousd)->toBeLessThanOrEqual((int) ($forecast->lowMicrousd * 1.6));
})->with([
    'Faible' => [BlogEffortLevel::Low, 1, [64_912, 66_162]],
    'Moyen' => [BlogEffortLevel::Medium, 0, [77_354, 89_765]],
    'Eleve' => [BlogEffortLevel::High, 0, [120_223, 123_433]],
]);

it('costs less at a low effort and more at a high one, both in searches and in reasoning', function () {
    // Regle de Micha du 19/11 : chaque niveau change les recherches (1 + 2, 2 + 3, 3 + 5) et la reflexion, Moyen
    // par defaut.
    $forecaster = app(BlogGenerationCostForecaster::class);
    $at = fn (BlogEffortLevel $effort): GenerationCostForecast => $forecaster->forecast(trialModels(), 1_000, stockImage(), effort: $effort);
    $search = config('blog.ai.web_search.price_microusd');

    expect($forecaster->forecast(trialModels(), 1_000, stockImage()))->toEqual($at(BlogEffortLevel::Medium))
        // Deux recherches de moins (pistes et sources), et moins de reflexion par-dessus.
        ->and($at(BlogEffortLevel::Medium)->lowMicrousd - $at(BlogEffortLevel::Low)->lowMicrousd)->toBeGreaterThan(2 * $search)
        ->and($at(BlogEffortLevel::Medium)->highMicrousd - $at(BlogEffortLevel::Low)->highMicrousd)->toBeGreaterThan(2 * $search)
        // Trois recherches de plus, et plus de reflexion par-dessus.
        ->and($at(BlogEffortLevel::High)->lowMicrousd - $at(BlogEffortLevel::Medium)->lowMicrousd)->toBeGreaterThan(3 * $search)
        ->and($at(BlogEffortLevel::High)->highMicrousd - $at(BlogEffortLevel::Medium)->highMicrousd)->toBeGreaterThan(3 * $search);
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
    $forecaster = app(BlogGenerationCostForecaster::class);
    $models = static fn (string $model): array => ['topic' => $model, 'research' => $model, 'writing' => $model, 'verification' => $model, 'translation' => $model];

    $reference = $forecaster->forecast($models('test/reference'), 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['research'], effort: BlogEffortLevel::Low);
    $anthropic = $forecaster->forecast($models('test/anthropic-like'), 1_000, new GenerationImageOptions(BlogAiImageMode::None, 1), topicImposed: true, onlySteps: ['research'], effort: BlogEffortLevel::Low);

    // Au niveau Faible, l'etape du dossier de faits ne reflechit pas : son cout suit les seuls jetons.
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
