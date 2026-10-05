<?php

declare(strict_types=1);

namespace App\Actions\Web\Payment;

use App\Data\Payment\PaymentReviewData;
use App\Enums\Payment\PaymentStatus;
use App\Enums\Payment\PaymentType;
use App\Mail\PaymentNeedsReview;
use App\Models\Payment;
use App\Services\Notification\TeamNotifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Garde-fou apres confirmation d'un paiement (MarkPaymentSucceededAction, une fois par paiement, quel que
 * soit le chemin : webhook, retour, reconciliation, bouton "verifier chez Stripe"). Le prestataire fait foi
 * et l'argent est recu : la confirmation n'est jamais bloquee. Mais si un autre paiement reussi couvrait deja
 * la meme chose (le client a paye deux fois), ou si le montant encaisse differe du montant attendu, Festilaw
 * est prevenue (journal + e-mail) pour verifier et, le cas echeant, rembourser depuis Stripe.
 */
final readonly class ReviewConfirmedPaymentAction
{
    public function __construct(private TeamNotifier $teamNotifier) {}

    public function execute(Payment $payment, ?int $chargedCents): PaymentReviewData
    {
        $review = new PaymentReviewData(
            duplicateOf: $this->alreadyCoveredBy($payment),
            chargedCents: $chargedCents,
            expectedCents: (int) $payment->amount_cents,
        );

        if ($review->needsReview()) {
            Log::channel('payments')->warning('Payment.needs_review', [
                'payment' => $payment->id,
                'submission' => $payment->submission_id,
                'duplicate_of' => $review->duplicateOf?->id,
                'charged_cents' => $review->chargedCents,
                'expected_cents' => $review->expectedCents,
            ]);

            $this->teamNotifier->notify(new PaymentNeedsReview($payment, $review));
        }

        return $review;
    }

    /**
     * Le paiement reussi (hors celui-ci, rembourses exclus) qui couvrait deja la meme chose : la cotisation de
     * la meme annee (annee 1 ou renouvellement), le passage au Pro de la meme annee, ou l'audit Scale.
     */
    private function alreadyCoveredBy(Payment $payment): ?Payment
    {
        $types = $payment->type->isSubscription() ? PaymentType::subscriptionCases() : [$payment->type];

        return Payment::query()
            ->where('submission_id', $payment->submission_id)
            ->whereKeyNot($payment->getKey())
            ->where('status', PaymentStatus::Succeeded)
            ->whereIn('type', $types)
            ->when($payment->type !== PaymentType::ScaleAudit, fn (Builder $query) => $query->where('service_year', $payment->service_year))
            ->oldest('id')
            ->first();
    }
}
