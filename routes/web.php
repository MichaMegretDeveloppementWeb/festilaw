<?php

use App\Http\Controllers\Web\Contact\ContactController;
use App\Http\Controllers\Web\Home\HomeController;
use App\Http\Controllers\Web\UnderstandGpsr\UnderstandGpsrController;
use Illuminate\Support\Facades\Route;

/*
 | La racine redirige vers la meilleure locale (negociation navigateur, repli sur la 1re supportee).
 */
Route::get('/', function () {
    return redirect('/'.request()->getPreferredLanguage(config('festilaw.supported_locales')));
});

/*
 | Espace public, prefixe par la locale (ADR-003). Les futures pages localisees vont dans ce groupe.
 | Les routes NON localisees (webhooks, back-office, etc.) restent hors de ce groupe.
 */
Route::prefix('{locale}')->middleware('setlocale')->group(function () {
    Route::get('/', HomeController::class)->name('home');
    Route::get('/understand-gpsr', UnderstandGpsrController::class)->name('understand-gpsr');
    Route::get('/contact', ContactController::class)->name('contact');
});
