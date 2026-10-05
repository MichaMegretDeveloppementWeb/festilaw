<?php

use function Pest\Laravel\get;

it('serves each legal page with the final Festilaw content, in English by default', function (string $routeName, array $needles) {
    $response = get(route($routeName))->assertOk();

    foreach ($needles as $needle) {
        $response->assertSee($needle, false);
    }

    // Plus de bandeau "brouillon", et la version anglaise (de reference) n'affiche pas la mention de traduction.
    $response->assertDontSee('working draft', false)
        ->assertDontSee('the English version prevails', false);
})->with([
    'legal-notice' => ['legal-notice', ['Festilaw B.V.', '77058720', 'NL 860886761B01', 'Hostinger International Ltd.', '8. Governing law']],
    'privacy-policy' => ['privacy-policy', ['1. Data controller', 'Docsketch, LLC', 'Autoriteit Persoonsgegevens', '9. Changes']],
    'terms' => ['terms', ['The service', 'Liability', 'Rechtbank Gelderland']],
]);

it('serves the translated legal pages with the English-prevails notice and a link to the English version', function (string $locale, string $routeName, string $needle, string $notice) {
    get(route('locale.switch', ['locale' => $locale]))->assertRedirect();

    get(route($routeName))
        ->assertOk()
        ->assertSee($needle, false)
        ->assertSee($notice, false)
        ->assertSee(route('locale.switch', ['locale' => 'en']), false)
        ->assertDontSee('working draft', false);
})->with([
    'fr legal-notice' => ['fr', 'legal-notice', '8. Droit applicable', 'la version anglaise prévaut'],
    'fr privacy-policy' => ['fr', 'privacy-policy', '1. Responsable du traitement', 'la version anglaise prévaut'],
    'fr terms' => ['fr', 'terms', 'Rechtbank Gelderland', 'la version anglaise prévaut'],
    'es legal-notice' => ['es', 'legal-notice', '8. Ley aplicable', 'prevalece la versión en inglés'],
    'es privacy-policy' => ['es', 'privacy-policy', '1. Responsable del tratamiento', 'prevalece la versión en inglés'],
    'es terms' => ['es', 'terms', 'Ley aplicable', 'prevalece la versión en inglés'],
]);

it('describes in the privacy policy how the site really works (reformulations validated on 04/10/2026)', function (string $locale, array $present, array $absent) {
    get(route('locale.switch', ['locale' => $locale]))->assertRedirect();

    $response = get(route('privacy-policy'))->assertOk();

    // Destinataires : Stripe, Google (reservation des consultations) et Resend, transfere comme SignWell.
    foreach (['Stripe Payments Europe, Limited', 'Google Ireland Limited', 'Plus Five Five, Inc.', ...$present] as $needle) {
        $response->assertSee($needle, false);
    }
    foreach ($absent as $needle) {
        $response->assertDontSee($needle, false);
    }
})->with([
    'en' => ['en', [
        'website or shop URL',
        'secure personal link sent by email, without an account or password',
        'and our email service provider, Resend, are established in the United States',
        'Last updated: 5 October 2026',
    ], ['login credentials', 'company name, position', 'password-protected', 'log in to the client area', 'Client area accounts']],
    'fr' => ['fr', [
        'adresse de votre site ou de votre boutique',
        'sans compte ni mot de passe',
        'et notre prestataire d\'envoi d\'e-mails, Resend, sont établis aux États-Unis',
        'Dernière mise à jour : 5 octobre 2026',
    ], ['identifiants de connexion', 'nom de société et fonction', 'protégé par mot de passe', 'Comptes de l\'espace client']],
    'es' => ['es', [
        'la dirección de su sitio web o tienda',
        'sin cuenta ni contraseña',
        'y nuestro proveedor de servicios de correo electrónico, Resend, están establecidos en Estados Unidos',
        'Última actualización: 5 de octubre de 2026',
    ], ['credenciales de acceso', 'nombre de la empresa, cargo', 'protegido mediante contraseña', 'Cuentas del área de cliente']],
]);

it('links the footer to the real legal pages with no dead anchors', function () {
    get(route('home'))
        ->assertSee(route('legal-notice'), false)
        ->assertSee(route('privacy-policy'), false)
        ->assertSee(route('terms'), false)
        ->assertDontSee('href="#footer"', false);
});

it('shows a GDPR privacy notice on the public forms linking to the policy', function () {
    get(route('contact'))
        ->assertOk()
        ->assertSee(route('privacy-policy'), false)
        ->assertSee('privacy policy', false);

    get(route('get-started.starter'))
        ->assertOk()
        ->assertSee(route('privacy-policy'), false);
});
