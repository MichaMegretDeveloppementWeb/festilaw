<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Submission;
use App\Services\Billing\PackPricingService;
use Illuminate\Support\Facades\Route;
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
    }
}
