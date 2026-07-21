<?php

use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Mail\AdminRenewalDigest;
use App\Mail\RenewalReminder;
use App\Models\Payment;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('festilaw.renewal.grace_days', 30);
    config()->set('festilaw.notification_email', 'team@festilaw.test');
});

/** A paid dossier whose subscription covered $serviceYear. */
function renewalDossier(int $serviceYear, string $email = 'client@example.com'): Submission
{
    $submission = Submission::factory()->starter()->create([
        'status' => SubmissionStatus::Paid,
        'email' => $email,
        'locale' => 'fr',
    ]);
    Payment::factory()->succeeded()->for($submission)->create([
        'type' => PaymentType::StarterSubscription,
        'service_year' => $serviceYear,
    ]);

    return $submission->fresh();
}

it('reminds the client and sends an admin digest when a renewal is due in January', function () {
    Mail::fake();
    $year = (int) now()->year;
    renewalDossier($year - 1);

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-05"])->assertOk();

    Mail::assertSent(RenewalReminder::class, fn ($m) => $m->hasTo('client@example.com'));
    Mail::assertSent(AdminRenewalDigest::class, fn ($m) => $m->hasTo('team@festilaw.test') && $m->overdue === false);
});

it('is idempotent within the year (no second reminder on a later run)', function () {
    Mail::fake();
    $year = (int) now()->year;
    renewalDossier($year - 1);

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-05"])->assertOk();
    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-12"])->assertOk();

    Mail::assertSent(RenewalReminder::class, 1);
    Mail::assertSent(AdminRenewalDigest::class, 1);
});

it('sends an overdue admin digest once the grace window has passed', function () {
    Mail::fake();
    $year = (int) now()->year;
    renewalDossier($year - 1);

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-03-01"])->assertOk();

    Mail::assertSent(AdminRenewalDigest::class, fn ($m) => $m->overdue === true);
});

it('does not touch up-to-date dossiers', function () {
    Mail::fake();
    $year = (int) now()->year;
    renewalDossier($year); // paye pour l'annee en cours

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-05"])->assertOk();

    Mail::assertNothingSent();
});

it('does not mark the yearly client reminder when it could not be sent (no email)', function () {
    Mail::fake();
    $year = (int) now()->year;
    $dossier = renewalDossier($year - 1, ''); // dossier actif mais sans email

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-05"])->assertOk();

    // Le rappel client n'a pas pu partir : le jalon annuel n'est PAS pose (le prochain passage reessaie).
    expect($dossier->fresh()->meta['renewal']['reminded_year'] ?? null)->toBeNull();
});

it('does not mark the admin digest meta when the digest failed to send', function () {
    Mail::fake();
    $year = (int) now()->year;
    $dossier = renewalDossier($year - 1);

    // Digest admin en echec (double de TeamNotifier renvoyant false) ; le rappel client, lui, reussit.
    $notifier = mock(TeamNotifier::class);
    $notifier->shouldReceive('notify')->andReturnFalse();
    app()->instance(TeamNotifier::class, $notifier);

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-05"])->assertOk();

    $renewal = $dossier->fresh()->meta['renewal'] ?? [];
    expect($renewal['reminded_year'] ?? null)->toBe($year)          // client OK -> marque
        ->and($renewal['admin_notified_year'] ?? null)->toBeNull(); // digest KO -> non marque, reessaiera
});

it('dry run sends nothing and writes no meta', function () {
    Mail::fake();
    $year = (int) now()->year;
    $dossier = renewalDossier($year - 1);

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-05", '--dry' => true])->assertOk();

    Mail::assertNothingSent();
    expect($dossier->fresh()->meta['renewal'] ?? null)->toBeNull();
});
