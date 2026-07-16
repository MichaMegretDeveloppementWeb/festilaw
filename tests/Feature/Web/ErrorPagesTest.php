<?php

use function Pest\Laravel\get;

it('renders a branded, translated 404 page with a way back home', function () {
    get('/this-page-does-not-exist')
        ->assertNotFound()
        ->assertSee('class="error-page"', false)
        ->assertSee('Page not found', false)
        ->assertSee('Back to home', false)
        ->assertSee(route('home'), false);
});
