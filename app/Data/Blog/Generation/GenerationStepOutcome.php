<?php

declare(strict_types=1);

namespace App\Data\Blog\Generation;

use App\Enums\Blog\Generation\BlogAiFailureKind;
use App\Exceptions\Blog\BlogAiProviderException;
use Throwable;

/**
 * Comment s'est finie l'execution d'une etape : reussie (avec son resultat), en echec (avec l'erreur du dernier
 * essai), interrompue parce qu'un autre worker a repris la generation, pas tentee parce que la generation a
 * atteint son arret de securite (ou qu'un appel l'aurait depasse), arretee a la main pendant l'etape, ou remise a
 * plus tard apres une panne passagere du fournisseur (controle du 20/11).
 */
final readonly class GenerationStepOutcome
{
    private function __construct(
        public ?GenerationStepResult $result,
        public ?Throwable $error,
        public bool $creditExhausted,
        public bool $lockLost,
        public bool $overBudget = false,
        public bool $stoppedBeforeCall = false,
        public ?int $retryInSeconds = null,
        public bool $cancelled = false,
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

    /** @param  bool  $beforeCall  l'appel suivant n'est pas parti, son cout maximal ne tenant plus */
    public static function overBudget(bool $beforeCall = false): self
    {
        return new self(null, null, false, false, true, $beforeCall);
    }

    public static function retryLater(int $seconds): self
    {
        return new self(null, null, false, false, retryInSeconds: $seconds);
    }

    public static function cancelled(): self
    {
        return new self(null, null, false, false, cancelled: true);
    }

    public function succeeded(): bool
    {
        return $this->result !== null;
    }

    /** La sorte de l'echec du fournisseur d'IA, nulle pour un echec d'une autre nature. */
    public function failureKind(): ?BlogAiFailureKind
    {
        return $this->error instanceof BlogAiProviderException ? $this->error->kind : null;
    }
}
