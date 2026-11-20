<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Ecrans de Falcon Analytics (Audience et Marketing) montes dans le back-office de Festilaw : notre barre
 | laterale, toute la place pour l'ecran, et la meme porte que nos propres ecrans.
 */

uses(RefreshDatabase::class);

it('sends a visitor who is not signed in to the login page', function (string $path) {
    get($path)->assertRedirect(route('admin.login'));
})->with([
    'audience' => ['/admin/analytics'],
    'one audience screen' => ['/admin/analytics/sessions'],
    'marketing' => ['/admin/marketing'],
]);

it('draws the audience and marketing screens in our back-office, with the whole room for the screen', function (string $path, string $title) {
    actingAs(User::factory()->create());

    $content = get($path)->assertOk()->assertSee($title)->getContent();

    // Notre barre laterale (sections Audience et Marketing), sans la colonne ni les marges de nos propres
    // ecrans (`py-6 sm:py-8`) : chaque ecran du package porte sa boite.
    expect($content)->toContain('data-surface="full"')
        ->and($content)->toContain('href="'.route('analytics.admin.realtime').'"')
        ->and($content)->toContain('href="'.route('analytics.admin.marketing.campaigns').'"')
        ->and($content)->toContain('href="'.route('admin.submissions.index').'"')
        ->and($content)->not->toContain('py-6 sm:py-8');
})->with([
    'audience overview' => ['/admin/analytics', 'Vue d\'ensemble'],
    'marketing overview' => ['/admin/marketing', 'Vue d\'ensemble'],
]);

it('keeps the column of our own screens, and signs out from the account menu at the foot of the bar', function () {
    actingAs(User::factory()->create(['name' => 'Laetitia', 'email' => 'laetitia@example.com']));

    $content = get('/admin/submissions')->assertOk()->getContent();

    expect($content)->toContain('data-surface="plain"')
        ->and($content)->toContain('mx-auto max-w-[90em] px-4 py-6 sm:py-8')
        ->and($content)->toContain('laetitia@example.com')
        ->and($content)->toContain('action="'.route('admin.logout').'"')
        ->and($content)->toContain('href="'.route('admin.profile').'"');
});
