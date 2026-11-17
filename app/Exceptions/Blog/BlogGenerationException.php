<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Enums\Blog\Generation\BlogGenerationStatus;
use App\Exceptions\BaseAppException;

/**
 * Refus et incoherences autour d'une generation par IA : lancement refuse par un plafond, relance ou arret
 * impossible dans l'etat courant, configuration du pipeline. Message utilisateur en francais (back-office).
 */
final class BlogGenerationException extends BaseAppException
{
    public static function notFound(int $generationId): self
    {
        return new self(
            technicalMessage: "Blog generation {$generationId} not found.",
            userMessage: __('Cette génération est introuvable.'),
        );
    }

    public static function spendCapReached(int $spentMicrousd, int $capMicrousd): self
    {
        return new self(
            technicalMessage: "Blog generation refused: monthly spend {$spentMicrousd} micro-USD has reached the cap of {$capMicrousd}.",
            userMessage: __('Le plafond de dépense du mois est atteint. Relevez-le dans les réglages pour lancer une génération.'),
        );
    }

    /**
     * @param  string  $high  la borne haute du cout estime, deja ecrite en dollars
     * @param  string  $cap  le plafond par article, deja ecrit en dollars
     */
    public static function forecastAboveArticleCap(int $highMicrousd, int $capMicrousd, string $high, string $cap): self
    {
        return new self(
            technicalMessage: "Blog generation refused: estimated cost up to {$highMicrousd} micro-USD is above the article cap of {$capMicrousd}.",
            userMessage: __('Cet article peut coûter jusqu\'à :high, au-dessus du plafond de :cap par article. Baissez la longueur, ou choisissez d\'autres modèles ou un plafond plus haut dans les réglages.', ['high' => $high, 'cap' => $cap]),
        );
    }

    public static function notRetryable(int $generationId, BlogGenerationStatus $status): self
    {
        return new self(
            technicalMessage: "Blog generation {$generationId} cannot be retried from status {$status->value}.",
            userMessage: __('Seule une génération en échec peut être relancée.'),
        );
    }

    public static function notCancellable(int $generationId, BlogGenerationStatus $status): self
    {
        return new self(
            technicalMessage: "Blog generation {$generationId} cannot be cancelled from status {$status->value}.",
            userMessage: __('Cette génération est déjà terminée.'),
        );
    }

    public static function simulatedPipelineInProduction(): self
    {
        return new self(
            technicalMessage: 'The simulated generation pipeline is refused in production (blog.ai.pipeline).',
            userMessage: __('La génération n\'est pas disponible, car sa configuration est celle des essais.'),
        );
    }

    public static function unknownPipeline(string $name): self
    {
        return new self(
            technicalMessage: "Unknown blog generation pipeline [{$name}] (blog.ai.pipeline).",
            userMessage: __('La génération n\'est pas disponible, car sa configuration est invalide.'),
        );
    }

    public static function missingPrompt(string $name): self
    {
        return new self(
            technicalMessage: "Blog generation prompt [{$name}] is missing or unreadable (resources/prompts/blog).",
            userMessage: __('La génération n\'est pas disponible, car ses consignes sont incomplètes.'),
        );
    }
}
