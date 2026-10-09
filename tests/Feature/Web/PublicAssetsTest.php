<?php

use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Feature tests share one PHP process; real Hostinger requests start with fresh Livewire state.
    app('livewire')->flushState();
});

it('does not ship Livewire on public information pages', function (string $path) {
    get($path)->assertOk()->assertDontSee('data-update-uri=', false);
})->with(['/', '/about', '/pricing', '/services', '/understand-gpsr', '/excluded-products', '/legal-notice', '/get-started']);

it('still boots Livewire on all public forms', function (string $path) {
    get($path)->assertOk()->assertSee('data-update-uri=', false);
})->with(['/contact', '/get-started/starter', '/get-started/pro', '/get-started/scale', '/my-project']);

it('keeps the resend-link form functional on a 404 response', function () {
    get('/my-project/nonexistent-token')->assertNotFound()
        ->assertSee('data-update-uri=', false)
        ->assertSee('wire:loading', false);
});

it('provides valid photo semantics and an AVIF preload with a WebP fallback', function () {
    get('/')->assertOk()
        ->assertSee('<div class="home-photo" role="img"', false)
        ->assertSee('type="image/avif" fetchpriority="high"', false);
    expect(file_exists(public_path('images/home-transport-optimized.avif')))->toBeTrue()
        ->and(file_exists(public_path('images/home-transport-optimized.webp')))->toBeTrue();
});

it('exposes named tab and panel relationships without Alpine', function () {
    $response = get('/understand-gpsr')->assertOk()->assertDontSee('x-data=', false);
    foreach (range(0, 3) as $index) {
        $response->assertSee('role="tab" id="gpsr-tab-'.$index.'" aria-controls="gpsr-panel-'.$index.'"', false)
            ->assertSee('role="tabpanel" id="gpsr-panel-'.$index.'" aria-labelledby="gpsr-tab-'.$index.'"', false);
    }
});
