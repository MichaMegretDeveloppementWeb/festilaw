<?php

declare(strict_types=1);

namespace App\Data\Web\Starter;

use App\Enums\Document\DocumentType;

/**
 * Completeness of a STARTER dossier: contract signed + all required documents present, and the next
 * step of the online journey (documents, then signature, then payment). Provider-agnostic output the UI
 * uses to show progress and the domain uses to gate signature and payment.
 */
final readonly class DossierStatusData
{
    /**
     * @param  list<DocumentType>  $missingDocuments
     * @param  'documents'|'sign'|'payment'  $nextStep
     */
    public function __construct(
        public bool $isComplete,
        public bool $contractSigned,
        public array $missingDocuments,
        public string $nextStep,
    ) {}
}
