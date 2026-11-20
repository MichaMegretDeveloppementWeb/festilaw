<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Funnel;

use App\Actions\Web\Payment\RememberPaymentVisitAction;
use App\Contracts\Analytics\AudienceTracker;
use App\Enums\Analytics\AudienceEvent;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payment\PaymentReturnService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Landing point of the payment provider's success/cancel URLs (signed route, cf. PaymentReturnService):
 * sends the buyer back to the dossier page with its CURRENT resume token, so a link resent while paying
 * no longer strands them on a 404. The signature (checked by the `signed` middleware) is the access guard.
 *
 * Pour l'audience, ce retour ne compte que l'abandon, geste du visiteur, une fois par paiement (un second
 * passage par la meme adresse ne compte pas). Un paiement reussi se compte a l'encaissement confirme
 * (MarkPaymentSucceededAction) : pour un moyen differe, Stripe renvoie ici avant d'avoir encaisse. Un retour
 * reussi garde la reference de la visite du client sur le paiement, la derniere prise l'emportant.
 */
final class PaymentReturnController extends Controller
{
    public function __construct(
        private readonly PaymentReturnService $returns,
        private readonly AudienceTracker $audience,
        private readonly RememberPaymentVisitAction $rememberVisit,
    ) {}

    public function __invoke(Request $request, Payment $payment): RedirectResponse
    {
        abort_if($payment->submission === null, 404);

        $cancelled = $request->query('status') === 'cancelled';

        if ($cancelled) {
            $this->audience->recordOnce(AudienceEvent::PaymentCancelled, "payment:{$payment->id}");
        } else {
            // La mesure ne doit jamais empecher le client de retrouver son dossier.
            try {
                $this->rememberVisit->execute($payment, $this->audience->currentSession());
            } catch (Throwable $e) {
                Log::error('The visit of a returning buyer could not be kept on the payment.', ['exception' => $e, 'payment' => $payment->id]);
            }
        }

        return redirect()->to($this->returns->destinationFor($payment, $cancelled));
    }
}
