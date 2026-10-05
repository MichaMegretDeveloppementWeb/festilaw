<?php

declare(strict_types=1);

namespace App\Exceptions\Admin;

use App\Exceptions\BaseAppException;
use Throwable;

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

    public static function packUpgradeNotRevertible(int $packChangeId): self
    {
        return new self(
            technicalMessage: "Pack change [{$packChangeId}] cannot be reverted: not a completed, paid upgrade.",
            userMessage: 'Ce passage au Pro ne peut pas être annulé : il n\'est pas terminé ou son paiement n\'est plus « réussi ».',
        );
    }

    public static function packDowngradeNotPending(int $packChangeId): self
    {
        return new self(
            technicalMessage: "Pack change [{$packChangeId}] is not a pending switch back to Creator.",
            userMessage: 'Cette demande de passage au Creator n\'est plus en attente (déjà traitée ou retirée par le client).',
        );
    }

    public static function packUpgradeRefundFailed(int $packChangeId, ?Throwable $previous = null): self
    {
        return new self(
            technicalMessage: "Pack change [{$packChangeId}]: the refund of the upgrade payment failed.",
            userMessage: 'Le remboursement n\'a pas pu être effectué chez le prestataire de paiement : rien n\'a été modifié. Réessayez dans un moment, ou remboursez depuis Stripe puis contactez le support.',
            previous: $previous,
        );
    }
}
