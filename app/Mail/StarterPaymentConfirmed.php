<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the buyer once their STARTER payment is confirmed. Doubles as the safety net for slow async
 * payment methods: even if the buyer left the page, they learn their Creator Pack is active. Dispatched
 * resiliently from MarkPaymentSucceededAction (a failure is logged, never breaks the confirmation).
 */
final class StarterPaymentConfirmed extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Your Festilaw Creator Pack is active');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.starter-payment-confirmed', with: [
            'fileUrl' => route('my-file', [
                'locale' => $this->submission->locale ?: config('app.locale'),
                'dossier' => $this->submission->resume_token,
            ]),
        ]);
    }
}
