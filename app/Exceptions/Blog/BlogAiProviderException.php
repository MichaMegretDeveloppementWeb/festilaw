<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Echec d'un appel au fournisseur d'IA pendant une etape. Porte ce qui a deja ete consomme (un appel qui
 * echoue peut avoir ete facture) pour que le moteur l'enregistre. Un credit epuise n'est pas reessaye :
 * recommencer ne changerait rien tant que le compte n'est pas recharge. Un appel refuse par la reservation
 * (son cout maximal ne tient plus avant l'arret de securite) n'est pas parti : le moteur arrete la generation.
 */
final class BlogAiProviderException extends BaseAppException
{
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly bool $creditExhausted,
        public readonly ?GenerationUsage $usage,
        ?Throwable $previous,
        public readonly bool $overBudget = false,
    ) {
        parent::__construct($technicalMessage, $userMessage, 0, $previous);
    }

    public static function overBudget(string $step, int $reservedMicrousd, int $leftMicrousd, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: "AI call of step [{$step}] not sent: it may cost up to {$reservedMicrousd} microusd and only {$leftMicrousd} are left before the safety stop.",
            userMessage: __('L\'appel suivant à l\'IA aurait pu dépasser l\'arrêt de sécurité de la génération.'),
            creditExhausted: false,
            usage: $usage,
            previous: null,
            overBudget: true,
        );
    }

    public static function creditExhausted(?Throwable $previous = null, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: 'AI provider refused the call: credit exhausted or key limit reached (HTTP 402).',
            userMessage: __('Le crédit du compte d\'IA est épuisé, ou la limite de la clé est atteinte. Rechargez le compte, puis relancez la génération.'),
            creditExhausted: true,
            usage: $usage,
            previous: $previous,
        );
    }

    /**
     * La meme erreur, avec en plus ce que les appels precedents de l'etape ont deja coute : un second appel
     * qui echoue ne fait pas disparaitre le cout du premier.
     */
    public function withEarlierUsage(GenerationUsage $earlier): self
    {
        return new self($this->getMessage(), $this->getUserMessage(), $this->creditExhausted, $earlier->plus($this->usage ?? GenerationUsage::none()), $this->getPrevious(), $this->overBudget);
    }

    public static function callFailed(string $step, Throwable $previous, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: "AI provider call failed during step [{$step}]: {$previous->getMessage()}",
            userMessage: __('Le fournisseur d\'IA n\'a pas répondu correctement.'),
            creditExhausted: false,
            usage: $usage,
            previous: $previous,
        );
    }
}
