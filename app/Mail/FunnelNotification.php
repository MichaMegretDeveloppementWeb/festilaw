<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Generic funnel notification to Festilaw (submission created, payment received...).
 * Sent synchronously from the Action after commit (no queue/worker).
 */
final class FunnelNotification extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Submission $submission,
        public string $reason,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->reason.' — '.($this->submission->company_name ?? $this->submission->email),
        );
    }

    public function content(): Content
    {
        return new Content(view: 'emails.funnel-notification');
    }
}
