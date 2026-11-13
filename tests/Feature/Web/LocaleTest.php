<?php

use function Pest\Laravel\from;
use function Pest\Laravel\get;

/*
 | Langue d'affichage. Les pages publiques ont une adresse par langue : la langue vient de l'adresse
 | (cf. LocalizedRoutingTest). Les pages a adresse unique (tunnel, espace client) gardent la langue en
 | session, changee par le selecteur.
 */

it('displays English by default at the unprefixed address', function () {
    get(route('pricing'))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('Pricing', false);

    expect(route('pricing'))->toEndWith('/pricing');
});

it('displays a public page in the language of its address', function () {
    get(route('fr.pricing'))
        ->assertOk()
        ->assertSee('lang="fr"', false)
        ->assertSee('Tarifs', false);

    get(route('es.pricing'))
        ->assertOk()
        ->assertSee('lang="es"', false)
        ->assertSee('Precios', false);
});

it('switches the language of a single-address page via the switcher and remembers it in session', function () {
    // La bascule memorise la locale en session et renvoie a la page courante (meme adresse).
    from(route('get-started.index'))
        ->get(route('locale.switch', ['locale' => 'fr']))
        ->assertRedirect(route('get-started.index'));

    expect(session('locale'))->toBe('fr');

    get(route('get-started.index'))
        ->assertOk()
        ->assertSee('lang="fr"', false);
});

it('ignores an unsupported locale and stays on the default language', function () {
    get(route('locale.switch', ['locale' => 'de']))->assertRedirect();

    expect(session('locale'))->toBeNull();

    get(route('get-started.index'))->assertSee('lang="en"', false);
});
