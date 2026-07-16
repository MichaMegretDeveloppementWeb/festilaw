<?php

use function Pest\Laravel\get;

it('serves each legal page with 200', function (string $routeName, string $needle) {
    get(route($routeName))
        ->assertOk()
        ->assertSee($needle, false);
})->with([
    'legal-notice' => ['legal-notice', 'Publisher'],
    'privacy-policy' => ['privacy-policy', 'Your rights'],
    'terms' => ['terms', 'Governing law'],
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
