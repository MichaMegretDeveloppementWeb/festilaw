<?php

use function Pest\Laravel\get;

/*
 | Traduction visuelle : la langue vit en session (pas dans l'URL). Le site garde un seul jeu d'URLs
 | et l'anglais comme langue canonique ; le selecteur ne fait que changer l'affichage.
 */

it('displays English by default and keeps the locale out of the URL', function () {
    get(route('pricing'))
        ->assertOk()
        ->assertSee('lang="en"', false)
        ->assertSee('Pricing', false);

    expect(route('pricing'))->toEndWith('/pricing');
});

it('switches the display language via the switcher and remembers it in session', function () {
    // La bascule memorise la locale en session et renvoie a la page courante.
    get(route('locale.switch', ['locale' => 'fr']))->assertRedirect();

    expect(session('locale'))->toBe('fr');

    // La MEME URL s'affiche desormais en francais (aucun hreflang, aucun prefixe).
    get(route('pricing'))
        ->assertOk()
        ->assertSee('lang="fr"', false)
        ->assertSee('Tarifs', false)
        ->assertDontSee('hreflang', false);
});

it('ignores an unsupported locale and stays on the default language', function () {
    get(route('locale.switch', ['locale' => 'de']))->assertRedirect();

    expect(session('locale'))->toBeNull();

    get(route('pricing'))->assertSee('lang="en"', false);
});
