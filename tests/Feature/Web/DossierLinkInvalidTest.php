<?php

use App\Livewire\Web\Funnel\AccessFileForm;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

/*
 | Lien de dossier qui ne resout plus (remplace par un plus recent, expire ou inconnu) : page explicative
 | (statut 404) avec le formulaire pour recevoir un nouveau lien, au lieu de la 404 generique.
 */

uses(RefreshDatabase::class);

it('explains an unknown or replaced dossier link and offers a fresh one', function (string $route) {
    get(route($route, ['dossier' => 'no-such-token']))
        ->assertNotFound()
        ->assertSee('This link is no longer valid')
        ->assertSeeLivewire(AccessFileForm::class)
        ->assertSee(route('contact'), false)
        ->assertSee('name="robots" content="noindex, nofollow"', false);
})->with(['get-started.starter.journey', 'my-project', 'get-started.scale.space']);

it('shows the same page for an expired link', function () {
    Submission::factory()->starter()->create(['resume_token' => 'expiredtoken', 'resume_expires_at' => now()->subDay()]);

    get(route('my-project', ['dossier' => 'expiredtoken']))
        ->assertNotFound()
        ->assertSee('This link is no longer valid');
});

it('translates the page', function () {
    get(route('locale.switch', ['locale' => 'fr']))->assertRedirect();

    get(route('get-started.starter.journey', ['dossier' => 'no-such-token']))
        ->assertNotFound()
        ->assertSee("Ce lien n'est plus valide");
});

it('keeps the generic 404 for a valid link used on the wrong journey', function () {
    Submission::factory()->scale()->create(['resume_token' => 'scaletoken', 'resume_expires_at' => now()->addDays(30)]);

    get(route('get-started.starter.journey', ['dossier' => 'scaletoken']))
        ->assertNotFound()
        ->assertDontSee('This link is no longer valid');
});
