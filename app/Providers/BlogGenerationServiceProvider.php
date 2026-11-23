<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Blog\Generation\Bench\RunBlogBenchVerificationAction;
use App\Actions\Blog\Generation\Editorial\GenerateBlogDraftImagesAction;
use App\Actions\Blog\Generation\ProcessNextBlogGenerationAction;
use App\Actions\Blog\Generation\RequestBlogTranslationAction;
use App\Actions\Blog\Generation\RunBlogGenerationAction;
use App\Contracts\Blog\Generation\GenerationPipeline;
use App\Contracts\Blog\Generation\HostResolver;
use App\Contracts\Blog\Generation\StockImageProvider;
use App\Data\Blog\Generation\ArticleLengthRules;
use App\Data\Blog\Generation\Bench\BlogBenchPlan;
use App\Data\Blog\Generation\GenerationSafetyStop;
use App\Exceptions\Blog\BlogGenerationException;
use App\Repositories\Blog\BlogGenerationHealthRepository;
use App\Repositories\Blog\OpenRouterKeyRepository;
use App\Services\Admin\Blog\Generation\BlogGenerationOverviewService;
use App\Services\Admin\Blog\Generation\OpenRouterKeyStateService;
use App\Services\Blog\Generation\Bench\BlogBenchFileStorage;
use App\Services\Blog\Generation\Bench\BlogBenchStepRunner;
use App\Services\Blog\Generation\BlogAiCostEstimator;
use App\Services\Blog\Generation\BlogAiModelCatalog;
use App\Services\Blog\Generation\BlogAiPriceTable;
use App\Services\Blog\Generation\BlogAiSettingsService;
use App\Services\Blog\Generation\BlogEffortLevels;
use App\Services\Blog\Generation\BlogGenerationAlertService;
use App\Services\Blog\Generation\BlogGenerationBudget;
use App\Services\Blog\Generation\BlogGenerationCostForecaster;
use App\Services\Blog\Generation\BlogGenerationStepRunner;
use App\Services\Blog\Generation\Editorial\BlogAgentCaller;
use App\Services\Blog\Generation\Editorial\BlogPromptLibrary;
use App\Services\Blog\Generation\Editorial\EditorialGenerationPipeline;
use App\Services\Blog\Generation\Editorial\Images\BlogAiImageGenerator;
use App\Services\Blog\Generation\Editorial\Images\PixabayStockImageProvider;
use App\Services\Blog\Generation\Editorial\Sources\DnsHostResolver;
use App\Services\Blog\Generation\Editorial\Steps\FactSearchStep;
use App\Services\Blog\Generation\Editorial\Steps\ResearchNotesStep;
use App\Services\Blog\Generation\Editorial\Steps\StyleStep;
use App\Services\Blog\Generation\Editorial\Steps\TopicScanStep;
use App\Services\Blog\Generation\Editorial\Steps\TranslationStep;
use App\Services\Blog\Generation\Editorial\Steps\WritingStep;
use App\Services\Blog\Generation\Health\BlogGenerationHealthCheck;
use App\Services\Blog\Generation\Health\OpenRouterCatalogReader;
use App\Services\Blog\Generation\OpenRouterKeyActivator;
use App\Services\Blog\Generation\Simulation\SimulatedGenerationPipeline;
use App\Services\Blog\Generation\UnavailableGenerationPipeline;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laravel\Ai\AiManager;

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
        $this->app->singleton(ArticleLengthRules::class, static fn (): ArticleLengthRules => ArticleLengthRules::fromConfig((array) config('blog.ai.article_length')));
        $this->app->singleton(GenerationSafetyStop::class, static fn (): GenerationSafetyStop => new GenerationSafetyStop(
            (int) config('blog.ai.safety_stop_percent'),
            (int) config('blog.ai.safety_stop_floor_microusd'),
        ));
        $this->app->when(BlogGenerationBudget::class)->needs('$warningPercent')->giveConfig('blog.ai.monthly_warning_percent');
        $this->app->when(BlogGenerationCostForecaster::class)->needs('$profile')->giveConfig('blog.ai.forecast.steps');
        $this->app->when(BlogGenerationCostForecaster::class)->needs('$defaultMarginPercent')->giveConfig('blog.ai.forecast.default_price_margin_percent');

        $this->app->when(BlogAiSettingsService::class)->needs('$defaults')->giveConfig('blog.ai.defaults');
        $this->app->when(BlogAiSettingsService::class)->needs('$fallbackEmail')->giveConfig('festilaw.notification_email');
        $this->app->when(BlogAiModelCatalog::class)->needs('$textModels')->giveConfig('blog.ai.models');
        $this->app->when(BlogAiModelCatalog::class)->needs('$imageModels')->giveConfig('blog.ai.image_models');
        $this->app->when(BlogGenerationOverviewService::class)->needs('$rulesReviewBy')->giveConfig('blog.ai.rules_reference.review_by');
        $this->app->when(OpenRouterKeyStateService::class)->needs('$warningDays')->giveConfig('blog.ai.health.key_expiry_warning_days');

        $this->app->when(BlogGenerationStepRunner::class)->needs('$maxAttempts')->giveConfig('blog.ai.step.max_attempts');
        $this->app->when(BlogGenerationStepRunner::class)->needs('$retryWaitSeconds')->giveConfig('blog.ai.step.retry_wait_seconds');
        $this->app->when(BlogGenerationStepRunner::class)->needs('$transientWaitSeconds')->giveConfig('blog.ai.step.transient_wait_seconds');
        $this->app->when(RunBlogGenerationAction::class)->needs('$lockMarginSeconds')->giveConfig('blog.ai.step.lock_margin_seconds');
        $this->app->when(RunBlogGenerationAction::class)->needs('$autoRelaunchMinutes')->giveConfig('blog.ai.step.auto_relaunch_after_minutes');
        $this->app->when(StyleStep::class)->needs('$maxFlaggedSentences')->giveConfig('blog.ai.style.max_flagged_sentences');
        $this->app->when(TranslationStep::class)->needs('$maxFlaggedSentences')->giveConfig('blog.ai.style.max_flagged_sentences');
        $this->app->when(WritingStep::class)->needs('$maxSources')->giveConfig('blog.ai.displayed_sources.max');
        $this->app->when(WritingStep::class)->needs('$maxJustifiedSources')->giveConfig('blog.ai.displayed_sources.max_justified');
        $this->app->when(WritingStep::class)->needs('$maxCategories')->giveConfig('blog.ai.max_categories');
        $this->app->when(ProcessNextBlogGenerationAction::class)->needs('$claimSeconds')->giveConfig('blog.ai.step.lock_margin_seconds');

        $this->app->when(BlogGenerationAlertService::class)->needs('$failuresThreshold')->giveConfig('blog.ai.consecutive_failures_alert');
        $this->app->when(BlogGenerationAlertService::class)->needs('$techEmail')->giveConfig('festilaw.tech_alert_email');
        $this->app->when(BlogGenerationAlertService::class)->needs('$fallbackEmail')->giveConfig('festilaw.notification_email');

        // Prix du jour releves chaque matin, le tableau de config/blog.php en secours (decision du 23/11) ; lus une
        // fois par requete ou par commande, au premier prix demande.
        $this->app->scoped(BlogAiPriceTable::class, static fn (Application $app): BlogAiPriceTable => new BlogAiPriceTable(
            $app->make(BlogGenerationHealthRepository::class),
            (array) config('blog.ai.models'),
            (int) config('blog.ai.health.prices_valid_days'),
        ));

        $this->app->when(BlogAgentCaller::class)->needs('$provider')->giveConfig('blog.ai.provider');
        // La cle OpenRouter vit dans les reglages, chiffree (decision du 23/11) : posee dans la configuration du SDK
        // une fois par commande ou par requete, avant le premier appel a un modele.
        $this->app->scoped(OpenRouterKeyActivator::class, static fn (Application $app): OpenRouterKeyActivator => new OpenRouterKeyActivator(
            $app->make(OpenRouterKeyRepository::class),
            $app->make(AiManager::class),
            (string) config('blog.ai.provider'),
        ));
        $this->app->when(BlogEffortLevels::class)->needs('$levels')->giveConfig('blog.ai.effort.levels');
        $this->app->when(BlogAgentCaller::class)->needs('$defaultMarginPercent')->giveConfig('blog.ai.forecast.default_price_margin_percent');
        $this->app->when([TopicScanStep::class, ResearchNotesStep::class, FactSearchStep::class])->needs('$webSearch')->giveConfig('blog.ai.web_search');
        $this->app->bind(HostResolver::class, DnsHostResolver::class);
        $this->app->when(RequestBlogTranslationAction::class)->needs('$locales')->giveConfig('festilaw.supported_locales');
        $this->app->bind(StockImageProvider::class, PixabayStockImageProvider::class);
        $this->app->when(PixabayStockImageProvider::class)->needs('$apiKey')->giveConfig('services.pixabay.key');
        $this->app->when(BlogAiImageGenerator::class)->needs('$provider')->giveConfig('blog.ai.provider');
        $this->app->when(GenerateBlogDraftImagesAction::class)->needs('$featuredMinWidth')->giveConfig('blog.images.min_featured.width');
        $this->app->when(GenerateBlogDraftImagesAction::class)->needs('$featuredMinHeight')->giveConfig('blog.images.min_featured.height');
        $this->app->when(GenerateBlogDraftImagesAction::class)->needs('$photoChecks')->giveConfig('blog.ai.stock_photo_checks');
        $this->app->when(BlogPromptLibrary::class)->needs('$directory')->giveConfig('blog.ai.prompts_directory');
        $this->app->when(BlogAiCostEstimator::class)->needs('$webSearchMicrousd')->giveConfig('blog.ai.web_search.price_microusd');

        // Verification quotidienne des services (controle du 20/11) : les adresses gratuites d'OpenRouter, a la
        // meme adresse de base que le SDK.
        $this->app->when(OpenRouterCatalogReader::class)->needs('$apiKey')
            ->give(static fn (Application $app): ?string => $app->make(OpenRouterKeyRepository::class)->current()?->secret);
        $this->app->when(OpenRouterCatalogReader::class)->needs('$baseUrl')
            ->give(static fn (): string => (string) (config('ai.providers.openrouter.url') ?: 'https://openrouter.ai/api/v1'));
        $this->app->when(BlogGenerationHealthCheck::class)->needs('$openRouterKey')
            ->give(static fn (Application $app): ?string => $app->make(OpenRouterKeyRepository::class)->current()?->secret);
        $this->app->when(BlogGenerationHealthCheck::class)->needs('$pipeline')->giveConfig('blog.ai.pipeline');
        $this->app->when(BlogGenerationHealthCheck::class)->needs('$textCatalog')->giveConfig('blog.ai.models');
        $this->app->when(BlogGenerationHealthCheck::class)->needs('$imageCatalog')->giveConfig('blog.ai.image_models');
        $this->app->when(BlogGenerationHealthCheck::class)->needs('$thresholds')->giveConfig('blog.ai.health');
        $this->app->when(BlogGenerationHealthCheck::class)->needs('$rulesReference')->giveConfig('blog.ai.rules_reference');

        $this->app->bind(BlogBenchPlan::class, static fn (): BlogBenchPlan => BlogBenchPlan::fromConfig((array) config('blog.ai.bench')));
        $this->app->when(BlogBenchStepRunner::class)->needs('$maxAttempts')->giveConfig('blog.ai.step.max_attempts');
        $this->app->when(BlogBenchStepRunner::class)->needs('$retryWaitSeconds')->giveConfig('blog.ai.step.retry_wait_seconds');
        $this->app->when(BlogBenchFileStorage::class)->needs('$directory')->giveConfig('blog.ai.bench.directory');
        $this->app->when(RunBlogBenchVerificationAction::class)->needs('$capCents')->giveConfig('blog.ai.bench.verification_cap_cents');
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
