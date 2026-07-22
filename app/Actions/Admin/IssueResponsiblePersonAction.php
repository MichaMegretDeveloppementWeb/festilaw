<?php

declare(strict_types=1);

namespace App\Actions\Admin;

use App\Enums\Submission\SubmissionStatus;
use App\Exceptions\Admin\AdminActionException;
use App\Mail\StarterResponsiblePersonIssued;
use App\Models\Submission;
use App\Services\Web\Starter\StarterDossierResolver;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Finalise un dossier STARTER : enregistre l'adresse de Personne Responsable UE delivree, passe le
 * dossier a "termine", et previent le client par email (envoi non bloquant, dans sa langue). La
 * Personne Responsable est le livrable : on ne la delivre (ni ne notifie le client) que si le dossier
 * est reellement pret · paiement actif, mandat signe, toutes les pieces deposees.
 */
final readonly class IssueResponsiblePersonAction
{
    public function __construct(private StarterDossierResolver $resolver) {}

    public function execute(Submission $submission, string $address): void
    {
        $this->assertReadyForIssuance($submission);

        $submission->update([
            'eu_rp_address' => $address,
            'status' => SubmissionStatus::Completed,
        ]);

        if ((string) $submission->email === '') {
            return;
        }

        try {
            Mail::to($submission->email)
                ->locale($submission->locale ?: config('app.locale'))
                ->send(new StarterResponsiblePersonIssued($submission));
        } catch (Throwable $e) {
            Log::error('Failed to send the Responsible Person issued email.', [
                'exception' => $e,
                'submission' => $submission->id,
            ]);
        }
    }

    /**
     * Preconditions metier de la delivrance (message le plus precis d'abord). Empeche notamment un email
     * "votre Personne Responsable est en place" sur un dossier non paye, non signe ou incomplet.
     *
     * @throws AdminActionException
     */
    private function assertReadyForIssuance(Submission $submission): void
    {
        $submission->loadMissing(['contract', 'uploadedDocuments', 'payments']);

        if (! $submission->isActive()) {
            throw AdminActionException::responsiblePersonNotPaid($submission->id);
        }

        $status = $this->resolver->resolve($submission);

        if (! $status->contractSigned) {
            throw AdminActionException::responsiblePersonMandateNotSigned($submission->id);
        }

        if ($status->missingDocuments !== []) {
            throw AdminActionException::responsiblePersonDocumentsMissing($submission->id);
        }
    }
}
