<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Blog\Generation\Editorial\GenerateBlogDraftImagesAction;
use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\RequestBlogTranslationAction;
use App\Actions\Blog\Generation\RunBlogGenerationAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Contracts\Blog\Generation\HostResolver;
use App\Contracts\Blog\Generation\StockImageProvider;
use App\Exceptions\Blog\BlogGenerationException;
use App\Services\Admin\Blog\Generation\BlogGenerationOverviewService;
use App\Services\Blog\Generation\BlogAiCostEstimator;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogGenerationAlertService;
use App\Services\Blog\Generation\BlogGenerationStepRunner;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\EditorialGenerationPipeline;
use App\Services\Blog\Generation\Editorial\Images\BlogAiImageGenerator;
use App\Services\Blog\Generation\Editorial\Images\PixabayStockImageProvider;
use App\Services\Blog\Generation\Editorial\Sources\DnsHostResolver;
use App\Services\Blog\Generation\Simulation\SimulatedGenerationPipeline;
use App\Services\Blog\Generation\UnavailableGenerationPipeline;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Generation d'articles par IA : choix du pipeline (liste des etapes) d'apres la configuration, et reglages
 * passes aux services par leur constructeur (config/blog.php, section ai), pour qu'ils restent des classes
 * pures, testables sans conteneur.
 */
final class BlogGenerationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GenerationPipeline::class, fn (Application $app): GenerationPipeline => $this->pipeline($app));

        $this->app->when(BlogAiSettingsService::class)->needs('$defaults')->giveConfig('blog.ai.defaults');
        $this->app->when(BlogAiSettingsService::class)->needs('$fallbackEmail')->giveConfig('festilaw.notification_email');
        $this->app->when(BlogAiSettingsService::class)->needs('$locales')->giveConfig('festilaw.supported_locales');
        $this->app->when(BlogAiModelCatalog::class)->needs('$textModels')->giveConfig('blog.ai.models');
        $this->app->when(BlogAiModelCatalog::class)->needs('$imageModels')->giveConfig('blog.ai.image_models');
        // La cle n'est lue que pour dire a l'ecran si elle est presente ; elle n'est jamais affichee.
        $this->app->when(BlogGenerationOverviewService::class)->needs('$apiKey')->giveConfig('ai.providers.openrouter.key');

        $this->app->when(BlogGenerationStepRunner::class)->needs('$maxAttempts')->giveConfig('blog.ai.step.max_attempts');
        $this->app->when(BlogGenerationStepRunner::class)->needs('$retryWaitSeconds')->giveConfig('blog.ai.step.retry_wait_seconds');
        $this->app->when(RunBlogGenerationAction::class)->needs('$lockMarginSeconds')->giveConfig('blog.ai.step.lock_margin_seconds');
        $this->app->when(ProcessNextBlogGenerationAction::class)->needs('$claimSeconds')->giveConfig('blog.ai.step.lock_margin_seconds');

        $this->app->when(BlogGenerationAlertService::class)->needs('$failuresThreshold')->giveConfig('blog.ai.consecutive_failures_alert');
        $this->app->when(BlogGenerationAlertService::class)->needs('$techEmail')->giveConfig('festilaw.tech_alert_email');
        $this->app->when(BlogGenerationAlertService::class)->needs('$fallbackEmail')->giveConfig('festilaw.notification_email');

        $this->app->when(BlogAiCostEstimator::class)->needs('$prices')->giveConfig('blog.ai.models');

        $this->app->when(BlogAgentCaller::class)->needs('$provider')->giveConfig('blog.ai.provider');
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
        $this->app->when(RequestBlogTranslationAction::class)->needs('$locales')->giveConfig('festilaw.supported_locales');
        $this->app->bind(StockImageProvider::class, PixabayStockImageProvider::class);
        $this->app->when(PixabayStockImageProvider::class)->needs('$apiKey')->giveConfig('services.pixabay.key');
        $this->app->when(BlogAiImageGenerator::class)->needs('$provider')->giveConfig('blog.ai.provider');
        $this->app->when(GenerateBlogDraftImagesAction::class)->needs('$featuredMinWidth')->giveConfig('blog.images.min_featured.width');
        $this->app->when(GenerateBlogDraftImagesAction::class)->needs('$featuredMinHeight')->giveConfig('blog.images.min_featured.height');
        $this->app->when(BlogPromptLibrary::class)->needs('$directory')->giveConfig('blog.ai.prompts_directory');
        $this->app->when(BlogAiCostEstimator::class)->needs('$webSearchMicrousd')->giveConfig('blog.ai.web_search_microusd');
    }

    /**
     * Le pipeline editorial par defaut. Les etapes factices ne tournent jamais en production : un brouillon
     * « genere » sans aucun modele n'a rien a y faire.
     */
    private function pipeline(Application $app): GenerationPipeline
    {
        $name = (string) config('blog.ai.pipeline');

        if ($name === 'editorial') {
            return $app->make(EditorialGenerationPipeline::class);
        }

        if ($name !== 'simulated') {
            return new UnavailableGenerationPipeline(BlogGenerationException::unknownPipeline($name));
        }

        if ($app->isProduction()) {
            return new UnavailableGenerationPipeline(BlogGenerationException::simulatedPipelineInProduction());
        }

        return new SimulatedGenerationPipeline(
            secondsPerStep: (int) config('blog.ai.simulation.seconds_per_step'),
            costMicrousdPerStep: (int) config('blog.ai.simulation.cost_microusd_per_step'),
            failStep: config('blog.ai.simulation.fail_step'),
            stopWithoutTopic: (bool) config('blog.ai.simulation.stop_without_topic'),
        );
    }
}
