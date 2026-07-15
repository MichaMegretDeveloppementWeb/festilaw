<?php

declare(strict_types=1);

namespace App\Mail;

use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the visitor with their STARTER resume link (a capability URL). Lets them continue their file
 * on any device without an account. Also the "retrieve my application" mechanism: re-opening the file
 * form with an email that already has a dossier re-sends this. Dispatched resiliently (a failure is
 * logged, never breaks the flow) via SendStarterResumeLinkAction.
 */
final class StarterResumeLink extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->isActive()
            ? 'Your Festilaw Creator Pack'
            : 'Continue your Festilaw application');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.starter-resume-link',
            with: [
                'resumeUrl' => route('get-started.starter.journey', [
                    'locale' => $this->submission->locale ?: config('app.locale'),
                    'dossier' => $this->submission->resume_token,
                ]),
                'ttlDays' => (int) config('festilaw.starter.resume_ttl_days', 30),
                'isActive' => $this->isActive(),
            ],
        );
    }

    /** The dossier is an already-active (paid) subscription rather than an unfinished application. */
    private function isActive(): bool
    {
        return in_array($this->submission->status, [SubmissionStatus::Paid, SubmissionStatus::Completed], true);
    }
}
