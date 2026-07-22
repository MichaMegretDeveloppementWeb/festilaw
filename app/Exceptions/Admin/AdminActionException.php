<?php

declare(strict_types=1);

namespace App\Exceptions\Admin;

use App\Exceptions\BaseAppException;

/**
 * Erreur metier d'une action du back-office (traitement manuel d'un dossier). Le message utilisateur est
 * en francais : ces messages ne s'affichent que dans le back-office interne (francophone), via un toast.
 */
final class AdminActionException extends BaseAppException
{
    public static function responsiblePersonNotPaid(int $submissionId): self
    {
        return new self(
            technicalMessage: "Cannot issue the Responsible Person for submission [{$submissionId}]: no active payment.",
            userMessage: 'Impossible de délivrer la Personne Responsable : le dossier n\'a pas de paiement actif.',
        );
    }

    public static function responsiblePersonMandateNotSigned(int $submissionId): self
    {
        return new self(
            technicalMessage: "Cannot issue the Responsible Person for submission [{$submissionId}]: mandate not signed.",
            userMessage: 'Impossible de délivrer la Personne Responsable : le mandat n\'est pas signé.',
        );
    }

    public static function responsiblePersonDocumentsMissing(int $submissionId): self
    {
        return new self(
            technicalMessage: "Cannot issue the Responsible Person for submission [{$submissionId}]: required documents missing.",
            userMessage: 'Impossible de délivrer la Personne Responsable : toutes les pièces requises ne sont pas déposées.',
        );
    }
}
