<?php

use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Enums\Blog\Generation\BlogAiPeriodUnit;
use App\Services\Blog\Generation\BlogAiCostEstimator;
use App\Services\Blog\Generation\BlogAiCostFormatter;
use App\Services\Blog\Generation\OpenRouterUsageReader;
use Carbon\CarbonImmutable;

/*
 | Calculs purs de la generation par IA : cout de repli d'apres la table de tarifs, lecture de la
 | consommation dans la reponse d'OpenRouter, affichage d'un cout, bornes des reglages, periode.
 */

/** Tarifs en cents de dollar par million de jetons : 2 $ en entree, 10 $ en sortie. */
function blogAiEstimator(): BlogAiCostEstimator
{
    return new BlogAiCostEstimator(['test/model' => ['input' => 200, 'output' => 1_000]], 10_000);
}

it('estimates a cost from the price table, in millionths of a dollar', function () {
    $estimator = blogAiEstimator();

    // 1 000 000 jetons en entree a 2 $ le million = 2 $ ; 100 000 en sortie a 10 $ le million = 1 $.
    expect($estimator->estimateMicrousd('test/model', 1_000_000, 100_000))->toBe(3_000_000)
        ->and($estimator->estimateMicrousd('test/model', 1_500, 600))->toBe(9_000)
        ->and($estimator->estimateMicrousd('test/model', 0, 0, webSearches: 3))->toBe(30_000)
        ->and($estimator->estimateMicrousd('unknown/model', 1_000_000, 1_000_000, webSearches: 1))->toBe(10_000)
        ->and($estimator->estimateMicrousd(null, 10, 10))->toBe(0);
});

it('reads the real cost and the consumption from an OpenRouter response', function () {
    $usage = (new OpenRouterUsageReader(blogAiEstimator()))->read([
        'model' => 'anthropic/claude-sonnet-5.5',
        'usage' => [
            'prompt_tokens' => 1_200,
            'completion_tokens' => 350,
            'total_tokens' => 1_550,
            'prompt_tokens_details' => ['cached_tokens' => 800],
            'completion_tokens_details' => ['reasoning_tokens' => 120],
            'cost' => 0.004215,
            'server_tool_use_details' => ['web_search_requests' => 2],
        ],
    ], 'requested/model');

    expect($usage->model)->toBe('anthropic/claude-sonnet-5.5')
        ->and($usage->inputTokens)->toBe(1_200)
        ->and($usage->outputTokens)->toBe(350)
        ->and($usage->cachedTokens)->toBe(800)
        ->and($usage->reasoningTokens)->toBe(120)
        ->and($usage->webSearches)->toBe(2)
        ->and($usage->costMicrousd)->toBe(4_215)
        ->and($usage->costIsEstimated)->toBeFalse();
});

it('estimates the cost, and says so, when the response carries none', function () {
    $reader = new OpenRouterUsageReader(blogAiEstimator());

    $withoutCost = $reader->read(['usage' => ['prompt_tokens' => 1_000_000, 'completion_tokens' => 100_000]], 'test/model');
    $withoutUsage = $reader->read([], 'test/model');
    $otherNames = $reader->read(['usage' => ['input_tokens' => 10, 'output_tokens' => 4, 'cost' => 0]]);

    expect($withoutCost->model)->toBe('test/model')
        ->and($withoutCost->costMicrousd)->toBe(3_000_000)
        ->and($withoutCost->costIsEstimated)->toBeTrue()
        ->and($withoutUsage->costMicrousd)->toBe(0)
        ->and($withoutUsage->costIsEstimated)->toBeTrue()
        ->and($otherNames->inputTokens)->toBe(10)
        ->and($otherNames->outputTokens)->toBe(4)
        ->and($otherNames->costIsEstimated)->toBeFalse();
});

