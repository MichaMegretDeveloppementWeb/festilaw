<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Data\Web\Seo\SitemapEntry;
use App\Services\Web\Blog\BlogSitemapService;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use App\Services\Web\Seo\SitemapService;
use Closure;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Vite;
use Throwable;

/**
 * Le sitemap, decoupe comme celui d'un site WordPress : un index, puis un fichier pour les pages fixes,
 * un pour les articles et un pour les categories. Chaque page y figure une fois par langue, avec ses
 * equivalents dans les autres langues (hreflang). Les fichiers portent noindex : Google les lit sans les
 * montrer dans ses resultats.
 */
final class SitemapController extends Controller
{
    private const STYLESHEET = 'resources/css/web/sitemap/index.css';

    public function index(SitemapService $sitemap): Response
    {
        return $this->xml('sitemap-index', ['files' => $sitemap->files()]);
    }

    public function pages(SitemapService $sitemap, LocalizedUrlGenerator $urls): Response
    {
        return $this->urlset($sitemap->pages(), $urls);
    }

    public function posts(BlogSitemapService $blog, LocalizedUrlGenerator $urls): Response
    {
        return $this->urlset($this->readBlog($blog->posts(...)), $urls);
    }

    public function categories(BlogSitemapService $blog, LocalizedUrlGenerator $urls): Response
    {
        return $this->urlset($this->readBlog($blog->categories(...)), $urls);
    }

    /**
     * Un fichier du blog qui ne se lit pas repond 503 : Google reessaiera. Un fichier vide lui dirait a
     * tort que les articles n'existent plus.
     *
     * @param  Closure(): list<SitemapEntry>  $read
     * @return list<SitemapEntry>
     */
    private function readBlog(Closure $read): array
    {
        try {
            return $read();
        } catch (Throwable $e) {
            Log::error('Blog sitemap file failed to load.', ['exception' => $e]);

            abort(503);
        }
    }

    /**
     * @param  list<SitemapEntry>  $entries
     */
    private function urlset(array $entries, LocalizedUrlGenerator $urls): Response
    {
        return $this->xml('sitemap', ['entries' => $entries, 'defaultLocale' => $urls->defaultLocale()]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function xml(string $view, array $data): Response
    {
        return response()
            ->view($view, [...$data, 'stylesheet' => Vite::asset(self::STYLESHEET)])
            ->header('Content-Type', 'application/xml; charset=UTF-8')
            ->header('X-Robots-Tag', 'noindex');
    }
}
