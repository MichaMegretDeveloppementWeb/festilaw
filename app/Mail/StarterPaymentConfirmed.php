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
 * For an annual renewal, it says the pack is renewed for that year and promises no address: the client has had
 * theirs since the first year (Micha, 23/11).
 */
final class StarterPaymentConfirmed extends Mailable
{
    use SerializesModels;

    /** @param  int|null  $renewedYear  the service year an annual renewal pays for; null for the first payment */
    public function __construct(public Submission $submission, public ?int $renewedYear = null) {}

    public function envelope(): Envelope
    {
        $pack = ['pack' => __($this->submission->type->label())];

        return new Envelope(subject: $this->renewedYear !== null
            ? __('Your Festilaw :pack is renewed', $pack)
            : __('Your Festilaw :pack is active', $pack));
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
