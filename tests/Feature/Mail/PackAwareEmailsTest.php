<?php

use App\Mail\StarterPaymentConfirmed;
use App\Mail\StarterResumeLink;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('names the Creator pack in the payment confirmation email', function () {
    $submission = Submission::factory()->starter()->create();

    $mailable = new StarterPaymentConfirmed($submission);

    $mailable->assertHasSubject('Your Festilaw Creator Pack is active');
    $mailable->assertSeeInHtml('Creator Pack');
});

it('names the Pro pack in the payment confirmation email', function () {
    $submission = Submission::factory()->pro()->create();

    $mailable = new StarterPaymentConfirmed($submission);

    $mailable->assertHasSubject('Your Festilaw Pro Pack is active');
    $mailable->assertSeeInHtml('Pro Pack');
})->group('pro');

it('names the pack in the active-dossier resume link subject', function () {
    $creator = Submission::factory()->starter()->paid()->create();
    $pro = Submission::factory()->pro()->paid()->create();

    (new StarterResumeLink($creator))->assertHasSubject('Your Festilaw Creator Pack');
    (new StarterResumeLink($pro))->assertHasSubject('Your Festilaw Pro Pack');
});
