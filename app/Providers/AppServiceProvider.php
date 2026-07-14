<?php

namespace App\Providers;

use App\Models\Submission;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Locale par defaut pour la generation d'URL (route('...')), utilisee hors du middleware
        // setlocale : updates Livewire, CLI, tests. Le middleware la surcharge par requete localisee.
        URL::defaults(['locale' => config('app.locale')]);

        // Lien de reprise du parcours STARTER : {dossier} = resume_token, resolu vers la Submission
        // si le lien est encore valide (scope resumable). Lie par nom (contrairement aux parametres
        // scalaires, lies par position), donc insensible au prefixe {locale}.
        Route::bind('dossier', static function (string $value): Submission {
            return Submission::resumable()->where('resume_token', $value)->firstOrFail();
        });
    }
}
