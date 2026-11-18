<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Ce qui arrete une partie du banc d'essai des modeles : un sujet ou un article qui n'aboutit pas, le credit du
 * fournisseur epuise, une demande qui nomme un sujet ou une configuration inconnus. Le banc se lance en ligne
 * de commande, le message utilisateur s'adresse a la personne qui l'a lance.
 */
final class BlogBenchException extends BaseAppException
{
    /** @param  bool  $stopsBench  vrai : rien d'autre ne peut aboutir, le banc s'arrete au lieu de passer a la suite */
    public function __construct(string $technicalMessage, string $userMessage, ?Throwable $previous = null, public readonly bool $stopsBench = false)
    {
        parent::__construct($technicalMessage, $userMessage, previous: $previous);
    }

    /** @param  list<string>  $keys */
    public static function unknownKeys(array $keys): self
    {
        $list = implode(', ', $keys);

        return new self(
            technicalMessage: "Blog bench: unknown topic or configuration [{$list}].",
            userMessage: __('Sujet ou configuration inconnu : :list.', ['list' => $list]),
        );
    }

    public static function stepFailed(string $scope, string $step, ?Throwable $previous): self
    {
        return new self(
            technicalMessage: "Blog bench: step [{$step}] failed for [{$scope}].",
            userMessage: __('L\'étape :step n\'a pas abouti pour :scope. Le détail est dans le journal.', ['step' => $step, 'scope' => $scope]),
            previous: $previous,
        );
    }

    public static function overBudget(string $scope, string $step): self
    {
        return new self(
            technicalMessage: "Blog bench: step [{$step}] stopped by the article cap for [{$scope}].",
            userMessage: __('L\'étape :step a été arrêtée par le plafond par article pour :scope.', ['step' => $step, 'scope' => $scope]),
        );
    }

    public static function stoppedWithoutArticle(string $scope, string $step, ?string $reason): self
    {
        return new self(
            technicalMessage: "Blog bench: the pipeline stopped at [{$step}] for [{$scope}] ({$reason}).",
            userMessage: __('Le pipeline s\'est arrêté à l\'étape :step pour :scope, sans article.', ['step' => $step, 'scope' => $scope]),
        );
    }

    public static function creditExhausted(?Throwable $previous): self
    {
        return new self(
            technicalMessage: 'Blog bench: the provider credit is exhausted.',
            userMessage: __('Le crédit du fournisseur d\'IA est épuisé et le banc s\'arrête. Relancez-le avec --run pour reprendre où il en était.'),
            previous: $previous,
            stopsBench: true,
        );
    }

    public static function unexpectedImage(string $mime): self
    {
        return new self(
            technicalMessage: "Blog bench: the image model returned an unexpected file type [{$mime}].",
            userMessage: __('L\'image générée n\'est pas dans un format attendu (:mime).', ['mime' => $mime]),
        );
    }
}
