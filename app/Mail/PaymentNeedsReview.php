<?php

declare(strict_types=1);

namespace App\Mail;

use App\Data\Payment\PaymentReviewData;
use App\Models\Payment;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Alerte interne (francophone) : un paiement confirme est a verifier (double paiement et/ou montant encaisse
 * different du montant attendu). Envoyee a l'adresse de notification de Festilaw, qui rembourse au besoin.
 */
final class PaymentNeedsReview extends Mailable
{
    public function __construct(
        public Payment $payment,
        public PaymentReviewData $review,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('Festilaw · paiement à vérifier · :reference', [
            'reference' => (string) $this->payment->submission?->reference,
        ]));
    }

    public function content(): Content
    {
        return new Content(view: 'emails.payment-needs-review', with: [
            'amount' => $this->euros($this->payment->amount_cents),
            'duplicateAmount' => $this->review->duplicateOf ? $this->euros($this->review->duplicateOf->amount_cents) : null,
            'chargedAmount' => $this->euros($this->review->chargedCents),
            'expectedAmount' => $this->euros($this->review->expectedCents),
            'dossierUrl' => route('admin.submissions.show', ['submission' => $this->payment->submission_id]),
        ]);
    }

    /** Meme format que le back-office : "333,00 EUR". */
    private function euros(?int $cents): string
    {
        return number_format(((int) $cents) / 100, 2, ',', ' ').' EUR';
    }
}
