<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogArticlePageData;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use App\Services\Web\Seo\StructuredDataGraphBuilder;

/**
 * Construit les entites schema.org propres au blog : le blog d'une langue et ses articles. Elles
 * rejoignent le graphe unique du layout (variable $jsonLdNodes), qui y ajoute l'organisation, le site,
 * la page et le fil d'Ariane ; elles citent ces elements par leur identifiant.
 */
final readonly class BlogStructuredDataBuilder
{
    public function __construct(
        private StructuredDataGraphBuilder $graph,
        private LocalizedUrlGenerator $urls,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function article(BlogArticlePageData $page, string $locale): array
    {
        return array_filter([
            '@type' => 'BlogPosting',
            '@id' => $page->url.'#article',
            'headline' => $page->title,
            'description' => $page->metaDescription,
            'image' => $page->shareImageUrl === null ? null : [$page->shareImageUrl],
            'datePublished' => $page->publishedAt->toIso8601String(),
            'dateModified' => ($page->updatedAt ?? $page->publishedAt)->toIso8601String(),
            'inLanguage' => $locale,
            'articleSection' => $page->category?->name,
            'url' => $page->url,
            'isPartOf' => $this->graph->blogReference($locale),
            'mainEntityOfPage' => ['@id' => $page->url.'#webpage'],
            // Pas d'adresse pour l'auteur : aucune page du site ne le presente.
            'author' => [
                '@type' => 'Person',
                'name' => $page->author->name,
                'jobTitle' => $page->author->jobTitle,
            ],
            'publisher' => $this->graph->organizationReference(),
        ], static fn (mixed $value): bool => $value !== null);
    }

    /**
     * Le blog d'une langue, decrit sur son accueil : chaque article de cette langue en fait partie.
     *
     * @return array<string, mixed>
     */
    public function blog(string $locale): array
    {
        return [
            '@type' => 'Blog',
            ...$this->graph->blogReference($locale),
            'name' => __('Festilaw blog'),
            'description' => __('Practical guides and news on EU product safety rules for sellers outside the EU.'),
            'url' => $this->urls->route('blog.index', [], $locale),
            'inLanguage' => $locale,
            'publisher' => $this->graph->organizationReference(),
        ];
    }
}
