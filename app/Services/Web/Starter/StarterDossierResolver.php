<?php

declare(strict_types=1);

namespace App\Services\Web\Starter;

use App\Data\Web\Starter\DossierStatusData;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Enums\Submission\SubmissionStatus;
use App\Models\Submission;

/**
 * Pure calculation service: decides where a STARTER / PRO dossier stands from the submission's
 * already-loaded relations (contract + uploaded documents). No persistence, no side effect.
 * The caller loads the relations before calling it.
 *
 * The journey runs documents -> signature -> payment, and the step is DERIVED from these facts (never
 * from the stored status): a dossier started in the former order (signed first) simply lands on the
 * documents, then goes straight to payment without signing again.
 */
final readonly class StarterDossierResolver
{
    public function resolve(Submission $submission): DossierStatusData
    {
        $contractSigned = $submission->contract?->signature_status === SignatureStatus::Signed;

        $present = $submission->uploadedDocuments->map(static fn ($document): DocumentType => $document->type)->all();

        $missing = array_values(array_filter(
            $submission->type->requiredDocuments(),
            static fn (DocumentType $type): bool => ! in_array($type, $present, true),
        ));

        return new DossierStatusData(
            isComplete: $contractSigned && $missing === [],
            contractSigned: $contractSigned,
            missingDocuments: $missing,
            nextStep: match (true) {
                $missing !== [] => 'documents',
                ! $contractSigned => 'sign',
                default => 'payment',
            },
        );
    }

    /**
     * Stored workflow status matching the facts, written after each step of the journey (documents,
     * signature). The stored status is only a back-office / search cache: "in progress" = not signed yet,
     * "awaiting documents" = signed but documents missing (former order), "awaiting payment" = complete.
     */
    public function workflowStatus(Submission $submission): SubmissionStatus
    {
        $status = $this->resolve($submission);

        return match (true) {
            ! $status->contractSigned => SubmissionStatus::InProgress,
            $status->missingDocuments !== [] => SubmissionStatus::AwaitingDocuments,
            default => SubmissionStatus::AwaitingPayment,
        };
    }
}
