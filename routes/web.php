<?php

use App\Http\Controllers\Admin\AdminContractMandateDownloadController;
use App\Http\Controllers\Admin\AdminCountersignedDownloadController;
use App\Http\Controllers\Admin\AdminDocumentDownloadController;
use App\Http\Controllers\Admin\AdminMandateDownloadController;
use App\Http\Controllers\Admin\Blog\BlogPostPreviewController;
use App\Http\Controllers\Admin\LogoutController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\Web\Funnel\GetStartedController;
use App\Http\Controllers\Web\Funnel\PaymentReturnController;
use App\Http\Controllers\Web\Funnel\ProController;
use App\Http\Controllers\Web\Funnel\ScaleAuditPaymentController;
use App\Http\Controllers\Web\Funnel\ScaleBookingController;
use App\Http\Controllers\Web\Funnel\ScaleController;
use App\Http\Controllers\Web\Funnel\ScaleSpaceController;
use App\Http\Controllers\Web\Funnel\StarterController;
use App\Http\Controllers\Web\Funnel\StarterCountersignedDownloadController;
use App\Http\Controllers\Web\Funnel\StarterDocumentDownloadController;
use App\Http\Controllers\Web\Funnel\StarterJourneyController;
use App\Http\Controllers\Web\Funnel\StarterMandateDownloadController;
use App\Http\Controllers\Web\Funnel\StarterProjectController;
use App\Http\Controllers\Web\Funnel\StarterRenewalController;
use App\Http\Controllers\Web\Quiz\StoreQuizResultController;
use App\Http\Controllers\Web\SwitchLocaleController;
use App\Http\Controllers\Web\Webhook\PaymentWebhookController;
use App\Http\Controllers\Web\Webhook\SignatureWebhookController;
use App\Livewire\Admin\AdminProfile;
use App\Livewire\Admin\Blog\CategoryForm as BlogCategoryForm;
use App\Livewire\Admin\Blog\CategoryList as BlogCategoryList;
use App\Livewire\Admin\Blog\PostForm as BlogPostForm;
use App\Livewire\Admin\Blog\PostList as BlogPostList;
use App\Livewire\Admin\LoginForm;
use App\Livewire\Admin\PricingSettings;
use App\Livewire\Admin\QuizResultList;
use App\Livewire\Admin\SubmissionDetail;
use App\Livewire\Admin\SubmissionList;
use Illuminate\Support\Facades\Route;

/*
 | Fichiers SEO. Le sitemap liste les pages publiques dans chaque langue, avec leurs equivalents (hreflang).
 | robots.txt est un fichier statique (public/robots.txt) servi directement par le serveur web,
 | sans PHP ni session : sa reponse ne peut pas varier selon le robot.
 */
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

// Persistance (anonyme) d'une reponse au quiz d'eligibilite, appelee en fin de quiz. Sous /api/* pour
// que les erreurs de validation sortent en JSON (cf. shouldRenderJsonWhen dans bootstrap/app.php).
// Limitee contre l'abus.
Route::post('/api/quiz/result', StoreQuizResultController::class)->name('quiz.result')->middleware('throttle:20,1');

/*
 | Webhooks providers (Stripe/SignWell) : POST externes, hors CSRF (voir bootstrap/app.php), synchrones.
 */
Route::post('/webhooks/payment/{provider}', PaymentWebhookController::class)->name('webhooks.payment');
Route::post('/webhooks/signature', SignatureWebhookController::class)->name('webhooks.signature');

/*
 | Bascule de langue des pages SANS adresse par langue (tunnel, espace client) : memorise la locale en
 | session, recharge la page courante. Les pages publiques, elles, ont un lien direct par langue.
 */
Route::get('/language/{locale}', SwitchLocaleController::class)->name('locale.switch');

/*
 | Espace public indexable : une adresse par langue (routes/localized.php). La langue par defaut garde
 | ses adresses sans prefixe ; les autres sont servies sous /{langue}, avec des noms prefixes ({langue}.).
 | La metadonnee `locale` de la route fixe la langue de la page (cf. SetLocale).
 */
foreach (config('festilaw.supported_locales') as $locale) {
    $localizedRoutes = Route::metadata(['locale' => $locale]);

    if ($locale !== config('festilaw.default_locale')) {
        $localizedRoutes = $localizedRoutes->prefix($locale)->name($locale.'.');
    }

    // Fonction plutot que chemin de fichier : `$locale` est ainsi en portee dans routes/localized.php, ou
    // certaines adresses ont un segment traduit (blog). Aucune route n'a de fermeture pour action : le
    // cache des routes reste possible.
    $localizedRoutes->group(function () use ($locale): void {
        require base_path('routes/localized.php');
    });
}

/*
 | Tunnel de souscription (noindex). Chaque page rend un composant Livewire du parcours. Pas d'adresse
 | par langue : la langue d'affichage vient de la session.
 */
