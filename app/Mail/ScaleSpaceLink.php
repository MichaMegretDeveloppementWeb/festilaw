<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the visitor after they request a SCALE audit: their secure link (capability URL) to the Scale
 * space, where they pay the 75 EUR audit fee and book the consultation. Lets them return on any device
 * without an account. Dispatched resiliently from CreateScaleSubmissionAction (a failure never breaks it).
 */
final class ScaleSpaceLink extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your Festilaw Scale audit'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.scale-space-link', with: [
            'spaceUrl' => route('get-started.scale.space', ['dossier' => $this->submission->resume_token]),
            'ttlDays' => (int) config('festilaw.scale.resume_ttl_days', 30),
        ]);
    }
}
