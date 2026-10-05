<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Submission\SubmissionType;
use App\Exceptions\Web\DossierLinkInvalidException;
use App\Models\Submission;
use App\Services\Billing\AnnualFeeProrator;
use App\Services\Billing\PackPricingService;
use App\Services\System\SchedulerHealthService;
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

        // Back-office : etat des taches automatiques (cron), en prod seulement (pas de cron en local).
        // Bandeau si elles sont a l'arret, ligne discrete "dernier passage" dans la barre laterale.
        View::composer('layouts.admin', static function ($view): void {
            if (! app()->isProduction()) {
                $view->with('schedulerStatus', null);

                return;
            }

            $health = app(SchedulerHealthService::class);
            $lastRunAt = $health->lastRunAt();
            $view->with('schedulerStatus', [
                'lastRunAt' => $lastRunAt,
                'minutesAgo' => $lastRunAt === null ? null : max(0, (int) floor($lastRunAt->diffInMinutes(now()))),
                'stalled' => $health->isStalled(),
            ]);
        });
    }
}
