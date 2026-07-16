<?php

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

        // Sur chaque requete web (apres StartSession) : SetLocale applique la locale de session
        // (y compris /livewire/update) ; SecurityHeaders pose les en-tetes de securite (nosniff, etc.).
        $middleware->web(append: [SetLocale::class, SecurityHeaders::class]);

        // Les webhooks providers (Stripe/SignWell) sont des POST externes : hors CSRF.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