it('adds up two consumptions', function () {
    $total = (new GenerationUsage(model: 'a', inputTokens: 10, outputTokens: 2, webSearches: 1, costMicrousd: 300))
        ->plus(new GenerationUsage(inputTokens: 5, cachedTokens: 3, reasoningTokens: 1, costMicrousd: 200, costIsEstimated: true));

    expect($total->model)->toBe('a')
        ->and($total->inputTokens)->toBe(15)
        ->and($total->outputTokens)->toBe(2)
        ->and($total->cachedTokens)->toBe(3)
        ->and($total->reasoningTokens)->toBe(1)
        ->and($total->webSearches)->toBe(1)
        ->and($total->costMicrousd)->toBe(500)
        ->and($total->costIsEstimated)->toBeTrue();
});

it('shows a cost in dollars with four decimals', function (int $microusd, string $expected) {
    expect((new BlogAiCostFormatter)->dollars($microusd))->toBe($expected);
})->with([
    [0, '0,0000 $'],
    [2_341, '0,0023 $'],
    [3_460, '0,0035 $'],
    [12_500, '0,0125 $'],
    [100_000, '0,1000 $'],
    [262_500, '0,2625 $'],
    [10_000_000, '10,0000 $'],
    [1_234_560_000, '1 234,5600 $'],
]);

it('brings stored settings back within their bounds', function () {
    $defaults = [
        'enabled' => false, 'period_count' => 1, 'period_unit' => 'week', 'articles_per_run' => 1,
        'pending_drafts_cap' => 3, 'monthly_spend_cap_cents' => 1_000, 'priority_topics' => '', 'avoided_topics' => '',
        'models' => ['writing' => 'default/model'], 'image_mode' => 'stock', 'max_images' => 1,
        'notification_email' => null, 'transparency_notice' => ['en' => '', 'fr' => ''], 'article_words' => 1_000,
    ];
    $length = new ArticleLengthRules(minWords: 400, maxWords: 2_000, askedTolerancePercent: 10, acceptedTolerancePercent: 20);

    $settings = BlogAiSettings::fromStored([
        'enabled' => 1,
        'period_count' => 0,
        'period_unit' => 'century',
        'articles_per_run' => -4,
        'pending_drafts_cap' => 0,
        'monthly_spend_cap_cents' => -50,
        'models' => ['writing' => '   ', 'not-a-step' => 'x'],
        'image_mode' => 'hologram',
        'max_images' => 9,
        'notification_email' => '  ',
        'transparency_notice' => ['fr' => '  Rédigé avec une IA.  ', 'de' => 'x'],
        'article_words' => 5_000,
    ], $defaults, 'team@example.com', ['en', 'fr'], $length);

    expect($settings->enabled)->toBeTrue()
        ->and($settings->periodCount)->toBe(1)
        ->and($settings->periodUnit)->toBe(BlogAiPeriodUnit::Week)
        ->and($settings->articlesPerRun)->toBe(1)
        ->and($settings->pendingDraftsCap)->toBe(1)
        ->and($settings->monthlySpendCapCents)->toBe(0)
        ->and($settings->models)->toBe(['writing' => 'default/model'])
        ->and($settings->imageMode)->toBe(BlogAiImageMode::Stock)
        ->and($settings->maxImages)->toBe(3)
        ->and($settings->notificationEmail)->toBe('team@example.com')
        ->and($settings->transparencyNotice)->toBe(['en' => '', 'fr' => 'Rédigé avec une IA.'])
        ->and($settings->articleWords)->toBe(2_000)
        ->and(BlogAiSettings::fromStored($settings->toStored(), $defaults, 'other@example.com', ['en', 'fr'], $length))->toEqual($settings);
});

it('adds a period to a date', function () {
    $from = CarbonImmutable::parse('2026-01-31 06:00:00');

    expect(BlogAiPeriodUnit::Day->addTo($from, 3)->toDateString())->toBe('2026-02-03')
        ->and(BlogAiPeriodUnit::Week->addTo($from, 2)->toDateString())->toBe('2026-02-14')
        // Pas de debordement : un mois apres le 31 janvier, c'est le dernier jour de fevrier.
        ->and(BlogAiPeriodUnit::Month->addTo($from, 1)->toDateString())->toBe('2026-02-28');
});
