<?php

declare(strict_types=1);

namespace App\Services\Blog\Post;

use App\Data\Blog\BlogAuthorData;

/**
 * Auteur des articles. Il n'y en a qu'un : il vit dans la configuration (config/blog.php), pas en
 * base. Calcul pur.
 */
final readonly class BlogAuthorProvider
{
    /**
     * @param  array{name: string, job_title: array<string, string>}  $author
     */
    public function __construct(
        private array $author,
        private string $defaultLocale,
    ) {}

    /** L'auteur avec sa qualite dans la langue demandee (a defaut, celle de la langue par defaut). */
    public function forLocale(string $locale): BlogAuthorData
    {
        $titles = $this->author['job_title'];

        return new BlogAuthorData(
            name: $this->author['name'],
            jobTitle: $titles[$locale] ?? $titles[$this->defaultLocale] ?? '',
        );
    }
}
