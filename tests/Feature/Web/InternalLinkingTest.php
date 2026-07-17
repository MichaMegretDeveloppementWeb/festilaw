<?php

use function Pest\Laravel\get;

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
