<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;

/**
 * Operation refusee sur une categorie du blog. Les messages utilisateur sont en francais : ils
 * s'affichent au back-office.
 */
final class BlogCategoryException extends BaseAppException
{
    public static function notFound(int $categoryId): self
    {
        return (new self(
            technicalMessage: "Blog category [{$categoryId}] not found.",
            userMessage: 'Cette catégorie est introuvable. Elle a peut-être été supprimée.',
        ))->asRefusal();
    }

    public static function nameMissing(string $locale): self
    {
        $language = match ($locale) {
            'en' => 'anglais',
            'fr' => 'français',
            'es' => 'espagnol',
            default => $locale,
        };

        return (new self(
            technicalMessage: "Blog category name is missing for locale [{$locale}].",
            userMessage: "Le nom de la catégorie est obligatoire dans les trois langues. Il manque en {$language}.",
        ))->asRefusal();
    }

    public static function notEmpty(int $categoryId, int $postCount): self
    {
        $posts = $postCount === 1 ? '1 article' : "{$postCount} articles";

        return (new self(
            technicalMessage: "Blog category [{$categoryId}] still holds {$postCount} post(s) and cannot be deleted.",
            userMessage: "Cette catégorie contient encore {$posts}. Rattachez-les à une autre catégorie avant de la supprimer.",
        ))->asRefusal();
    }
}
