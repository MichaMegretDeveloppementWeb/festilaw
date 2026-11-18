<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Bench;

use App\Data\Blog\Generation\ArticleSpendLimit;
use App\Data\Blog\Generation\Bench\BlogBenchConfiguration;
use App\Data\Blog\Generation\Bench\BlogBenchPlan;
use App\Data\Blog\Generation\BlogAiSettings;
use App\Data\Blog\Generation\GenerationCostForecast;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;

/**
 * Le cout estime du banc d'essai, avant toute depense, avec le profil de l'estimation des articles
 * (BlogGenerationCostForecaster). Par sujet, la recherche et l'image ne se paient qu'une fois ; l'ecriture se
 * paie par configuration. Le cout d'un article de chaque configuration, recherche et image comprises, est
 * compare a son plafond augmente de la marge, comme au lancement d'une vraie generation. Calcul pur.
 */
final readonly class BlogBenchForecaster
{
    private const SHARED_STEPS = ['topic', 'research_notes', 'research'];

    private const ARTICLE_STEPS = ['writing', 'verification', 'style', 'translation_fr', 'translation_es'];

    public function __construct(
        private BlogGenerationCostForecaster $forecaster,
        private BlogAiModelCatalog $catalog,
        private ArticleSpendLimit $spendLimit,
    ) {}

    /**
     * @return array{shared: GenerationCostForecast, image: GenerationCostForecast, configurations: list<array{configuration: BlogBenchConfiguration, article: GenerationCostForecast, limit_microusd: int, exceeds: bool}>, total: GenerationCostForecast}
     */
    public function forecast(BlogBenchPlan $plan, BlogAiSettings $settings): array
    {
        $none = new GenerationImageOptions(BlogAiImageMode::None, 1);
        $shared = $this->forecaster->forecast($plan->sharedModels, $plan->words, $none, true, self::SHARED_STEPS);
        $reserve = $this->catalog->imageReserveMicrousd($plan->imageModel);
        $image = new GenerationCostForecast(intdiv($reserve, 2), $reserve);
        $configurations = [];
        $writingLow = 0;
        $writingHigh = 0;

        foreach ($plan->configurations as $configuration) {
            $writing = $this->forecaster->forecast([...$plan->sharedModels, ...$configuration->models], $plan->words, $none, true, self::ARTICLE_STEPS);
            $article = new GenerationCostForecast($shared->lowMicrousd + $image->lowMicrousd + $writing->lowMicrousd, $shared->highMicrousd + $image->highMicrousd + $writing->highMicrousd);
            $limit = $this->spendLimit->limit($configuration->articleCapCents === null ? $settings->articleSpendCapMicrousd() : $configuration->articleCapCents * 10_000);
            $configurations[] = ['configuration' => $configuration, 'article' => $article, 'limit_microusd' => $limit, 'exceeds' => $article->exceeds($limit)];
            $writingLow += $writing->lowMicrousd;
            $writingHigh += $writing->highMicrousd;
        }

        $topics = count($plan->topics);

        return [
            'shared' => $shared,
            'image' => $image,
            'configurations' => $configurations,
            'total' => new GenerationCostForecast(
                $topics * ($shared->lowMicrousd + $image->lowMicrousd + $writingLow),
                $topics * ($shared->highMicrousd + $image->highMicrousd + $writingHigh),
            ),
        ];
    }
}
