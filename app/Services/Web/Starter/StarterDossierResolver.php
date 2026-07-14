<?php

declare(strict_types=1);

namespace App\Services\Web\Starter;

use App\Data\Web\Starter\DossierStatusData;
use App\Enums\Contract\SignatureStatus;
use App\Enums\Document\DocumentType;
use App\Models\Submission;

/**
 * Pure calculation service: decides whether a STARTER dossier is complete from the submission's
 * already-loaded relations (contract + uploaded documents). No persistence, no side effect.
 * The caller loads the relations before calling resolve().
 */
final readonly class StarterDossierResolver
{
    public function resolve(Submission $submission): DossierStatusData
    {
        $contractSigned = $submission->contract?->signature_status === SignatureStatus::Signed;

        $required = array_map(
            static fn (string $value): DocumentType => DocumentType::from($value),
            (array) config('festilaw.starter.required_documents', []),
        );

        $present = $submission->uploadedDocuments->map(static fn ($document): DocumentType => $document->type)->all();

        $missing = array_values(array_filter(
            $required,
            static fn (DocumentType $type): bool => ! in_array($type, $present, true),
        ));

        return new DossierStatusData(
            isComplete: $contractSigned && $missing === [],
            contractSigned: $contractSigned,
            missingDocuments: $missing,
        );
    }
}
