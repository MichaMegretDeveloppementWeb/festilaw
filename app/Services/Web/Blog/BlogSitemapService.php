<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogSitemapSummary;
use App\Data\Web\Seo\SitemapEntry;
use App\Repositories\Blog\BlogSitemapRepository;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Carbon\CarbonImmutable;

/**
 * Les pages du blog a declarer dans le sitemap : chaque article publie, et les categories qui ont un
 * article publie, avec leurs adresses dans chaque langue. Le resume dit a l'index et a la liste des
 * pages s'il y a un blog a declarer (l'accueil du blog n'est indexe qu'a partir du premier article).
 *
 * Plan de requetes : 1 par methode.
 */
final readonly class BlogSitemapService
{
    public function __construct(
        private BlogSitemapRepository $sitemap,
        private LocalizedUrlGenerator $urls,
    ) {}

    /**
     * Null tant qu'aucun article n'est publie.
     */
    public function summary(): ?BlogSitemapSummary
    {
        $row = $this->sitemap->publishedPostSummary();

        if ($row === null) {
            return null;
        }

        return new BlogSitemapSummary(
            $this->w3cDate($row['latest_modification']),
            $row['has_category'],
        );
    }

    /**
     * Les articles publies, du plus recent au plus ancien, avec leur date de derniere modification.
     *
     * @return list<SitemapEntry>
     */
    public function posts(): array
    {
        $posts = [];

        foreach ($this->sitemap->publishedPostSlugs() as $row) {
            $posts[$row['post_id']]['lastmod'] = $this->w3cDate($row['content_updated_at'] ?? $row['published_at']);
            $posts[$row['post_id']]['urls'][$row['locale']] = $this->urls->route('blog.show', ['slug' => $row['slug']], $row['locale']);
        }

        $entries = [];

        foreach ($posts as $post) {
            if ($this->isComplete($post['urls'])) {
                $entries[] = new SitemapEntry($this->ordered($post['urls']), $post['lastmod']);
            }
        }

        return $entries;
    }

    /**
     * Les categories qui ont au moins un article publie, dans l'ordre du menu du blog.
     *
     * @return list<SitemapEntry>
     */
    public function categories(): array
    {
        $categories = [];

        foreach ($this->sitemap->categorySlugsWithPublishedPosts() as $row) {
            $categories[$row['category_id']][$row['locale']] = $this->urls->route('blog.category', ['slug' => $row['slug']], $row['locale']);
        }

        $entries = [];

        foreach ($categories as $urls) {
            if ($this->isComplete($urls)) {
                $entries[] = new SitemapEntry($this->ordered($urls));
            }
        }

        return $entries;
    }

    private function w3cDate(string $date): string
    {
        return CarbonImmutable::parse($date, 'UTC')->toAtomString();
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
