<?php

use function Pest\Laravel\get;

it('shows the sticky funnel CTA on content pages but hides it inside the funnel', function () {
    get(route('home'))->assertSee('class="sticky-cta"', false);
    get(route('pricing'))->assertSee('class="sticky-cta"', false);

    get(route('get-started.index'))->assertDontSee('class="sticky-cta"', false);
});

it('links content pages contextually to the GPSR hub, services, pricing and excluded products', function () {
    // Home -> Understand GPSR (hub), masque sur la page hub elle-meme.
    get(route('home'))->assertSee('Understand the GPSR in plain language', false);
    get(route('understand-gpsr'))->assertDontSee('Understand the GPSR in plain language', false);

    // Understand GPSR -> Services + Excluded products.
    get(route('understand-gpsr'))
        ->assertSee(route('services'), false)
        ->assertSee(route('excluded-products'), false);

    // Services -> Pricing.
    get(route('services'))->assertSee('View plans and pricing', false);
});
