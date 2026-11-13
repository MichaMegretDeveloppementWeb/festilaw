<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Web\Blog\BlogSitemapService;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

final class SitemapController extends Controller
{
    /**
     * Pages indexables (voir plan-du-site.md). Les tunnels et le back-office sont noindex.
     *
     * @var list<string>
     */
    private const INDEXABLE_ROUTES = [
        'home',
        'about',
        'understand-gpsr',
        'services',
        'pricing',
        'excluded-products',
        'contact',
    ];

    /**
     * Chaque page figure une fois par langue, avec ses equivalents dans les autres langues (hreflang).
     * Les pages du blog (hub, categories, articles publies) s'y ajoutent avec leur date de modification ;
     * si leur lecture echoue, le sitemap des pages fixes est servi quand meme.
     */
    public function __invoke(LocalizedUrlGenerator $localizedUrls, BlogSitemapService $blog): Response
    {
        $pages = array_map(
            static fn (string $name): array => ['urls' => $localizedUrls->inAllLocales($name), 'lastmod' => null],
            self::INDEXABLE_ROUTES,
        );

        try {
            foreach ($blog->entries() as $entry) {
                $pages[] = ['urls' => $entry->urls, 'lastmod' => $entry->lastmod];
            }
        } catch (Throwable $e) {
            Log::error('Blog sitemap entries failed to load; serving the static pages only.', ['exception' => $e]);
        }

        return response()
            ->view('sitemap', [
                'pages' => $pages,
                'defaultLocale' => $localizedUrls->defaultLocale(),
            ])
            ->header('Content-Type', 'application/xml');
    }
}
