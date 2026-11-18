<?php

declare(strict_types=1);

namespace App\Services\Blog\Generation\Bench;

use App\Data\Blog\Generation\Bench\BlogBenchConfiguration;
use App\Data\Blog\Generation\Bench\BlogBenchPlan;
use App\Data\Blog\Generation\GenerationCostForecast;
use App\Data\Blog\Generation\GenerationImageOptions;
use App\Data\Blog\Generation\GenerationSafetyStop;
use App\Enums\Blog\Generation\BlogAiImageMode;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;

/**
 * Le cout annonce du banc d'essai, avant toute depense, avec le profil de l'estimation des articles
 * (BlogGenerationCostForecaster). Par sujet, la recherche et l'image ne se paient qu'une fois ; l'ecriture se
 * paie par configuration. Comme une vraie generation, chaque article s'arrete par securite au double du haut
 * annonce pour sa configuration, recherche et image comprises (GenerationSafetyStop). Calcul pur.
 */
final readonly class BlogBenchForecaster
{
    private const SHARED_STEPS = ['topic', 'research_notes', 'research'];

    private const ARTICLE_STEPS = ['writing', 'verification', 'style', 'translation_fr', 'translation_es'];

    public function __construct(
        private BlogGenerationCostForecaster $forecaster,
        private BlogAiModelCatalog $catalog,
        private GenerationSafetyStop $safetyStop,
    ) {}

    /** Le cadrage du sujet et la recherche, partages par les configurations. */
    public function shared(BlogBenchPlan $plan): GenerationCostForecast
    {
        return $this->forecaster->forecast($plan->sharedModels, $plan->words, $this->noImage(), true, self::SHARED_STEPS);
    }

    /** Un article d'une configuration tel qu'une vraie generation l'annoncerait : recherche et image comprises. */
    public function article(BlogBenchPlan $plan, BlogBenchConfiguration $configuration): GenerationCostForecast
    {
        $shared = $this->shared($plan);
        $image = $this->image($plan);
        $writing = $this->writing($plan, $configuration);

        return new GenerationCostForecast(
            $shared->lowMicrousd + $image->lowMicrousd + $writing->lowMicrousd,
            $shared->highMicrousd + $image->highMicrousd + $writing->highMicrousd,
        );
    }

    /**
     * @return array{shared: GenerationCostForecast, image: GenerationCostForecast, configurations: list<array{configuration: BlogBenchConfiguration, article: GenerationCostForecast, stop_microusd: int}>, total: GenerationCostForecast}
     */
    public function forecast(BlogBenchPlan $plan): array
    {
        $shared = $this->shared($plan);
        $image = $this->image($plan);
        $configurations = [];
        $writingLow = 0;
        $writingHigh = 0;

        foreach ($plan->configurations as $configuration) {
            $writing = $this->writing($plan, $configuration);
            $article = $this->article($plan, $configuration);
            $configurations[] = ['configuration' => $configuration, 'article' => $article, 'stop_microusd' => $this->safetyStop->ceiling($article->highMicrousd)];
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

    private function writing(BlogBenchPlan $plan, BlogBenchConfiguration $configuration): GenerationCostForecast
    {
        return $this->forecaster->forecast([...$plan->sharedModels, ...$configuration->models], $plan->words, $this->noImage(), true, self::ARTICLE_STEPS);
    }

    private function image(BlogBenchPlan $plan): GenerationCostForecast
    {
        [$low, $high] = $this->catalog->imagePriceMicrousd($plan->imageModel);

        return new GenerationCostForecast($low, $high);
    }

    private function noImage(): GenerationImageOptions
    {
        return new GenerationImageOptions(BlogAiImageMode::None, 1);
    }
}
