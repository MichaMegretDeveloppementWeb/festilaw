<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Data\Blog\BlogPostReadiness;
use App\Exceptions\BaseAppException;
use Carbon\CarbonInterface;

/**
 * Operation refusee sur un article. Les messages utilisateur sont en francais : ils s'affichent au
 * back-office.
 */
final class BlogPostException extends BaseAppException
{
    public static function notFound(int $postId): self
    {
        return (new self(
            technicalMessage: "Blog post [{$postId}] not found.",
            userMessage: 'Cet article est introuvable. Il a peut-être été supprimé.',
        ))->asRefusal();
    }

    public static function categoryNotFound(int $categoryId): self
    {
        return (new self(
            technicalMessage: "Blog category [{$categoryId}] not found while saving a post.",
            userMessage: 'La catégorie choisie n\'existe plus. Choisissez-en une autre.',
        ))->asRefusal();
    }

    /** Publication ou programmation demandee alors qu'il manque quelque chose. */
    public static function notReady(int $postId, BlogPostReadiness $readiness): self
    {
        return (new self(
            technicalMessage: "Blog post [{$postId}] is not ready to be published or scheduled: {$readiness->blockerSummary()}.",
            userMessage: 'L\'article ne peut pas encore être publié. Il manque : '.$readiness->blockerSummary().'.',
        ))->asRefusal();
    }

    /** Enregistrement qui rendrait incomplet un article deja publie ou programme. */
    public static function incompleteWhileLive(int $postId, BlogPostReadiness $readiness): self
    {
        return (new self(
            technicalMessage: "Blog post [{$postId}] is published or scheduled and cannot be saved incomplete: {$readiness->blockerSummary()}.",
            userMessage: 'Cet article est publié ou programmé : il doit rester complet. Il manque : '.$readiness->blockerSummary().'. Complétez-le, ou repassez-le d\'abord en brouillon.',
        ))->asRefusal();
    }

    public static function scheduleNotInFuture(int $postId, CarbonInterface $at): self
    {
        return (new self(
            technicalMessage: "Blog post [{$postId}] cannot be scheduled in the past ({$at->toIso8601String()}).",
            userMessage: 'La date de publication doit être dans le futur.',
        ))->asRefusal();
    }

    public static function alreadyPublished(int $postId): self
    {
        return (new self(
            technicalMessage: "Blog post [{$postId}] is already published and cannot be scheduled.",
            userMessage: 'Cet article est déjà publié. Repassez-le en brouillon pour le programmer à une autre date.',
        ))->asRefusal();
    }
}
