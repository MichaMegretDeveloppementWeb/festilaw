<?php

declare(strict_types=1);

namespace App\Data\Web\Starter;

/**
 * View-model du panneau "changer de pack" de l'espace client (SC12). `mode` dit quoi afficher ; le reste
 * fournit les montants et dates des textes. Aucun modele Eloquent n'est passe a la vue.
 */
final readonly class PackChangePanelData
{
    /** Rien a proposer (dossier non actif, renouvellement du, Scale...). */
    public const NONE = 'none';

    /** Creator a jour : peut passer au Pro. */
    public const UPGRADE_OFFER = 'upgrade_offer';

    /** Montee en cours : mandat Pro a signer. */
    public const UPGRADE_SIGN = 'upgrade_sign';

    /** Montee en cours : mandat Pro signe, difference a payer. */
    public const UPGRADE_PAY = 'upgrade_pay';

    /** Montee en cours : paiement en cours de confirmation par le prestataire. */
    public const UPGRADE_PROCESSING = 'upgrade_processing';

    public function __construct(
        public string $mode,
        public string $currentPackLabel,
        public string $otherPackLabel,
        public int $otherAnnualCents,
        public int $quoteCents,
        public int $year,
        public bool $signatureStarted = false,
        public bool $signatureDeclined = false,
    ) {}
}
