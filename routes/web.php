<?php

use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Web\About\AboutController;
use App\Http\Controllers\Web\Contact\ContactController;
use App\Http\Controllers\Web\ExcludedProducts\ExcludedProductsController;
use App\Http\Controllers\Web\Home\HomeController;
use App\Http\Controllers\Web\Pricing\PricingController;
use App\Http\Controllers\Web\Services\ServicesController;
use App\Http\Controllers\Web\UnderstandGpsr\UnderstandGpsrController;
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
});
