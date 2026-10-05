<?php

declare(strict_types=1);

use App\Actions\Admin\ChangeSubmissionStatusAction;
use App\Actions\Admin\SendAdminMessageAction;
use App\Enums\Submission\SubmissionStatus;
use App\Livewire\Admin\SubmissionDetail;
use App\Livewire\Web\Funnel\AccessFileForm;
use App\Mail\StarterResumeLink;
use App\Models\Submission;
use App\Models\SubmissionNote;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/*
 | Politique de confidentialite (decisions de Festilaw du 04-05/10/2026) :
 | - fin de la relation = dossier annule : le lien magique du client est ferme, rouvrir le dossier le retablit ;
 | - demandes de contact supprimees 12 mois apres le dernier echange (reception, note, e-mail du back-office).
 */

uses(RefreshDatabase::class);

beforeEach(fn () => Mail::fake());

/** Passe le dossier au statut donne depuis le back-office, comme le fait l'operatrice. */
function setStatusFromBackOffice(Submission $dossier, SubmissionStatus $status): void
{
    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $dossier])
        ->set('newStatus', $status->value)
        ->call('updateStatus')
        ->assertHasNoErrors();
}

it('closes the client access of a cancelled dossier: journey, my project and downloads', function () {
    $dossier = Submission::factory()->starter()->paid()->create();
    $token = $dossier->resume_token;

    setStatusFromBackOffice($dossier, SubmissionStatus::Cancelled);

    foreach (['get-started.starter.journey', 'my-project', 'get-started.starter.mandate'] as $route) {
        get(route($route, ['dossier' => $token]))
            ->assertNotFound()
            ->assertSee('This link is no longer valid');
    }
});

it('closes the Scale space of a cancelled Scale dossier', function () {
    $dossier = Submission::factory()->scale()->create([
        'status' => SubmissionStatus::New,
        'resume_token' => 'scaletok',
        'resume_expires_at' => now()->addDays(30),
    ]);

    app(ChangeSubmissionStatusAction::class)->execute($dossier, SubmissionStatus::Cancelled);

    get(route('get-started.scale.space', ['dossier' => 'scaletok']))->assertNotFound();
});

it('never sends a new link for a cancelled dossier', function () {
    $dossier = Submission::factory()->starter()->paid()->create(['email' => 'client@example.com']);
    app(ChangeSubmissionStatusAction::class)->execute($dossier, SubmissionStatus::Cancelled);
    $token = $dossier->fresh()->resume_token;

    Livewire::test(AccessFileForm::class)->set('email', 'client@example.com')->call('submit');

    actingAs(User::factory()->create());
    Livewire::test(SubmissionDetail::class, ['submission' => $dossier->fresh()])
        ->assertDontSee('Renvoyer le lien')
        ->assertSee('Accès client fermé (dossier annulé). Rouvrir le dossier rétablit le lien.')
        ->call('resendLink');

    Mail::assertNotSent(StarterResumeLink::class);
    expect($dossier->fresh()->resume_token)->toBe($token);
});

it('restores the access when a cancelled dossier is reopened: permanent once paid, 30 days otherwise', function () {
    $paid = Submission::factory()->starter()->paid()->create();
    $unpaid = Submission::factory()->starter()->create();
    foreach ([$paid, $unpaid] as $dossier) {
        app(ChangeSubmissionStatusAction::class)->execute($dossier, SubmissionStatus::Cancelled);
    }

    setStatusFromBackOffice($paid->fresh(), SubmissionStatus::Completed);
    setStatusFromBackOffice($unpaid->fresh(), SubmissionStatus::InProgress);

    expect($paid->fresh()->resume_expires_at)->toBeNull()
        ->and($unpaid->fresh()->resume_expires_at->isSameDay(now()->addDays(30)))->toBeTrue();

    get(route('my-project', ['dossier' => $paid->resume_token]))->assertOk();
    get(route('get-started.starter.journey', ['dossier' => $unpaid->resume_token]))->assertOk();
});

