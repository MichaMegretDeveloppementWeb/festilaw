<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the client when their annual renewal is due (and again, as an overdue nudge, once the grace
 * window has lapsed). Links to their dossier space where they pay the full annual fee. Dispatched
 * resiliently from the renewal command (a failure is logged, never breaks the run).
 */
final class RenewalReminder extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Submission $submission,
        public int $year,
        public bool $overdue,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Time to renew your Festilaw :pack', [
            'pack' => __($this->submission->type->label()),
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.renewal-reminder', with: [
            'dossierUrl' => route('my-project', ['dossier' => $this->submission->resume_token]),
            'packLabel' => __($this->submission->type->label()),
            'amount' => '€'.number_format($this->submission->type->annualCents() / 100),
        ]);
    }
}
