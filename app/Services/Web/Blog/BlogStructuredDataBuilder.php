<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogArticlePageData;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Construit les donnees enrichies (schema.org) des pages du blog. Les noeuds rendus rejoignent le
 * graphe unique du layout (variable $jsonLdNodes) ; le fil d'Ariane est ajoute par le layout.
 */
final readonly class BlogStructuredDataBuilder
{
    public function __construct(private UrlGenerator $url) {}

    /**
     * @return array<string, mixed>
     */
    public function article(BlogArticlePageData $page, string $locale): array
    {
        return array_filter([
            '@type' => 'BlogPosting',
            'headline' => $page->title,
            'description' => $page->metaDescription,
            'image' => $page->shareImageUrl === null ? null : [$page->shareImageUrl],
            'datePublished' => $page->publishedAt->toIso8601String(),
            'dateModified' => ($page->updatedAt ?? $page->publishedAt)->toIso8601String(),
            'inLanguage' => $locale,
            'articleSection' => $page->category?->name,
            'url' => $page->url,
            'mainEntityOfPage' => ['@type' => 'WebPage', '@id' => $page->url],
            'author' => [
                '@type' => 'Person',
                'name' => $page->author->name,
                'jobTitle' => $page->author->jobTitle,
                'url' => $page->authorUrl,
            ],
            // L'organisation est deja declaree par le layout : on y renvoie par son identifiant.
            'publisher' => ['@id' => $this->url->to('/').'#organization'],
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Page de liste (hub ou categorie).
     *
     * @return array<string, mixed>
     */
    public function collection(string $name, string $url, ?string $description, string $locale): array
    {
        return array_filter([
            '@type' => 'CollectionPage',
            'name' => $name,
            'description' => $description,
            'url' => $url,
            'inLanguage' => $locale,
            'isPartOf' => ['@type' => 'WebSite', 'url' => $this->url->to('/')],
        ], static fn (mixed $value): bool => $value !== null);
    }
}
