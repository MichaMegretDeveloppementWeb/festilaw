<?php

namespace App\Providers;

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
    }
}
