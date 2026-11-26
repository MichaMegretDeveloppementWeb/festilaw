<?php

declare(strict_types=1);

namespace App\Providers;

use App\Actions\Blog\Category\SaveBlogCategoryAction;
use App\Actions\Blog\Post\SaveBlogPostAction;
use App\Actions\System\SendDailyErrorReportAction;
use App\Contracts\Analytics\AudienceTracker;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Web\DossierLinkInvalidException;
use App\Models\Submission;
use App\Repositories\System\ExpiredCacheRepository;
use App\Services\Admin\AdminNavigation;
use App\Services\Admin\Blog\BlogCategoryAdminService;
use App\Services\Admin\Blog\BlogPostFormService;
use App\Services\Admin\Blog\BlogPostPreviewService;
use App\Services\Analytics\AudienceMeasurementChoice;
use App\Services\Analytics\FalconAudienceTracker;
use App\Services\Billing\AnnualFeeProrator;
use App\Services\Billing\PackPricingService;
use App\Services\Blog\Content\BlogContentNormalizer;
use App\Services\Blog\Content\BlogContentRenderer;
use App\Services\Blog\Content\BlogLinkPolicy;
use App\Services\Blog\Content\BlogMarkdownConverter;
use App\Services\Blog\Image\BlogImageInspector;
use App\Services\Blog\Image\BlogImagePaths;
use App\Services\Blog\Image\BlogImageProcessor;
use App\Services\Blog\Image\BlogImageStorage;
use App\Services\Blog\Image\BlogImageTextCleaner;
use App\Services\Blog\Post\BlogAuthorProvider;
use App\Services\Blog\Post\BlogPostReadinessCalculator;
use App\Services\Blog\Slug\BlogPostSlugAssigner;
use App\Services\Blog\Slug\BlogSlugGenerator;
use App\Services\System\ErrorLogReader;
use App\Services\System\SchedulerHealthService;
use App\Services\Web\Blog\BlogArticlePageAssembler;
use App\Services\Web\Blog\BlogCategoryPageService;
use App\Services\Web\Blog\BlogHomeBlockService;
use App\Services\Web\Blog\BlogHubPageWindow;
use App\Services\Web\Blog\BlogNavigationService;
use App\Services\Web\Seo\StructuredDataGraphBuilder;
use Falcon\Analytics\Facades\Analytics;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Singleton : memoise les surcharges de prix pour la requete (cf. SubmissionType::annualCents()).
        $this->app->singleton(PackPricingService::class);

        // Le prorata de l'annee 1 recoit le plancher d'encaissement (config) pour ne jamais tomber sous le
        // minimum du prestataire (Stripe ~0,50 €) sur un tarif de test tres bas.
        $this->app->bind(AnnualFeeProrator::class, static fn (): AnnualFeeProrator => new AnnualFeeProrator(
            (int) config('festilaw.payment.min_charge_cents', 50),
        ));

        // Le suivi d'audience (Falcon Analytics), derriere son contrat comme les autres prestataires.
        $this->app->bind(AudienceTracker::class, FalconAudienceTracker::class);

        // Le menu ne propose l'ecran des integrations de Falcon Analytics qu'avec un client Search Console.
        $this->app->when(AdminNavigation::class)->needs('$searchConsoleConfigured')
            ->give(static fn (): bool => trim((string) config('analytics.search_console.client_id')) !== '');

        $this->registerErrorReportSettings();
        $this->registerBlogSettings();
        $this->registerStructuredDataSettings();
    }

    /**
     * Donnees structurees des pages publiques : le nom du site, l'adresse de contact publiee et les
     * langues du site.
     */
    private function registerStructuredDataSettings(): void
    {
        $this->app->when(StructuredDataGraphBuilder::class)->needs('$siteName')->giveConfig('app.name');
        $this->app->when(StructuredDataGraphBuilder::class)->needs('$contactEmail')->giveConfig('festilaw.contact_email');
        $this->app->when(StructuredDataGraphBuilder::class)->needs('$locales')->giveConfig('festilaw.supported_locales');
    }

    /**
     * Rapport d'erreurs du matin : le dossier du journal, l'environnement dont on lit les erreurs, et le
     * destinataire technique, sans repli vers l'adresse de la cliente.
     */
    private function registerErrorReportSettings(): void
    {
        $this->app->when(ErrorLogReader::class)->needs('$directory')->give(static fn (): string => storage_path('logs'));
        $this->app->when(ErrorLogReader::class)->needs('$environment')->giveConfig('app.env');
        $this->app->when(ErrorLogReader::class)->needs('$basePath')->give(static fn (): string => base_path());
        $this->app->when(SendDailyErrorReportAction::class)->needs('$recipient')->giveConfig('festilaw.tech_alert_email');
    }

    /**
     * Blog : les services recoivent leurs reglages (config/blog.php) par leur constructeur, pour rester
     * des classes pures, testables sans conteneur ni configuration.
     */
    private function registerBlogSettings(): void
    {
        $this->app->when(BlogSlugGenerator::class)->needs('$maxLength')->giveConfig('blog.slug.max_length');
        $this->app->when(BlogPostSlugAssigner::class)->needs('$reservedWords')->giveConfig('blog.slug.reserved');

        $this->app->when(BlogLinkPolicy::class)->needs('$siteHost')
            ->give(static fn (): string => (string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $this->app->when(BlogContentNormalizer::class)->needs('$maxBytes')->giveConfig('blog.content.max_bytes');
        $this->app->when(BlogContentNormalizer::class)->needs('$maxNodes')->giveConfig('blog.content.max_nodes');
        $this->app->when(BlogContentNormalizer::class)->needs('$maxDepth')->giveConfig('blog.content.max_depth');
        $this->app->when(BlogMarkdownConverter::class)->needs('$maxBytes')->giveConfig('blog.content.max_bytes');
        $this->app->when(BlogContentRenderer::class)->needs('$imageSizes')->giveConfig('blog.content.image_sizes');
        $this->app->when(BlogContentRenderer::class)->needs('$reducedWidths')->giveConfig('blog.content.image_widths');
        $this->app->when(BlogImagePaths::class)->needs('$directory')->giveConfig('blog.images.directory');

        $this->app->when(BlogImageStorage::class)->needs('$disk')->giveConfig('blog.images.disk');
        $this->app->when(BlogImageStorage::class)->needs('$sourceDisk')->giveConfig('blog.images.source_disk');
        $this->app->when(ExpiredCacheRepository::class)->needs('$connection')->giveConfig('cache.stores.database.connection');
        $this->app->when(ExpiredCacheRepository::class)->needs('$table')->give(static fn (): string => (string) (config('cache.stores.database.table') ?: 'cache'));
        $this->app->when(ExpiredCacheRepository::class)->needs('$lockTable')->give(static fn (): string => (string) (config('cache.stores.database.lock_table') ?: 'cache_locks'));
        $this->app->when(BlogImageTextCleaner::class)->needs('$locales')->giveConfig('festilaw.supported_locales');
        $this->app->when(BlogImageInspector::class)->needs('$maxKilobytes')->giveConfig('blog.images.max_upload_kilobytes');
        $this->app->when(BlogImageInspector::class)->needs('$maxPixels')->giveConfig('blog.images.max_pixels');
        $this->app->when(BlogImageInspector::class)->needs('$minFeaturedWidth')->giveConfig('blog.images.min_featured.width');
        $this->app->when(BlogImageInspector::class)->needs('$minFeaturedHeight')->giveConfig('blog.images.min_featured.height');
        $this->app->when(BlogImageProcessor::class)->needs('$widths')->giveConfig('blog.images.widths');
        $this->app->when(BlogImageProcessor::class)->needs('$webpQuality')->giveConfig('blog.images.webp_quality');
        $this->app->when(BlogImageProcessor::class)->needs('$shareWidth')->giveConfig('blog.images.share.width');
        $this->app->when(BlogImageProcessor::class)->needs('$shareHeight')->giveConfig('blog.images.share.height');
        $this->app->when(BlogImageProcessor::class)->needs('$shareQuality')->giveConfig('blog.images.share.jpeg_quality');
        $this->app->when(BlogImageProcessor::class)->needs('$sourceWidth')->giveConfig('blog.images.source_width');
        $this->app->when(BlogImageProcessor::class)->needs('$minCropWidth')->giveConfig('blog.images.min_crop.width');
        $this->app->when(BlogImageProcessor::class)->needs('$minCropHeight')->giveConfig('blog.images.min_crop.height');

        $this->app->when([
            BlogPostReadinessCalculator::class,
            SaveBlogPostAction::class,
            SaveBlogCategoryAction::class,
        ])->needs('$locales')->giveConfig('festilaw.supported_locales');
        $this->app->when(BlogAuthorProvider::class)->needs('$author')->giveConfig('blog.author');
        $this->app->when(BlogAuthorProvider::class)->needs('$defaultLocale')->giveConfig('festilaw.default_locale');

        $this->app->when(BlogHubPageWindow::class)->needs('$perPage')->giveConfig('blog.per_page');
        $this->app->when(BlogCategoryPageService::class)->needs('$perPage')->giveConfig('blog.per_page');
        $this->app->when(BlogArticlePageAssembler::class)->needs('$relatedLimit')->giveConfig('blog.related');
        $this->app->when([BlogPostFormService::class, BlogCategoryAdminService::class, BlogPostPreviewService::class])
            ->needs('$locales')->giveConfig('festilaw.supported_locales');
        $this->app->when(BlogHomeBlockService::class)->needs('$limit')->giveConfig('blog.home_latest');
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Lien de reprise du parcours STARTER : {dossier} = resume_token, resolu vers la Submission
        // si le lien est encore valide (scope resumable). Sinon (lien remplace par un plus recent, expire
        // ou inconnu) : page "lien expire" qui propose d'en recevoir un nouveau, plutot qu'une 404 brute.
        Route::bind('dossier', static function (string $value): Submission {
            return Submission::resumable()->where('resume_token', $value)->firstOr(
                static fn () => throw new DossierLinkInvalidException,
            );
        });

        // Prix effectifs des packs (editables au back-office) exposes aux pages publiques qui les
        // affichent, pour qu'un changement de tarif se reflete partout sans HTML en dur.
        View::composer([
            'web.sections.pricing',
            'web.get-started.index',
            'web.get-started.starter',
            'web.get-started.pro',
            'web.get-started.journey',
            'web.pricing.index',
        ], static function ($view): void {
            $pricing = app(PackPricingService::class);
            $view->with([
                'creatorAnnualCents' => $pricing->annualCents(SubmissionType::Starter),
                'proAnnualCents' => $pricing->annualCents(SubmissionType::Pro),
            ]);
        });

        // Lien "Blog" de l'en-tete et du pied de page : affiche a partir du premier article publie. La
        // condition vient d'ici (une requete par page, memorisee), jamais d'une requete dans le composant.
        View::composer(['components.layout.web.header', 'components.layout.web.footer'], static function ($view): void {
            $view->with('showBlogLink', app(BlogNavigationService::class)->hasPublishedPosts());
        });

        // Back-office : etat des taches automatiques (cron), en prod seulement (pas de cron en local).
        // Bandeau si elles sont a l'arret, ligne discrete "dernier passage" dans la barre laterale.
        View::composer('layouts.admin', static function ($view): void {
            // Menu principal, decrit une fois et rendu deux fois (mobile, barre laterale). Une prise de
            // contact s'ouvre par la route des dossiers : c'est son type qui dit quel lien est actif.
            $submission = request()->route('submission');
            $view->with('adminNav', app(AdminNavigation::class)->entries(
                request()->route()?->getName(),
                $submission instanceof Submission && $submission->type === SubmissionType::Contact,
            ));

            if (! app()->isProduction()) {
                $view->with('schedulerStatus', null);

                return;
            }

            $health = app(SchedulerHealthService::class);
            $lastRunAt = $health->lastRunAt();
            $view->with('schedulerStatus', [
                'lastRunAt' => $lastRunAt,
                'stalled' => $health->isStalled(),
            ]);
        });

        $this->registerAudienceMeasurement();
    }

    /**
     * Mesure d'audience (decision de Micha du 24/11/2026) : un navigateur est reconnu d'une visite a l'autre
     * sans bandeau, par le cookie de Falcon Analytics (treize mois, jamais prolonge), et tout visiteur peut
     * refuser d'etre compte depuis la politique de confidentialite. Un refus exclut le navigateur comme un
     * compte du back-office connecte : rien n'est mesure, ni par le collecteur ni par le serveur. La regle
     * d'exclusion du paquet (`identity.exclude_guards`) est reprise ici, car une regle ecrite la remplace.
     */
    private function registerAudienceMeasurement(): void
    {
        Analytics::consentUsing(static fn (): bool => true);

        Analytics::excludeUsing(static function (): bool {
            foreach ((array) config('analytics.identity.exclude_guards', []) as $guard) {
                if (auth()->guard($guard)->check()) {
                    return true;
                }
            }

            return app(AudienceMeasurementChoice::class)->isRefused(request());
        });
    }
}
