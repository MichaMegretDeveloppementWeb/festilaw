<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

// Les pages publiques lisent la base : lien "Blog" de l'en-tete et du pied de page, bloc de l'accueil.
uses(RefreshDatabase::class);

it('renders a branded, translated 404 page with a way back home', function () {
    get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('class="error-page"', false)
        ->assertSee('Page not found', false)
        ->assertSee('Back to home', false)
        ->assertSee(route('home'), false);
});

it('renders the 404 page of an unknown address under a language prefix in that language', function (string $path, string $title, string $back, string $homeRoute) {
    get($path)
        ->assertNotFound()
        ->assertSee('<html lang="'.explode('/', $path)[1].'">', false)
        ->assertSee($title)
        ->assertSee($back)
        // Le retour a l'accueil et le selecteur de langue restent sur des adresses valides.
        ->assertSee('href="'.route($homeRoute).'" class="btn btn--coral">', false)
        ->assertSee('href="'.route('home').'"', false)
        ->assertDontSee('hreflang', false);
})->with([
    'fr' => ['/fr/cette-page-n-existe-pas', 'Page introuvable', "Retour à l'accueil", 'fr.home'],
    'es' => ['/es/esta-pagina-no-existe', 'Página no encontrada', 'Volver al inicio', 'es.home'],
]);
