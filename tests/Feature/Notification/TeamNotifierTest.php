<?php

use App\Enums\Notification\FunnelNotificationReason;
use App\Mail\FunnelNotification;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

it('sends the team notification to the configured address', function () {
    Mail::fake();
    config()->set('festilaw.notification_email', 'team@festilaw.test');

    $submission = Submission::factory()->starter()->create();

    app(TeamNotifier::class)->notify(new FunnelNotification($submission, FunnelNotificationReason::CreatorSubmission));

    Mail::assertSent(FunnelNotification::class, fn (FunnelNotification $mail) => $mail->hasTo('team@festilaw.test'));
});

it('logs and never throws when the notification fails to send', function () {
    // Simulateur d'echec d'envoi (ex: SMTP down) : notify() doit avaler l'erreur et la loguer.
    Mail::shouldReceive('to')->once()->andReturnSelf();
    Mail::shouldReceive('send')->once()->andThrow(new RuntimeException('smtp down'));
    Log::shouldReceive('error')->once();

    $submission = Submission::factory()->starter()->create();

    // Ne doit pas lever : si une exception fuit, le test echoue ici.
    app(TeamNotifier::class)->notify(new FunnelNotification($submission, FunnelNotificationReason::CreatorSubmission));

    expect(true)->toBeTrue();
});
