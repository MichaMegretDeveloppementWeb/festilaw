<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Payment;
use App\Models\Submission;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Sent to the client once their switch to the Pro Pack is paid and applied (SC12): the amount paid for the
 * rest of the year, the Pro price from the next renewal, and the link to their file (new signed mandate).
 * Dispatched resiliently from MarkPaymentSucceededAction (a failure is logged, never breaks confirmation).
 */
final class PackUpgradeConfirmed extends Mailable
{
    use SerializesModels;

    public function __construct(public Submission $submission, public Payment $payment) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('You\'re now on the Festilaw :pack', ['pack' => __($this->submission->type->label())]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.pack-upgrade-confirmed', with: [
            'amount' => $this->euros($this->payment->amount_cents),
            'year' => $this->payment->service_year ?? now()->year,
            'annualPrice' => $this->euros($this->submission->type->annualCents()),
            'fileUrl' => route('my-project', ['dossier' => $this->submission->resume_token]),
        ]);
    }

    private function euros(int $cents): string
    {
        return '€'.number_format($cents / 100, $cents % 100 === 0 ? 0 : 2);
    }
}
