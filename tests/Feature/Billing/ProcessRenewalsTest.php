<?php

use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Mail\AdminRenewalDigest;
use App\Mail\RenewalReminder;
use App\Models\Payment;
use App\Models\Submission;
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

it('dry run sends nothing and writes no meta', function () {
    Mail::fake();
    $year = (int) now()->year;
    $dossier = renewalDossier($year - 1);

    $this->artisan('festilaw:process-renewals', ['--now' => "{$year}-01-05", '--dry' => true])->assertOk();

    Mail::assertNothingSent();
    expect($dossier->fresh()->meta['renewal'] ?? null)->toBeNull();
});
