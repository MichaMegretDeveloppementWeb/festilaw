<?php

declare(strict_types=1);

namespace App\Enums\Submission;

enum SubmissionStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case AwaitingDocuments = 'awaiting_documents';
    case AwaitingPayment = 'awaiting_payment';
    case Paid = 'paid';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    /**
     * Libelle lisible. Rendu en francais : ces statuts ne sont affiches que dans le back-office
     * interne (francophone), jamais cote client.
     */
    public function label(): string
    {
        return match ($this) {
            self::New => __('Nouveau'),
            self::InProgress => __('En cours'),
            self::AwaitingDocuments => __('En attente de pièces'),
            self::AwaitingPayment => __('En attente de paiement'),
            self::Paid => __('Payé'),
            self::Completed => __('Terminé'),
            self::Cancelled => __('Annulé'),
        };
    }
}
