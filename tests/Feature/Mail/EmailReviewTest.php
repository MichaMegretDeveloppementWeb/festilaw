<?php

use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Enums\Submission\SubmissionStatus;
use App\Enums\Submission\SubmissionType;
use App\Mail\AdminRenewalDigest;
use App\Mail\BlogGenerationBatchFinished;
use App\Mail\BlogGenerationFailuresAlert;
use App\Mail\BlogGenerationPaused;
use App\Mail\BlogMonthlySpendWarning;
use App\Mail\BlogSpendLimitReached;
use App\Mail\ContactSubmissionReceived;
use App\Mail\DailyErrorReport;
use App\Mail\FunnelNotification;
use App\Mail\InternalMail;
use App\Mail\PackDowngradeRejected;
use App\Mail\PaymentNeedsReview;
use App\Mail\RenewalReminder;
use App\Mail\SchedulerStalledAlert;
use App\Mail\StarterPaymentConfirmed;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\get;

/*
 | Relecture des e-mails par Micha (23/11) : les e-mails internes partent en francais, la notification d'un
 | dossier montre des libelles, la relance en retard a son titre, un renouvellement paye ne promet plus
 | d'adresse, et l'adresse de contact publique vient d'un seul reglage.
 */

uses(RefreshDatabase::class);

it('sends every mail read by the team or the developer in French', function () {
    expect([
        AdminRenewalDigest::class, BlogGenerationBatchFinished::class, BlogGenerationFailuresAlert::class,
        BlogGenerationPaused::class, BlogMonthlySpendWarning::class, BlogSpendLimitReached::class,
        ContactSubmissionReceived::class, DailyErrorReport::class, FunnelNotification::class,
        PaymentNeedsReview::class, SchedulerStalledAlert::class,
    ])->each(fn ($class) => $class->toExtend(InternalMail::class));
});

it('keeps a team notification in French, labels included, whatever the language of the visitor', function () {
    app()->setLocale('es');
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);

    $html = (new FunnelNotification($submission, FunnelNotificationReason::CreatorSubmission))->render();

    expect($html)->toContain('<html lang="fr"')
        ->toContain('Votre Personne Responsable GPSR dans l&#039;UE')
        ->toContain('Payé')
        ->toContain(__(SubmissionType::Starter->label(), locale: 'fr'))
        ->not->toContain('>starter<')
        ->not->toContain('>paid<');
});

it('titles the overdue renewal reminder as overdue', function () {
    $submission = Submission::factory()->starter()->paid()->create();

    (new RenewalReminder($submission, 2027, overdue: true))->locale('fr')
        ->assertSeeInHtml('Le renouvellement de votre')
        ->assertDontSeeInHtml('Il est temps de renouveler');
});

it('confirms a renewal payment without promising a new address', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create(['status' => SubmissionStatus::Paid]);
    $payment = $submission->payments()->create([
        'type' => PaymentType::AnnualRenewal, 'amount_cents' => 33300, 'service_year' => 2027, 'currency' => 'EUR',
        'provider' => 'stripe', 'provider_reference' => 'cs_renewal', 'status' => PaymentStatus::Pending,
    ]);

    app(MarkPaymentSucceededAction::class)->execute($payment);

    Mail::assertSent(StarterPaymentConfirmed::class, fn (StarterPaymentConfirmed $mail): bool => $mail->renewedYear === 2027);
    (new StarterPaymentConfirmed($submission, 2027))
        ->assertHasSubject('Your Festilaw Creator Pack is renewed')
        ->assertSeeInHtml('renewed for 2027')
        ->assertDontSeeInHtml('within 24 hours');
});

it('shows the public contact address from a single setting, on the site and in the emails to clients', function () {
    config()->set('festilaw.contact_email', 'hello@example.org');
    $submission = Submission::factory()->starter()->paid()->create();

    get('/contact')->assertSee('mailto:hello@example.org', false)->assertDontSee('team@festilaw.com');
    get('/fr/legal-notice')->assertSee('hello@example.org')->assertDontSee('team@festilaw.com');
    expect((new PackDowngradeRejected($submission, null))->render())->toContain('hello@example.org');
});
