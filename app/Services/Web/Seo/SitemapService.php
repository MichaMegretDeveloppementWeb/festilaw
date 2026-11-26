<?php

declare(strict_types=1);

namespace App\Services\Web\Seo;

use App\Data\Web\Blog\BlogSitemapSummary;
use App\Data\Web\Seo\SitemapEntry;
use App\Data\Web\Seo\SitemapIndexEntry;
use App\Services\Web\Blog\BlogSitemapService;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Contracts\Routing\UrlGenerator;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * L'index du sitemap et le fichier des pages fixes. L'index cite le fichier des pages, puis ceux des
 * articles et des categories des qu'ils ont quelque chose a lister. Si la lecture du blog echoue, les
 * deux servent les pages fixes seules et l'erreur va au journal : le reste du site reste declare.
 *
 * Plan de requetes : 1 par methode (le resume du blog).
 */
final readonly class SitemapService
{
    /**
     * Pages fixes indexables, une fois par langue (voir plan-du-site.md). Les tunnels, l'espace client
     * et le back-office sont noindex. L'accueil du blog s'ajoute apres elles des le premier article.
     *
     * @var list<string>
     */
    private const FIXED_PAGES = [
        'home',
        'about',
        'understand-gpsr',
        'services',
        'pricing',
        'excluded-products',
        'contact',
    ];

    /**
     * Pages legales, indexables avec une priorite basse : elles ferment la liste.
     *
     * @var list<string>
     */
    private const LEGAL_PAGES = [
        'legal-notice',
        'privacy-policy',
        'terms',
    ];

    public function __construct(
        private BlogSitemapService $blog,
        private LocalizedUrlGenerator $localizedUrls,
        private UrlGenerator $url,
    ) {}

    /**
     * @return list<SitemapIndexEntry>
     */
    public function files(): array
    {
        $summary = $this->blogSummary();
        $latest = $summary?->latestModification;

        $files = [new SitemapIndexEntry($this->url->route('sitemap.pages'), $latest)];

        if ($summary !== null) {
            $files[] = new SitemapIndexEntry($this->url->route('sitemap.posts'), $latest);
        }

        if ($summary?->hasCategories === true) {
            $files[] = new SitemapIndexEntry($this->url->route('sitemap.categories'), $latest);
        }

        return $files;
    }

    /**
     * @return list<SitemapEntry>
     */
    public function pages(): array
    {
        $summary = $this->blogSummary();
        $pages = array_map($this->fixedPage(...), self::FIXED_PAGES);

        if ($summary !== null) {
            $pages[] = new SitemapEntry($this->localizedUrls->inAllLocales('blog.index'), $summary->latestModification);
        }

        return [...$pages, ...array_map($this->fixedPage(...), self::LEGAL_PAGES)];
    }

    private function fixedPage(string $routeName): SitemapEntry
    {
        return new SitemapEntry($this->localizedUrls->inAllLocales($routeName));
    }

    private function blogSummary(): ?BlogSitemapSummary
    {
        try {
            return $this->blog->summary();
        } catch (Throwable $e) {
            Log::error('Blog sitemap entries failed to load; serving the fixed pages only.', ['exception' => $e]);

            return null;
        }
    }
}
