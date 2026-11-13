<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\Response;

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
     */
    public function __invoke(LocalizedUrlGenerator $localizedUrls): Response
    {
        $pages = array_map(
            static fn (string $name): array => $localizedUrls->inAllLocales($name),
            self::INDEXABLE_ROUTES,
        );

        return response()
            ->view('sitemap', [
                'pages' => $pages,
                'defaultLocale' => $localizedUrls->defaultLocale(),
            ])
            ->header('Content-Type', 'application/xml');
    }
}
