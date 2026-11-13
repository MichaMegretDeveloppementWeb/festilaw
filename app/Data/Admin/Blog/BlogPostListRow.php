<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use Carbon\CarbonImmutable;

/**
 * Une ligne de la liste des articles du back-office : ce que la liste affiche, rien de plus.
 */
final readonly class BlogPostListRow
{
    /**
     * @param  string|null  $title  titre francais, a defaut anglais ; null tant qu'aucun n'est saisi
     * @param  list<string>  $writtenLocales  langues redigees (titre, adresse et contenu)
     * @param  string|null  $thumbnailUrl  plus petite variante de l'image principale
     * @param  string|null  $publicUrl  page publique en francais, seulement pour un article publie
     */
    public function __construct(
        public int $id,
        public ?string $title,
        public BlogPostStatus $status,
        public BlogPostOrigin $origin,
        public ?string $categoryName,
        public array $writtenLocales,
        public ?CarbonImmutable $publishedAt,
        public ?CarbonImmutable $scheduledFor,
        public ?string $thumbnailUrl,
        public ?string $publicUrl,
    ) {}
}
