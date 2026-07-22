<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer once their self-service payment is confirmed (Creator OR Pro). Doubles as the safety
 * net for slow async payment methods: even if the buyer left the page, they learn their pack is active.
 * Dispatched resiliently from MarkPaymentSucceededAction (a failure is logged, never breaks confirmation).
 */
final class StarterPaymentConfirmed extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Your Festilaw :pack is active', ['pack' => __($this->submission->type->label())]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.starter-payment-confirmed', with: [
            'fileUrl' => route('my-project', [
                'dossier' => $this->submission->resume_token,
            ]),
        ]);
    }
}
