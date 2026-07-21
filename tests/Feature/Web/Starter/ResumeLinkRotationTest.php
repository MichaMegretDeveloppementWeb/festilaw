<?php

use App\Actions\Web\Starter\SendStarterResumeLinkAction;
use App\Models\Submission;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

it('rotates the resume token on each link request, killing the previous link', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create();
    $oldToken = $submission->resume_token;

    app(SendStarterResumeLinkAction::class)->execute($submission);

    $newToken = $submission->fresh()->resume_token;
    expect($newToken)->not->toBe($oldToken);

    // L'ancien lien ne resout plus le dossier (404) ; seul le nouveau fonctionne.
    get(route('get-started.starter.journey', ['dossier' => $oldToken]))->assertNotFound();
    get(route('get-started.starter.journey', ['dossier' => $newToken]))->assertOk();
});

it('does not rotate or send anything when there is no email to send to', function () {
    Mail::fake();
    $submission = Submission::factory()->starter()->create(['email' => '']);
    $token = $submission->resume_token;

    app(SendStarterResumeLinkAction::class)->execute($submission);

    expect($submission->fresh()->resume_token)->toBe($token);
    Mail::assertNothingSent();
});
