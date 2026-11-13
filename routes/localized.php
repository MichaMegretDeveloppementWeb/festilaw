<?php

use App\Http\Controllers\Web\About\AboutController;
use App\Http\Controllers\Web\Blog\BlogCategoryController;
use App\Http\Controllers\Web\Blog\BlogIndexController;
use App\Http\Controllers\Web\Blog\BlogPostController;
use App\Http\Controllers\Web\Contact\ContactController;
use App\Http\Controllers\Web\ExcludedProducts\ExcludedProductsController;
use App\Http\Controllers\Web\Home\HomeController;
use App\Http\Controllers\Web\Pricing\PricingController;
use App\Http\Controllers\Web\Services\ServicesController;
use App\Http\Controllers\Web\UnderstandGpsr\UnderstandGpsrController;
use Illuminate\Support\Facades\Route;

/*
 | Pages publiques indexables : UNE ADRESSE PAR LANGUE. Ce fichier est charge une fois par langue depuis
 | routes/web.php : la langue par defaut (anglais) sans prefixe, les autres sous /fr et /es. Chaque
 | route porte la metadonnee `locale` et le nom de la langue par defaut, prefixe par la langue pour les
 | autres (pricing, fr.pricing, es.pricing). La langue de la page vient de son adresse (cf. SetLocale).
 |
 | Tout lien vers une de ces pages passe par localized_route() (LocalizedUrlGenerator), jamais par
 | route(), pour que le visiteur reste dans sa langue.
 */
Route::get('/', HomeController::class)->name('home');
Route::get('/about', AboutController::class)->name('about');
Route::get('/understand-gpsr', UnderstandGpsrController::class)->name('understand-gpsr');
Route::get('/services', ServicesController::class)->name('services');
Route::get('/pricing', PricingController::class)->name('pricing');
Route::get('/excluded-products', ExcludedProductsController::class)->name('excluded-products');
Route::get('/contact', ContactController::class)->name('contact');

/*
 | Blog. Le slug d'un article ou d'une categorie est propre a chaque langue, et le segment de categorie
 | est traduit (category, categorie, categoria) : `$locale` est fourni par routes/web.php. La page
 | declare elle-meme ses equivalents dans les autres langues (LocalizedUrlGenerator::declareAlternates) ;
 | `locale_fallback` dit vers quelle page renvoyer quand une adresse ne peut pas etre transposee telle
 | quelle dans une autre langue.
 */
Route::get('/blog', BlogIndexController::class)->name('blog.index');
Route::get('/blog/'.config('blog.routes.category_segment')[$locale].'/{slug}', BlogCategoryController::class)
    ->where('slug', '[a-z0-9-]+')
    ->name('blog.category')
    ->metadata(['locale_fallback' => 'blog.index']);
Route::get('/blog/{slug}', BlogPostController::class)
    ->where('slug', '[a-z0-9-]+')
    ->name('blog.show')
    ->metadata(['locale_fallback' => 'blog.index']);

/*
 | Pages legales (le contenu definitif est fourni et valide par Festilaw). Indexables, faible priorite.
 */
Route::view('/legal-notice', 'web.legal.legal-notice')->name('legal-notice');
Route::view('/privacy-policy', 'web.legal.privacy-policy')->name('privacy-policy');
Route::view('/terms', 'web.legal.terms')->name('terms');
