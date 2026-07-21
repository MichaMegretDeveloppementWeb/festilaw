<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Web\Payment\MarkPaymentExpiredAction;
use App\Actions\Web\Payment\MarkPaymentFailedAction;
use App\Actions\Web\Payment\MarkPaymentSucceededAction;
use App\Enums\Payment\PaymentEventOutcome;
use App\Enums\Payment\PaymentStatus;
use App\Models\Payment;
use App\Services\Payment\PaymentGatewayRegistry;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Filet ultime des paiements : re-interroge le provider (source de verite serveur) pour chaque paiement
 * reste "en attente" au-dela d'un delai, et le confirme s'il est en realite paye. Rattrape les cas ou
 * le retour navigateur ET le webhook ont ete loupes (onglet ferme, webhook perdu). Idempotent : ne
 * touche que les paiements en attente, via MarkPaymentSucceededAction (transition atomique).
 *
 * Planifiee frequemment (routes/console.php). Options :
 *  --minutes=N  age minimal (defaut 15) d'un paiement en attente avant de le reprendre (laisse le
 *               chemin nominal retour+webhook agir d'abord)
 *  --dry        affiche ce qui serait fait, sans rien confirmer
 */
final class ReconcilePayments extends Command
{
    protected $signature = 'festilaw:reconcile-payments {--minutes=15 : Age minimal (minutes) des paiements en attente} {--dry : Simulation, sans ecriture}';

    protected $description = 'Re-interroge le provider pour confirmer les paiements en attente restes bloques (filet de reconciliation).';

    public function __construct(
        private readonly PaymentGatewayRegistry $gateways,
        private readonly MarkPaymentSucceededAction $markPaymentSucceeded,
        private readonly MarkPaymentFailedAction $markPaymentFailed,
        private readonly MarkPaymentExpiredAction $markPaymentExpired,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $minutes = max(0, (int) $this->option('minutes'));
        $dry = (bool) $this->option('dry');
        $cutoff = now()->subMinutes($minutes);

        $checked = 0;
        $confirmed = 0;
        $settled = 0; // expires ou echoues, ranges pour ne plus etre re-verifies

        // On reprend tout etat non definitif (en attente ou async en cours) reste bloque au-dela du delai.
        Payment::query()
            ->whereIn('status', PaymentStatus::confirmable())
            ->whereNotNull('provider_reference')
            ->where('created_at', '<=', $cutoff)
            ->chunkById(100, function (Collection $payments) use ($dry, &$confirmed, &$settled, &$checked): void {
                foreach ($payments as $payment) {
                    if (! $this->gateways->has((string) $payment->provider)) {
                        continue;
                    }

                    $checked++;

                    try {
                        $event = $this->gateways->get((string) $payment->provider)->checkStatus($payment);
                    } catch (Throwable $e) {
                        Log::channel('payments')->warning('Reconcile: checkStatus failed.', ['exception' => $e, 'payment' => $payment->id]);

                        continue;
                    }

                    // Paye/expire/echoue : on regle. En cours ou indetermine : on laisse en attente.
                    match ($event->outcome) {
                        PaymentEventOutcome::Paid => $this->confirmPayment($payment, $event->providerReference, $dry, $confirmed),
                        PaymentEventOutcome::Expired => $this->settlePayment(fn () => $this->markPaymentExpired->execute($payment), $dry, $settled),
                        PaymentEventOutcome::Failed => $this->settlePayment(fn () => $this->markPaymentFailed->execute($payment), $dry, $settled),
                        default => null,
                    };
                }
            });

        $this->info('Paiements verifies : '.$checked.' · confirmes : '.$confirmed.' · ranges (expire/echoue) : '.$settled.($dry ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }

    private function confirmPayment(Payment $payment, string $providerReference, bool $dry, int &$confirmed): void
    {
        $confirmed++;
        if (! $dry) {
            $this->markPaymentSucceeded->execute($payment, $providerReference);
            Log::channel('payments')->notice('Payment.reconciled', ['payment' => $payment->id, 'provider' => $payment->provider]);
        }
    }

    private function settlePayment(callable $transition, bool $dry, int &$settled): void
    {
        $settled++;
        if (! $dry) {
            $transition();
        }
    }
}
