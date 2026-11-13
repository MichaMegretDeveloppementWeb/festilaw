<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Auteur des articles, dans une langue : ce que la page publique et les donnees enrichies affichent.
 */
final readonly class BlogAuthorData
{
    /**
     * @param  string  $profileRoute  nom de la route (localisee) de la page qui presente l'auteur
     */
    public function __construct(
        public string $name,
        public string $jobTitle,
        public string $profileRoute,
    ) {}
}
