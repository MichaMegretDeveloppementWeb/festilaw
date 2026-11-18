<?php

use App\Ai\Agents\Blog\FactCheckAgent;
use App\Ai\Agents\Blog\StyleAgent;
use App\Ai\Agents\Blog\TranslatorAgent;
use App\Ai\Agents\Blog\WriterAgent;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use Illuminate\Foundation\Testing\RefreshDatabase;

/*
 | Fourchette de cout d'un article, avant tout lancement : modeles choisis, longueur et images. Calcul pur
 | sur le profil `blog.ai.forecast`, cale sur la generation 77 du 17/11. Aucun appel ; la base ne sert qu'a
 | lire les reglages par defaut.
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

it('brackets what the first real articles cost, retries left aside', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $first = $forecaster->forecast(trialModels(), 522, stockImage());
    $second = $forecaster->forecast(trialModels(), 1_000, stockImage());

    // Generation 77 (522 mots) : 0,0694 $ pour les appels qui ont abouti, 0,0929 $ avec trois nouveaux essais.
    // Generation 78 (1 000 mots demandes) : 0,0743 $ sans la verification rejetee, 0,0792 $ avec.
    expect($first->lowMicrousd)->toBeLessThan(69_390)
        ->and($first->highMicrousd)->toBeGreaterThan(69_390)
        ->and($second->lowMicrousd)->toBeLessThan(74_297)
        ->and($second->highMicrousd)->toBeGreaterThan(79_183);
});

it('costs more for a longer article and with generated images, and nothing more for stock photos', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $short = $forecaster->forecast(trialModels(), 400, stockImage());
    $long = $forecaster->forecast(trialModels(), 2_000, stockImage());
    $generated = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::Generated, 3));
    $none = $forecaster->forecast(trialModels(), 400, new GenerationImageOptions(BlogAiImageMode::None, 1));

    expect($long->lowMicrousd)->toBeGreaterThan($short->lowMicrousd)
        ->and($long->highMicrousd)->toBeGreaterThan($short->highMicrousd)
        ->and($none->highMicrousd)->toBe($short->highMicrousd)
        // GPT-5 Image Mini : la moitie de sa reserve en bas (l'image principale), trois reserves en haut.
        ->and($generated->lowMicrousd - $short->lowMicrousd)->toBe(15_000)
        ->and($generated->highMicrousd - $short->highMicrousd)->toBe(90_000);
});

it('follows the price of the models chosen, and leaves the web scan out when the topic is imposed', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $cheap = $forecaster->forecast(trialModels(), 1_000, stockImage());
    $dear = $forecaster->forecast([...trialModels(), 'writing' => 'anthropic/claude-sonnet-5.5'], 1_000, stockImage());
    $imposed = $forecaster->forecast(trialModels(), 1_000, stockImage(), topicImposed: true);

    expect($dear->highMicrousd)->toBeGreaterThan($cheap->highMicrousd)
        ->and($imposed->lowMicrousd)->toBeLessThan($cheap->lowMicrousd)
        ->and($forecaster->forecast(trialModels(), 1_000, stockImage())->exceeds($cheap->highMicrousd))->toBeFalse()
        ->and($cheap->exceeds($cheap->highMicrousd - 1))->toBeTrue();
});

it('keeps the default settings under the default cap per article, a model billed at its catalog price counting at that price', function () {
    $settings = app(BlogAiSettingsService::class)->current();
    $forecast = app(BlogGenerationCostForecaster::class)->forSettings($settings);

    // Une premiere version appliquait a tous les modeles l'ecart de prix mesure sur DeepSeek (2,8 fois le
    // catalogue) : les reglages par defaut, tout en Haiku, depassaient alors les 15 centimes.
    expect($settings->articleWords)->toBe(1_000)
        ->and($forecast->exceeds($settings->articleSpendCapMicrousd()))->toBeFalse();
});

it('counts a model never measured at its catalog price at the bottom, and with a margin at the top', function () {
    $forecaster = app(BlogGenerationCostForecaster::class);
    $luna = $forecaster->forecast([...trialModels(), 'writing' => 'openai/gpt-6-luna', 'translation' => 'openai/gpt-6-luna'], 1_000, stockImage());
    $haiku = $forecaster->forecast([...trialModels(), 'writing' => 'anthropic/claude-haiku-5.5', 'translation' => 'anthropic/claude-haiku-5.5'], 1_000, stockImage());

    // Meme prix au catalogue : seule la borne haute de Luna, jamais mesure, porte une marge.
    expect($luna->lowMicrousd)->toBe($haiku->lowMicrousd)
        ->and($luna->highMicrousd)->toBeGreaterThan($haiku->highMicrousd);
});

it('gives the agents that write the article a token limit that grows with its length', function () {
    // DeepSeek a reflechi jusqu'a 11 200 jetons le 18/11 : la limite garde 14 000 jetons, plus deux fois la
    // reponse. Sans longueur connue, celle de l'article le plus long (2 000 mots).
    expect((new WriterAgent('', 1_200))->maxOutputTokens())->toBe(20_000)
        ->and((new StyleAgent('', 2_400))->maxOutputTokens())->toBe(26_000)
        ->and((new FactCheckAgent('', 480))->maxOutputTokens())->toBe(16_400)
        ->and((new TranslatorAgent)->maxOutputTokens())->toBe(24_000);
});
