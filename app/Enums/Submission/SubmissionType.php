<?php

declare(strict_types=1);

namespace App\Enums\Submission;

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
    public function annualCents(): int
    {
        return match ($this) {
            self::Starter => (int) config('festilaw.starter.amount_cents', 33300),
            self::Pro => (int) config('festilaw.pro.amount_cents', 120000),
            default => throw new \LogicException("No annual fee for submission type {$this->value}."),
        };
    }
}