Route::prefix('get-started')->name('get-started.')->group(function () {
    Route::get('/', GetStartedController::class)->name('index');
    Route::get('/pro', ProController::class)->name('pro');
    Route::get('/scale', ScaleController::class)->name('scale');

    // Retour du prestataire de paiement (URL signee, independante du token du dossier qui peut avoir
    // change entre-temps) : redirige vers la page du dossier avec son token actuel.
    Route::get('/payment/{payment:id}/return', PaymentReturnController::class)
        ->middleware('signed:relative')
        ->name('payment.return');

    // Espace SCALE (magic link, porte par le token) : payer l'audit 75 EUR puis reserver la visio.
    Route::get('/scale/{dossier}', ScaleSpaceController::class)->name('scale.space');
    Route::post('/scale/{dossier}/pay', ScaleAuditPaymentController::class)->name('scale.pay');
    Route::post('/scale/{dossier}/book', ScaleBookingController::class)->name('scale.book');

    // Parcours STARTER : page d'ouverture, puis dossier resumable via son token ({dossier}).
    Route::get('/starter', StarterController::class)->name('starter');
    Route::get('/starter/{dossier}', StarterJourneyController::class)->name('starter.journey');

    // Renouvellement annuel : paiement plein tarif declenche depuis l'espace "mon projet" (POST).
    Route::post('/starter/{dossier}/renew', StarterRenewalController::class)->name('starter.renew');

    // Espace "mon dossier" : telechargement du mandat signe et des documents (portes par le token).
    Route::get('/starter/{dossier}/mandate', StarterMandateDownloadController::class)->name('starter.mandate');
    Route::get('/starter/{dossier}/countersigned', StarterCountersignedDownloadController::class)->name('starter.countersigned');
    Route::get('/starter/{dossier}/document/{document}', StarterDocumentDownloadController::class)->name('starter.document');
});

// Espace client "mon projet" (le hub du dossier a tout stade), separe du parcours. Magic link.
Route::view('/my-project', 'web.find-my-project')->name('find-my-project');
Route::get('/my-project/{dossier}', StarterProjectController::class)->name('my-project');

// Page ou mene un lien de dossier qui ne resout plus (DossierLinkInvalidException). Adresse fixe, hors de
// /my-project/{dossier} : elle ne peut pas etre prise pour un lien, quel que soit l'ordre des routes.
Route::view('/link-expired', 'web.dossier-link-invalid')->name('link-expired');

/*
 | Back-office (ADR-002) : Blade + Livewire sur-mesure, prefixe /admin, noindex, hors sitemap.
 | Auth minimale par session ; comptes crees par la commande festilaw:create-admin (pas d'inscription
 | publique). Les dossiers sont routes par id (getRouteKeyName = resume_token cote public, absent hors
 | STARTER). Traitement manuel des dossiers.
 */
Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('/login', LoginForm::class)->middleware('guest')->name('login');

    Route::middleware('auth')->group(function () {
        Route::post('/logout', LogoutController::class)->name('logout');
        Route::get('/', fn () => redirect()->route('admin.submissions.index'))->name('dashboard');
        Route::get('/submissions', SubmissionList::class)->name('submissions.index');
        Route::get('/contacts', SubmissionList::class)->name('contacts.index');
        Route::get('/quiz', QuizResultList::class)->name('quiz.index');
        Route::get('/profile', AdminProfile::class)->name('profile');
        Route::get('/settings', PricingSettings::class)->name('settings');

        // Blog : articles, categories, apercu d'un article quel que soit son statut.
        Route::prefix('blog')->name('blog.')->group(function () {
            Route::get('/posts', BlogPostList::class)->name('posts.index');
            Route::get('/posts/create', BlogPostForm::class)->name('posts.create');
            Route::get('/posts/{post}/edit', BlogPostForm::class)->whereNumber('post')->name('posts.edit');
            Route::get('/posts/{post}/preview/{locale}', BlogPostPreviewController::class)
                ->whereNumber('post')
                ->whereIn('locale', config('festilaw.supported_locales'))
                ->name('posts.preview');
            Route::get('/categories', BlogCategoryList::class)->name('categories.index');
            Route::get('/categories/create', BlogCategoryForm::class)->name('categories.create');
            Route::get('/categories/{category}/edit', BlogCategoryForm::class)->whereNumber('category')->name('categories.edit');
        });
        Route::get('/submissions/{submission:id}', SubmissionDetail::class)->name('submissions.show');
        Route::get('/submissions/{submission:id}/documents/{document:id}', AdminDocumentDownloadController::class)
            ->name('submissions.document')
            ->withoutScopedBindings();
        Route::get('/submissions/{submission:id}/mandate', AdminMandateDownloadController::class)->name('submissions.mandate');
        // Un mandat precis du dossier (mandats remplaces par un changement de pack apres paiement, SC12).
        Route::get('/submissions/{submission:id}/contracts/{contract:id}/mandate', AdminContractMandateDownloadController::class)
            ->name('submissions.contract-mandate');
        Route::get('/submissions/{submission:id}/countersigned', AdminCountersignedDownloadController::class)->name('submissions.countersigned');
    });
});
