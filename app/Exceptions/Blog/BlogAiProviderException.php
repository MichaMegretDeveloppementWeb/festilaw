<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Data\Blog\Generation\GenerationUsage;
use App\Enums\Blog\Generation\BlogAiFailureKind;
use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Echec d'un appel au fournisseur d'IA pendant une etape. Porte ce qui a deja ete consomme (un appel qui
 * echoue peut avoir ete facture) pour que le moteur l'enregistre, et sa sorte (BlogAiFailureKind), qui dit au
 * moteur s'il faut reessayer, attendre ou s'arreter. Un credit epuise, une cle refusee ou un modele retire ne
 * sont pas reessayes : recommencer ne changerait rien. Un appel refuse par la reservation (son cout maximal ne
 * tient plus avant l'arret de securite) n'est pas parti : le moteur arrete la generation.
 */
final class BlogAiProviderException extends BaseAppException
{
    public readonly bool $creditExhausted;

    public readonly bool $overBudget;

    private function __construct(
        string $technicalMessage,
        string $userMessage,
        public readonly BlogAiFailureKind $kind,
        public readonly ?GenerationUsage $usage,
        ?Throwable $previous,
    ) {
        parent::__construct($technicalMessage, $userMessage, 0, $previous);
        $this->creditExhausted = $kind === BlogAiFailureKind::Credit;
        $this->overBudget = $kind === BlogAiFailureKind::OverBudget;
    }

    public static function overBudget(string $step, int $reservedMicrousd, int $leftMicrousd, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: "AI call of step [{$step}] not sent: it may cost up to {$reservedMicrousd} microusd and only {$leftMicrousd} are left before the safety stop.",
            userMessage: __('L\'appel suivant à l\'IA aurait pu dépasser l\'arrêt de sécurité de la génération.'),
            kind: BlogAiFailureKind::OverBudget,
            usage: $usage,
            previous: null,
        );
    }

    public static function creditExhausted(?Throwable $previous = null, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: 'AI provider refused the call: credit exhausted or key limit reached (HTTP 402).',
            userMessage: __('Le crédit du compte d\'IA est épuisé, ou la limite de la clé est atteinte. Rechargez le compte, puis relancez la génération.'),
            kind: BlogAiFailureKind::Credit,
            usage: $usage,
            previous: $previous,
        );
    }

    public static function keyRejected(string $step, Throwable $previous, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: "AI provider rejected the API key during step [{$step}]: {$previous->getMessage()}",
            userMessage: __('Le fournisseur d\'IA refuse la clé du site.'),
            kind: BlogAiFailureKind::KeyRejected,
            usage: $usage,
            previous: $previous,
        );
    }

    public static function modelUnavailable(string $step, string $model, Throwable $previous, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: "AI model [{$model}] is no longer available for step [{$step}]: {$previous->getMessage()}",
            userMessage: __('Le fournisseur d\'IA ne propose plus le modèle choisi pour cette étape.'),
            kind: BlogAiFailureKind::ModelUnavailable,
            usage: $usage,
            previous: $previous,
        );
    }

    public static function cancelled(string $step, ?GenerationUsage $usage = null): self
    {
        return new self(
            technicalMessage: "AI call of step [{$step}] not sent: the generation was stopped by hand.",
            userMessage: __('La génération a été arrêtée.'),
            kind: BlogAiFailureKind::Cancelled,
            usage: $usage,
            previous: null,
        );
    }

    /**
     * La meme erreur, avec en plus ce que les appels precedents de l'etape ont deja coute : un second appel
     * qui echoue ne fait pas disparaitre le cout du premier.
     */
    public function withEarlierUsage(GenerationUsage $earlier): self
    {
        return new self($this->getMessage(), $this->getUserMessage(), $this->kind, $earlier->plus($this->usage ?? GenerationUsage::none()), $this->getPrevious());
    }

    /** @param  BlogAiFailureKind  $kind  `Other` par defaut : trois essais rapproches, comme avant le controle du 20/11 */
    public static function callFailed(string $step, Throwable $previous, ?GenerationUsage $usage = null, BlogAiFailureKind $kind = BlogAiFailureKind::Other): self
    {
        return new self(
            technicalMessage: "AI provider call failed during step [{$step}]: {$previous->getMessage()}",
            userMessage: __('Le fournisseur d\'IA n\'a pas répondu correctement.'),
            kind: $kind,
            usage: $usage,
            previous: $previous,
        );
    }
}
