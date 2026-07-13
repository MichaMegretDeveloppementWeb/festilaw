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
        $urls = [];

        foreach (config('festilaw.published_locales') as $locale) {
            foreach (self::INDEXABLE_ROUTES as $name) {
                $urls[] = route($name, ['locale' => $locale]);
            }
        }

        return response()
            ->view('sitemap', ['urls' => $urls])
            ->header('Content-Type', 'application/xml');
    }
}
