<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer once their SCALE audit payment (75 EUR) is confirmed. Also the safety net for slow
 * async payment methods: even if the buyer left the page, they learn the audit is paid and are invited to
 * book their consultation. Dispatched resiliently from MarkPaymentSucceededAction.
 */
final class ScaleAuditConfirmed extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your Festilaw Scale audit is confirmed'));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.scale-audit-confirmed', with: [
            'spaceUrl' => route('get-started.scale.space', ['dossier' => $this->submission->resume_token]),
        ]);
    }
}
