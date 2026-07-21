<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Web\Starter\MarkContractDeclinedAction;
use App\Actions\Web\Starter\MarkContractExpiredAction;
use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureEventOutcome;
use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Filet ultime des signatures : re-interroge le prestataire (source de verite serveur) pour chaque
 * contrat reste "en attente" au-dela d'un delai, et enregistre le resultat reel (signe / refuse /
 * expire). Rattrape les cas ou le retour navigateur ET le webhook ont ete loupes. Idempotent : ne
 * touche que les contrats en attente, via les actions dediees (transitions dirigees).
 *
 * Planifiee frequemment (routes/console.php). Options :
 *  --minutes=N  age minimal (defaut 15) d'un contrat en attente avant de le reprendre
 *  --dry        affiche ce qui serait fait, sans rien ecrire
 */
final class ReconcileSignatures extends Command
{
    protected $signature = 'festilaw:reconcile-signatures {--minutes=15 : Age minimal (minutes) des contrats en attente} {--dry : Simulation, sans ecriture}';

    protected $description = 'Re-interroge le prestataire pour regler les signatures restees en attente (filet de reconciliation).';

    public function __construct(
        private readonly SignatureGatewayInterface $gateway,
        private readonly MarkContractSignedAction $markContractSigned,
        private readonly MarkContractDeclinedAction $markContractDeclined,
        private readonly MarkContractExpiredAction $markContractExpired,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $minutes = max(0, (int) $this->option('minutes'));
        $dry = (bool) $this->option('dry');
        $cutoff = now()->subMinutes($minutes);

        $checked = 0;
        $signed = 0;
        $settled = 0; // refuses ou expires, ranges pour ne plus etre re-verifies

        Contract::query()
            ->whereIn('signature_status', SignatureStatus::confirmable())
            ->whereNotNull('signature_provider_reference')
            ->where('signature_provider', $this->gateway->key())
            ->where('created_at', '<=', $cutoff)
            ->chunkById(100, function (Collection $contracts) use ($dry, &$signed, &$settled, &$checked): void {
                foreach ($contracts as $contract) {
                    $checked++;

                    try {
                        $event = $this->gateway->checkStatus($contract);
                    } catch (Throwable $e) {
                        Log::channel('signature')->warning('Reconcile: checkStatus failed.', ['exception' => $e, 'contract' => $contract->id]);

                        continue;
                    }

                    // Signe/refuse/expire : on regle. En attente ou indetermine : on laisse.
                    match ($event->outcome) {
                        SignatureEventOutcome::Signed => $this->confirmSigned($contract, $event->providerReference, $dry, $signed),
                        SignatureEventOutcome::Declined => $this->settle(fn () => $this->markContractDeclined->execute($contract), $dry, $settled),
                        SignatureEventOutcome::Expired => $this->settle(fn () => $this->markContractExpired->execute($contract), $dry, $settled),
                        SignatureEventOutcome::Unresolved => null,
                    };
                }
            });

        $this->info('Contrats verifies : '.$checked.' · signes : '.$signed.' · ranges (refuse/expire) : '.$settled.($dry ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }

    private function confirmSigned(Contract $contract, string $providerReference, bool $dry, int &$signed): void
    {
        $signed++;
        if (! $dry) {
            $this->markContractSigned->execute($contract, $providerReference);
            Log::channel('signature')->notice('Signature.reconciled', ['contract' => $contract->id]);
        }
    }

    private function settle(callable $transition, bool $dry, int &$settled): void
    {
        $settled++;
        if (! $dry) {
            $transition();
        }
    }
}
