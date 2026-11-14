<?php

use App\Livewire\Web\Funnel\AccessFileForm;
use App\Mail\StarterResumeLink;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\Support\LivewireRoundTrip;

use function Pest\Laravel\get;

/*
 | Lien de dossier qui ne resout plus (remplace par un plus recent, expire ou inconnu) : le visiteur est
 | redirige vers une page explicative a adresse fixe, avec le formulaire pour recevoir un nouveau lien.
 | La page ne peut pas vivre a l'adresse du lien mort : a chaque aller-retour, Livewire rejoue la route de la
 | page d'origine, la liaison {dossier} echouerait de nouveau et le formulaire ne pourrait rien envoyer
 | (c'etait le cas jusqu'au 14/11/2026 : la page repondait 404 a cette adresse, et son formulaire aussi).
 */

uses(RefreshDatabase::class);

it('sends an unknown or replaced dossier link to the page that offers a fresh one', function (string $route) {
    get(route($route, ['dossier' => 'no-such-token']))->assertRedirect(route('link-expired'));
})->with(['get-started.starter.journey', 'my-project', 'get-started.scale.space']);

it('sends an expired link to the same page', function () {
    Submission::factory()->starter()->create(['resume_token' => 'expiredtoken', 'resume_expires_at' => now()->subDay()]);

    get(route('my-project', ['dossier' => 'expiredtoken']))->assertRedirect(route('link-expired'));
});

it('explains that the link is no longer valid and offers a fresh one', function () {
    get(route('link-expired'))
        ->assertOk()
        ->assertSee('This link is no longer valid')
        ->assertSeeLivewire(AccessFileForm::class)
        ->assertSee(route('contact'), false)
        ->assertSee('name="robots" content="noindex, nofollow"', false);
});

it('translates the page', function () {
    get(route('locale.switch', ['locale' => 'fr']))->assertRedirect();

    $this->followingRedirects()
        ->get(route('get-started.starter.journey', ['dossier' => 'no-such-token']))
        ->assertOk()
        ->assertSee("Ce lien n'est plus valide");
});

it('keeps the generic 404 for a valid link used on the wrong journey', function () {
    Submission::factory()->scale()->create(['resume_token' => 'scaletoken', 'resume_expires_at' => now()->addDays(30)]);

    get(route('get-started.starter.journey', ['dossier' => 'scaletoken']))
        ->assertNotFound()
        ->assertDontSee('This link is no longer valid');
});

// Le test du defaut : une vraie requete du formulaire, depuis la page ou mene un lien mort.
it('really emails a fresh link from the page a dead link leads to', function () {
    Submission::factory()->starter()->create(['email' => 'client@example.com']);
    Mail::fake();

    $page = $this->followingRedirects()
        ->get(route('my-project', ['dossier' => 'no-such-token']))
        ->assertOk();

    $response = LivewireRoundTrip::call($this, $page->getContent(), 'submit', ['email' => 'client@example.com'])->assertOk();

    expect(LivewireRoundTrip::data($response)['sent'])->toBeTrue();
    Mail::assertSent(StarterResumeLink::class, fn (StarterResumeLink $mail) => $mail->hasTo('client@example.com'));
});

it('leads a client whose link died while their dossier was open to that page', function () {
    $dossier = Submission::factory()->starter()->create();
    $page = get(route('get-started.starter.journey', ['dossier' => $dossier->resume_token]))->assertOk();

    $dossier->forceFill(['resume_expires_at' => now()->subMinute()])->save();

    LivewireRoundTrip::call($this, $page->getContent(), '$refresh')->assertRedirect(route('link-expired'));
});
