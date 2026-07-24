<?php

declare(strict_types=1);

namespace App\Providers;

use App\Enums\Submission\SubmissionType;
use App\Models\Submission;
use App\Services\Billing\AnnualFeeProrator;
use App\Services\Billing\PackPricingService;
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
        // si le lien est encore valide (scope resumable).
        Route::bind('dossier', static function (string $value): Submission {
            return Submission::resumable()->where('resume_token', $value)->firstOrFail();
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
    }
}
