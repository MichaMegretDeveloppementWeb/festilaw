<?php

declare(strict_types=1);

namespace App\Enums\Blog;

/**
 * Statut d'un article. ATTENTION : la colonne blog_posts.status porte une contrainte CHECK en
 * production ; ajouter un cas ici exige une migration de rafraichissement de la contrainte.
 */
enum BlogPostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('Brouillon'),
            self::Scheduled => __('Programmé'),
            self::Published => __('Publié'),
        };
    }

    /** Un article programme ou publie doit rester complet (trois langues redigees, image, categorie). */
    public function requiresCompleteContent(): bool
    {
        return match ($this) {
            self::Draft => false,
            self::Scheduled, self::Published => true,
        };
    }
}