it('sends a link valid for the announced 30 days, even for a dossier whose previous link had expired', function () {
    $dossier = Submission::factory()->starter()->create(['resume_expires_at' => now()->subDays(3)]);

    actingAs(User::factory()->create());
    Livewire::test(SubmissionDetail::class, ['submission' => $dossier])->call('resendLink');

    Mail::assertSent(StarterResumeLink::class);
    $dossier->refresh();
    expect($dossier->resume_expires_at->isSameDay(now()->addDays(30)))->toBeTrue();
    get(route('get-started.starter.journey', ['dossier' => $dossier->resume_token]))->assertOk();
});

it('keeps a permanent link when resending to a paid client', function () {
    $dossier = Submission::factory()->starter()->paid()->create();

    actingAs(User::factory()->create());
    Livewire::test(SubmissionDetail::class, ['submission' => $dossier])->call('resendLink');

    Mail::assertSent(StarterResumeLink::class);
    expect($dossier->fresh()->resume_expires_at)->toBeNull();
});

it('closes, once a day, the access of cancelled dossiers whose link is still valid', function () {
    $cancelled = Submission::factory()->starter()->paid()->create();
    $cancelled->update(['status' => SubmissionStatus::Cancelled]); // annule avant la regle : lien encore valable
    $active = Submission::factory()->starter()->paid()->create();

    $this->artisan('festilaw:apply-privacy-retention', ['--dry' => true])
        ->expectsOutputToContain('[DRY-RUN] Acces fermes (dossiers annules) : 1.')
        ->assertOk();
    expect($cancelled->fresh()->resume_expires_at)->toBeNull();

    $this->artisan('festilaw:apply-privacy-retention')
        ->expectsOutputToContain('Acces fermes (dossiers annules) : 1.')
        ->assertOk();

    get(route('my-project', ['dossier' => $cancelled->resume_token]))->assertNotFound();
    get(route('my-project', ['dossier' => $active->resume_token]))->assertOk();
});

it('deletes contact requests (and their notes) twelve months after the last exchange, never other dossiers', function () {
    $this->travel(-13)->months();
    $stale = Submission::factory()->create(['email' => 'stale@example.com']);
    $staleNote = SubmissionNote::factory()->for($stale)->create();
    $answeredByNote = Submission::factory()->create();
    $answeredByEmail = Submission::factory()->create();
    $oldClientDossier = Submission::factory()->starter()->create();
    $this->travelBack();

    $this->travel(-11)->months();
    $recent = Submission::factory()->create();
    $this->travelBack();

    $this->travel(-2)->months();
    SubmissionNote::factory()->for($answeredByNote)->create();               // une note repousse le delai
    app(SendAdminMessageAction::class)->execute($answeredByEmail, 'Re', 'Hello'); // un e-mail aussi
    $this->travelBack();

    $this->artisan('festilaw:apply-privacy-retention', ['--dry' => true])->assertOk();
    expect(Submission::find($stale->id))->not->toBeNull();

    $this->artisan('festilaw:apply-privacy-retention')
        ->expectsOutputToContain('Demandes de contact supprimees : 1.')
        ->assertOk();

    expect(Submission::find($stale->id))->toBeNull()
        ->and(SubmissionNote::find($staleNote->id))->toBeNull()
        ->and(Submission::find($answeredByNote->id))->not->toBeNull()
        ->and(Submission::find($answeredByEmail->id))->not->toBeNull()
        ->and(Submission::find($recent->id))->not->toBeNull()
        ->and(Submission::find($oldClientDossier->id))->not->toBeNull();
});

it('tells the back-office when a contact request will be deleted', function () {
    $contact = Submission::factory()->create();

    actingAs(User::factory()->create());

    Livewire::test(SubmissionDetail::class, ['submission' => $contact])
        ->assertSee('Suppression automatique le '.now()->addMonths(12)->format('d/m/Y'));
});
