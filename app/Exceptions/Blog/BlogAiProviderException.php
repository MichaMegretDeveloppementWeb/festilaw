<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Data\Blog\Generation\GenerationUsage;
use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Echec d'un appel au fournisseur d'IA pendant une etape. Porte ce qui a deja ete consomme (un appel qui
 * echoue peut avoir ete facture) pour que le moteur l'enregistre. Un credit epuise n'est pas reessaye :
 * recommencer ne changerait rien tant que le compte n'est pas recharge.
 */
final class BlogAiProviderException extends BaseAppException
{
    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly bool $creditExhausted,
        public readonly ?GenerationUsage $usage,
        ?Throwable $previous,
    ) {
        parent::__construct($technicalMessage, $userMessage, 0, $previous);
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
