<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

// Les pages publiques lisent la base : lien "Blog" de l'en-tete et du pied de page, bloc de l'accueil.
uses(RefreshDatabase::class);

it('sets baseline security headers on web responses', function () {
    $response = get(route('home'));

    expect($response->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($response->headers->get('X-Frame-Options'))->toBe('SAMEORIGIN')
        ->and($response->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin');
});
