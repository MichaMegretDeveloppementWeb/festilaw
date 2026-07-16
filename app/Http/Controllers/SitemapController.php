<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

    public function __invoke(): Response
    {
        $urls = array_map(
            static fn (string $name): string => route($name),
            self::INDEXABLE_ROUTES,
        );

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
