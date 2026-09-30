<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Web\Starter\MarkContractSignedAction;
use App\Contracts\Signature\SignatureGatewayInterface;
use App\Enums\Contract\SignatureStatus;
use App\Models\Contract;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Rattrapage du PDF signe : un contrat SIGNE dont le fichier n'a pas pu etre telecharge a la confirmation
 * reste "signe" sans fichier local. C'est le cas normal avec la signature integree : le navigateur confirme
 * la signature avant que le prestataire ait genere le PDF final (404 quelques secondes). On re-telecharge
 * ici le fichier manquant, sans toucher au statut. Idempotent.
 *
 * Planifiee chaque minute (routes/console.php) : le mandat signe est disponible en une minute au plus,
 * meme si le webhook du prestataire n'arrive pas. Option --dry : compte sans rien ecrire.
 */
final class BackfillSignedPdfs extends Command
{
    protected $signature = 'festilaw:backfill-signed-pdfs {--dry : Simulation, sans ecriture}';

    protected $description = 'Re-telecharge le PDF des contrats signes dont le fichier manque encore.';

    public function __construct(
        private readonly SignatureGatewayInterface $gateway,
        private readonly MarkContractSignedAction $markContractSigned,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $dry = (bool) $this->option('dry');
        $missing = 0;
        $repaired = 0;

        Contract::query()
            ->where('signature_status', SignatureStatus::Signed)
            ->whereNull('signed_file_path')
            ->whereNotNull('signature_provider_reference')
            ->where('signature_provider', $this->gateway->key())
            ->chunkById(100, function (Collection $contracts) use ($dry, &$missing, &$repaired): void {
                foreach ($contracts as $contract) {
                    $missing++;
                    if ($dry) {
                        continue;
                    }

                    try {
                        $contract = $this->markContractSigned->backfillSignedDocument($contract);
                    } catch (Throwable $e) {
                        Log::channel('signature')->warning('Signed PDF backfill failed.', ['exception' => $e, 'contract' => $contract->id]);

                        continue;
                    }

                    if ($contract->signed_file_path !== null) {
                        $repaired++;
                        Log::channel('signature')->notice('Signature.pdf_backfilled', ['contract' => $contract->id]);
                    }
                }
            });

        $this->info('PDF signes manquants : '.$missing.' · rattrapes : '.$repaired.($dry ? ' [DRY-RUN]' : ''));

        return self::SUCCESS;
    }
}
