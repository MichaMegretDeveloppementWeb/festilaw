<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use Throwable;

/**
 * Comment s'est finie l'execution d'une etape, tous essais faits : reussie (avec son resultat), en echec
 * (avec l'erreur du dernier essai), ou interrompue parce qu'un autre worker a repris la generation.
 */
final readonly class GenerationStepOutcome
{
    private function __construct(
        public ?GenerationStepResult $result,
        public ?Throwable $error,
        public bool $creditExhausted,
        public bool $lockLost,
    ) {}

    public static function completed(GenerationStepResult $result): self
    {
        return new self($result, null, false, false);
    }

    public static function failed(Throwable $error, bool $creditExhausted = false): self
    {
        return new self(null, $error, $creditExhausted, false);
    }

    public static function lockLost(): self
    {
        return new self(null, null, false, true);
    }

    public function succeeded(): bool
    {
        return $this->result !== null;
    }
}
