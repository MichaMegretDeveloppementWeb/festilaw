<?php

use App\Http\Middleware\EnsureProductionIsConfigured;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Derriere le proxy de l'hebergement mutualise : detecter correctement HTTPS et l'IP client.
        $middleware->trustProxies(at: '*');
        // ... mais EPINGLER l'hote autorise (domaine d'APP_URL + sous-domaines) : sans ca, un
        // X-Forwarded-Host hostile empoisonnerait les URLs generees (liens de dossier par email, retours
        // Stripe qui portent le token). APP_URL doit etre l'URL canonique de prod.
        $middleware->trustHosts(subdomains: true);

        // Fail-closed : une PROD mal configuree (faux prestataires, mail simule, debug...) refuse de
        // servir plutot que d'encaisser en simulation. Global (prepend) : couvre aussi le webhook fake.
        $middleware->prepend(EnsureProductionIsConfigured::class);

        // En-tetes de securite sur TOUTES les reponses, y compris les erreurs (un 404 "route inconnue"
        // est leve avant le groupe web) : SecurityHeaders est donc global, pas dans le groupe web.
        $middleware->append(SecurityHeaders::class);

        // Sur chaque requete web (apres StartSession) : SetLocale applique la locale de session
        // (y compris /livewire/update).
        $middleware->web(append: [SetLocale::class]);

        // Les webhooks providers (Stripe/SignWell) sont des POST externes : hors CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        // Back-office : les invites vont au login admin, les connectes au tableau des dossiers.
        $middleware->redirectGuestsTo(fn () => route('admin.login'));
        $middleware->redirectUsersTo(fn () => route('admin.submissions.index'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
