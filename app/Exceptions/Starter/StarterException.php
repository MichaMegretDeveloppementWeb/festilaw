<?php

declare(strict_types=1);

namespace App\Exceptions\Starter;

use App\Exceptions\BaseAppException;
use Throwable;

final class StarterException extends BaseAppException
{
    public static function dossierIncomplete(int $submissionId): self
    {
        return new self(
            technicalMessage: "STARTER submission [{$submissionId}] cannot proceed to payment: dossier is incomplete.",
            userMessage: 'Please sign your contract and upload all required documents before paying.',
        );
    }

    public static function renewalNotDue(int $submissionId): self
    {
        return new self(
            technicalMessage: "Submission [{$submissionId}] has no renewal due: cannot start a renewal payment.",
            userMessage: 'Your subscription is up to date · there is nothing to renew right now.',
        );
    }

    public static function contractMissing(int $submissionId): self
    {
        return new self(
            technicalMessage: "STARTER submission [{$submissionId}] has no contract to sign.",
            userMessage: 'Your file could not be found. Please start again or contact us.',
        );
    }

    public static function documentStorageFailed(int $submissionId, string $documentType, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Failed to store STARTER document [{$documentType}] for submission [{$submissionId}].",
            userMessage: 'We could not save your documents. Please try again with a PDF or image under 10 MB.',
            previous: $previous,
        );
    }

    /** @param  list<string>  $missingTypes */
    public static function documentsMissing(int $submissionId, array $missingTypes): self
    {
        $list = implode(', ', $missingTypes);

        return new self(
            technicalMessage: "STARTER submission [{$submissionId}] is missing required documents: [{$list}].",
            userMessage: 'Please add every required document before continuing.',
        );
    }
}
