<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Analytics\AudienceMeasurementChoice;
use Falcon\Analytics\Facades\Analytics;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/*
 | Mesure d'audience sans bandeau (decision de Micha du 24/11/2026) : un navigateur est reconnu d'une visite a
 | l'autre d'office, et chacun peut refuser d'etre compte depuis la politique de confidentialite. Un refus coupe
 | la mesure comme un compte du back-office connecte.
 */

uses(RefreshDatabase::class);

it('recognises a browser from one visit to the next without asking for consent', function () {
    expect(Analytics::hasConsent())->toBeTrue();
});

it('lets a visitor refuse the measurement from the privacy policy, in the language of the page', function () {
    get('/fr/privacy-policy')->assertOk()
        ->assertSee('Vos visites sont comptées dans la mesure d\'audience.')
        ->assertSee('data-measurement-state="measured"', false)
        ->assertSee('data-track-label="Mesure d&#039;audience, ne plus compter"', false);

    // Le retour suit la langue du bouton, jamais l'adresse precedente que le visiteur peut fausser.
    post(route('audience-measurement'), ['choice' => 'refuse', 'locale' => 'fr'], ['Referer' => 'https://example.org/ailleurs'])
        ->assertRedirect(localized_route('privacy-policy', [], 'fr').'#audience-measurement')
        ->assertPlainCookie(AudienceMeasurementChoice::COOKIE, '1')
        ->assertCookieExpired(AudienceMeasurementChoice::MEASUREMENT_COOKIE);
});

it('stops measuring a browser that refused, collector and server alike, until it accepts again', function () {
    $this->withUnencryptedCookie(AudienceMeasurementChoice::COOKIE, '1');

    expect(get('/')->assertOk()->getContent())->not->toContain('vendor/falcon/analytics/analytics.js')
        ->and(Analytics::isExcluded())->toBeTrue()
        ->and(Analytics::currentSession())->toBeNull();

    get('/es/privacy-policy')->assertOk()
        ->assertSee('Sus visitas ya no se cuentan en la medición de audiencia.')
        ->assertSee('data-measurement-state="refused"', false);

    post(route('audience-measurement'), ['choice' => 'accept', 'locale' => 'es'])
        ->assertRedirect(localized_route('privacy-policy', [], 'es').'#audience-measurement')
        ->assertCookieExpired(AudienceMeasurementChoice::COOKIE);
});

it('keeps measuring a visitor who said nothing, and never a signed-in back-office account', function () {
    expect(get('/')->assertOk()->getContent())->toContain('vendor/falcon/analytics/analytics.js')
        ->and(Analytics::isExcluded())->toBeFalse();

    actingAs(User::factory()->create());

    expect(Analytics::isExcluded())->toBeTrue();
});

it('refuses an unknown choice or language without touching the cookies', function (array $input) {
    post(route('audience-measurement'), $input)
        ->assertSessionHasErrors()
        ->assertCookieMissing(AudienceMeasurementChoice::COOKIE);
})->with([
    'unknown choice' => [['choice' => 'maybe', 'locale' => 'fr']],
    'unknown language' => [['choice' => 'refuse', 'locale' => 'de']],
]);
