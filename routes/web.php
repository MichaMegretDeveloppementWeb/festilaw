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
use App\Http\Controllers\Web\Funnel\StarterJourneyController;
use App\Http\Controllers\Web\Funnel\StarterMandateDownloadController;
use App\Http\Controllers\Web\Funnel\StarterProjectController;
use App\Http\Controllers\Web\Home\HomeController;
use App\Http\Controllers\Web\Pricing\PricingController;
use App\Http\Controllers\Web\Services\ServicesController;
use App\Http\Controllers\Web\SwitchLocaleController;
use App\Http\Controllers\Web\UnderstandGpsr\UnderstandGpsrController;
use App\Http\Controllers\Web\Webhook\PaymentWebhookController;
use App\Http\Controllers\Web\Webhook\SignatureWebhookController;
use Illuminate\Support\Facades\Route;

/*
 | Fichiers SEO. Le site a un SEUL jeu d'URLs (langue canonique : anglais). La traduction FR/ES est
 | purement visuelle (locale en session, cf. SetLocale), sans prefixe d'URL ni hreflang.
 */
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/robots.txt', function () {
    $body = "User-agent: *\nDisallow:\n\nSitemap: ".url('/sitemap.xml')."\n";

    return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
})->name('robots');

/*
 | Webhooks providers (Stripe/SignWell) : POST externes, hors CSRF (voir bootstrap/app.php), synchrones.
 */
Route::post('/webhooks/payment/{provider}', PaymentWebhookController::class)->name('webhooks.payment');
Route::post('/webhooks/signature', SignatureWebhookController::class)->name('webhooks.signature');

/*
 | Bascule de langue (traduction visuelle) : memorise la locale en session, recharge la page courante.
 */
Route::get('/language/{locale}', SwitchLocaleController::class)->name('locale.switch');

/*
 | Espace public. La locale est appliquee par le middleware `setlocale` (groupe web) depuis la session.
 */
Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');
Route::get('/understand-gpsr', UnderstandGpsrController::class)->name('understand-gpsr');
Route::get('/services', ServicesController::class)->name('services');
Route::get('/pricing', PricingController::class)->name('pricing');
Route::get('/excluded-products', ExcludedProductsController::class)->name('excluded-products');
Route::get('/contact', ContactController::class)->name('contact');

/*
 | Pages legales (le contenu definitif est fourni et valide par Festilaw). Indexables, faible priorite.
 */
Route::view('/legal-notice', 'web.legal.legal-notice')->name('legal-notice');
Route::view('/privacy-policy', 'web.legal.privacy-policy')->name('privacy-policy');
Route::view('/terms', 'web.legal.terms')->name('terms');

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

// Espace client "mon projet" (le hub du dossier a tout stade), separe du parcours. Magic link.
Route::view('/my-project', 'web.find-my-project')->name('find-my-project');
Route::get('/my-project/{dossier}', StarterProjectController::class)->name('my-project');
