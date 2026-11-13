<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogSitemapEntry;
use App\Repositories\Blog\BlogSitemapRepository;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Carbon\CarbonImmutable;

/**
 * Les pages du blog a declarer dans le sitemap : le hub, les categories qui ont un article publie, et
 * chaque article publie, avec leurs adresses dans chaque langue. Rien tant qu'aucun article n'est
 * publie (le hub n'est alors pas indexe).
 *
 * Plan de requetes : 2 (slugs des articles, slugs des categories) ; 1 seule sans article publie.
 */
final readonly class BlogSitemapService
{
    public function __construct(
        private BlogSitemapRepository $sitemap,
        private LocalizedUrlGenerator $urls,
    ) {}

    /**
     * @return list<BlogSitemapEntry>
     */
    public function entries(): array
    {
        $postRows = $this->sitemap->publishedPostSlugs();

        if ($postRows === []) {
            return [];
        }

        $posts = [];
        $latest = null;

        foreach ($postRows as $row) {
            $lastmod = CarbonImmutable::parse($row['content_updated_at'] ?? $row['published_at'], 'UTC');
            $latest = $latest === null || $lastmod->greaterThan($latest) ? $lastmod : $latest;

            $posts[$row['post_id']]['lastmod'] = $lastmod->toAtomString();
            $posts[$row['post_id']]['urls'][$row['locale']] = $this->urls->route('blog.show', ['slug' => $row['slug']], $row['locale']);
        }

        $categories = [];

        foreach ($this->sitemap->categorySlugsWithPublishedPosts() as $row) {
            $categories[$row['category_id']][$row['locale']] = $this->urls->route('blog.category', ['slug' => $row['slug']], $row['locale']);
        }

        $entries = [new BlogSitemapEntry($this->urls->inAllLocales('blog.index'), $latest?->toAtomString())];

        foreach ($categories as $urls) {
            if ($this->isComplete($urls)) {
                $entries[] = new BlogSitemapEntry($this->ordered($urls));
            }
        }

        foreach ($posts as $post) {
            if ($this->isComplete($post['urls'])) {
                $entries[] = new BlogSitemapEntry($this->ordered($post['urls']), $post['lastmod']);
            }
        }

        return $entries;
    }

    /**
     * Une page n'est declaree que si elle existe dans toutes les langues du site.
     *
     * @param  array<string, string>  $urls
     */
    private function isComplete(array $urls): bool
    {
        return array_diff($this->urls->locales(), array_keys($urls)) === [];
    }

    /**
     * @param  array<string, string>  $urls
     * @return array<string, string> dans l'ordre des langues du site
     */
    private function ordered(array $urls): array
    {
        $ordered = [];

        foreach ($this->urls->locales() as $locale) {
            $ordered[$locale] = $urls[$locale];
        }

        return $ordered;
    }
}
