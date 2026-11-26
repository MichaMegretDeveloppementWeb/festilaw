<?php

declare(strict_types=1);

namespace App\Data\Web\Blog;

/**
 * Ce que l'index du sitemap et la liste des pages ont besoin de savoir du blog publie : la date de la
 * derniere modification d'un article, et si au moins un article publie est range dans une categorie
 * (sinon le fichier des categories serait vide).
 */
final readonly class BlogSitemapSummary
{
    /**
     * @param  string  $latestModification  date au format W3C (ISO 8601)
     */
    public function __construct(
        public string $latestModification,
        public bool $hasCategories,
    ) {}
}
