<?php

declare(strict_types=1);

namespace App\Actions\Web\Starter;

use App\Enums\Document\DocumentType;
use App\Enums\Notification\FunnelNotificationReason;
use App\Enums\Submission\PackChangeStatus;
use App\Enums\Submission\SubmissionType;
use App\Exceptions\Starter\StarterException;
use App\Mail\FunnelNotification;
use App\Models\PackChange;
use App\Models\Submission;
use App\Services\Notification\TeamNotifier;
use App\Services\Web\Starter\PackChangeService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;

/**
 * Le client Pro demande a revenir au Creator a partir de son prochain renouvellement (SC12). Il joint un
 * justificatif de chiffre d'affaires recent (piece "Proof of turnover" du dossier, ajoutee ou remplacee) et
 * atteste son eligibilite (moins de 35 000 EUR de CA, 9 produits maximum). Rien ne change sans la validation
 * de Festilaw, prevenue par e-mail.
 */
final readonly class RequestPackDowngradeAction
{
    public function __construct(
        private PackChangeService $packChanges,
        private ReplaceStarterDocumentAction $storeDocument,
        private TeamNotifier $teamNotifier,
    ) {}

    public function execute(Submission $submission, UploadedFile $turnoverProof): PackChange
    {
        $request = Cache::lock('checkout:'.$submission->getKey(), 15)->block(10, function () use ($submission, $turnoverProof): PackChange {
            $submission->refresh();

            if (! $this->packChanges->canRequestDowngrade($submission)) {
                throw StarterException::packDowngradeUnavailable($submission->id, 'not an active Pro client, or a change is already in progress');
            }

            $this->storeDocument->execute($submission, DocumentType::TurnoverProof, $turnoverProof, createIfMissing: true);

            return $submission->packChanges()->create([
                'from_pack' => SubmissionType::Pro,
                'to_pack' => SubmissionType::Starter,
                'status' => PackChangeStatus::Requested,
                'effective_year' => $this->packChanges->downgradeEffectiveYear($submission),
                'eligibility_confirmed_at' => now(),
            ]);
        });

        $this->teamNotifier->notify(new FunnelNotification($submission, FunnelNotificationReason::PackDowngradeRequested));

        return $request;
    }
}
