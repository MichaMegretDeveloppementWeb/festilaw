<?php

declare(strict_types=1);

namespace App\Enums\Submission;

use App\Services\Billing\PackPricingService;

enum SubmissionType: string
{
    case Contact = 'contact';
    case Starter = 'starter';
    case Pro = 'pro';
    case Scale = 'scale';

    /** Libelle lisible du parcours (noms de packs, back-office). */
    public function label(): string
    {
        return match ($this) {
            self::Contact => __('Contact'),
            self::Starter => 'Creator Pack',
            self::Pro => 'Pro Pack',
            self::Scale => 'Scale Pack',
        };
    }

    /** Packs a cotisation annuelle empruntant le meme parcours en ligne self-service (signer -> payer). */
    public function hasOnlineJourney(): bool
    {
        return in_array($this, [self::Starter, self::Pro], true);
    }

    /** Cotisation annuelle du pack, en centimes. Seuls Creator et Pro en ont une. */
    /**
     * Effective annual price in cents. Resolved through PackPricingService so an admin override (set in
     * the back-office) takes precedence over the config default, everywhere at once (payment, proration,
     * display, renewal, contract PDF).
     */
    public function annualCents(): int
    {
        return match ($this) {
            self::Starter, self::Pro => app(PackPricingService::class)->annualCents($this),
            default => throw new \LogicException("No annual fee for submission type {$this->value}."),
        };
    }
}
