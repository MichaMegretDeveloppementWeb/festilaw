<?php

declare(strict_types=1);

namespace App\Exceptions\Blog;

use App\Exceptions\BaseAppException;
use Throwable;

/**
 * Slug d'article ou de categorie refuse. Les messages utilisateur sont en francais : ils s'affichent
 * au back-office.
 */
final class BlogSlugException extends BaseAppException
{
    public static function taken(string $locale, string $slug): self
    {
        return new self(
            technicalMessage: "Blog post slug [{$slug}] is already taken in locale [{$locale}].",
            userMessage: "L'adresse « {$slug} » est déjà utilisée par un autre article (".self::language($locale).'). Choisissez-en une autre.',
        );
    }

    public static function reserved(string $locale, string $slug): self
    {
        return new self(
            technicalMessage: "Blog post slug [{$slug}] is a reserved word (locale [{$locale}]).",
            userMessage: "L'adresse « {$slug} » est réservée au fonctionnement du blog (".self::language($locale).'). Choisissez-en une autre.',
        );
    }

    public static function categoryTaken(string $locale, string $slug): self
    {
        return new self(
            technicalMessage: "Blog category slug [{$slug}] is already taken in locale [{$locale}].",
            userMessage: "L'adresse « {$slug} » est déjà utilisée par une autre catégorie (".self::language($locale).'). Choisissez-en une autre.',
        );
    }

    /** Deux enregistrements simultanes ont vise le meme slug : l'index unique a tranche. */
    public static function takenOnWrite(Throwable $previous): self
    {
        return new self(
            technicalMessage: 'Blog slug unique constraint violated on write (concurrent save).',
            userMessage: 'Cette adresse vient d\'être prise par un autre enregistrement. Rechargez la page et choisissez-en une autre.',
            previous: $previous,
        );
    }

    private static function language(string $locale): string
    {
        return match ($locale) {
            'en' => 'anglais',
            'fr' => 'français',
            'es' => 'espagnol',
            default => $locale,
        };
    }
}
