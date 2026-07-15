<?php

use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Web\About\AboutController;
use App\Http\Controllers\Web\Contact\ContactController;
use App\Http\Controllers\Web\ExcludedProducts\ExcludedProductsController;
use App\Http\Controllers\Web\Funnel\GetStartedController;
use App\Http\Controllers\Web\Funnel\ProController;
use App\Http\Controllers\Web\Funnel\ScaleController;
use App\Http\Controllers\Web\Funnel\StarterController;
use App\Http\Controllers\Web\Funnel\StarterDevPayController;
use App\Http\Controllers\Web\Funnel\StarterDevSignController;
use App\Http\Controllers\Web\Funnel\StarterDocumentDownloadController;
use App\Http\Controllers\Web\Funnel\StarterDossierController;
use App\Http\Controllers\Web\Funnel\StarterJourneyController;
use App\Http\Controllers\Web\Funnel\StarterMandateDownloadController;
use App\Http\Controllers\Web\Home\HomeController;
use App\Http\Controllers\Web\Pricing\PricingController;
use App\Http\Controllers\Web\Services\ServicesController;
use App\Http\Controllers\Web\UnderstandGpsr\UnderstandGpsrController;
use App\Http\Controllers\Web\Webhook\PaymentWebhookController;
use App\Http\Controllers\Web\Webhook\SignatureWebhookController;
use Illuminate\Support\Facades\Route;

/*
 | La racine redirige vers la meilleure locale (negociation navigateur, repli sur la 1re supportee).
 */
Route::get('/', function () {
    return redirect('/'.request()->getPreferredLanguage(config('festilaw.supported_locales')));
});

/*
 | Fichiers SEO non localises. Declares AVANT le groupe {locale} pour ne pas etre captes par le prefixe.
 */
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', function () {
    $body = "User-agent: *\nDisallow:\n\nSitemap: ".url('/sitemap.xml')."\n";

    return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

/*
 | Webhooks providers (Stripe/Zoho) : POST externes, hors CSRF (voir bootstrap/app.php), traites en synchrone.
 */
Route::post('/webhooks/payment/{provider}', PaymentWebhookController::class)->name('webhooks.payment');
Route::post('/webhooks/signature', SignatureWebhookController::class)->name('webhooks.signature');

/*
 | Espace public, prefixe par la locale (ADR-003). Les futures pages localisees vont dans ce groupe.
 | Les routes NON localisees (webhooks, back-office, etc.) restent hors de ce groupe.
 */
Route::prefix('{locale}')->middleware('setlocale')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/about', AboutController::class)->name('about');
    Route::get('/understand-gpsr', UnderstandGpsrController::class)->name('understand-gpsr');
    Route::get('/services', ServicesController::class)->name('services');
    Route::get('/pricing', PricingController::class)->name('pricing');
    Route::get('/excluded-products', ExcludedProductsController::class)->name('excluded-products');
    Route::get('/contact', ContactController::class)->name('contact');

    /*
     | Tunnel de souscription (noindex). Chaque page rend un composant Livewire du parcours.
     */
    Route::prefix('get-started')->name('get-started.')->group(function () {
        Route::get('/', GetStartedController::class)->name('index');
        Route::get('/pro', ProController::class)->name('pro');
        Route::get('/scale', ScaleController::class)->name('scale');

        // Parcours STARTER : page d'ouverture, puis dossier resumable via son token ({dossier}).
        Route::get('/starter', StarterController::class)->name('starter');
        Route::get('/starter/{dossier}', StarterJourneyController::class)->name('starter.journey');

        // Espace "mon dossier" : telechargement du mandat signe et des documents (portes par le token).
        Route::get('/starter/{dossier}/mandate', StarterMandateDownloadController::class)->name('starter.mandate');
        Route::get('/starter/{dossier}/document/{document}', StarterDocumentDownloadController::class)->name('starter.document');

        // Completion des providers Fake (dev/local uniquement, bloquee en production) : ces routes
        // rejouent ce que ferait le webhook du vrai provider, puis renvoient au dossier.
        Route::get('/starter/{dossier}/dev/sign', StarterDevSignController::class)->name('starter.dev-sign');
        Route::get('/starter/{dossier}/dev/pay', StarterDevPayController::class)->name('starter.dev-pay');
    });

    // Espace client "mon dossier" (dossier actif/paye), separe du parcours. Acces par magic link.
    // /my-file : saisie de l'email -> envoi du lien. /my-file/{dossier} : le dossier lui-meme.
    Route::view('/my-file', 'web.find-my-file')->name('find-my-file');
    Route::get('/my-file/{dossier}', StarterDossierController::class)->name('my-file');
});
