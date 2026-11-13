<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Blog;

use App\Enums\Blog\BlogSlugOutcome;
use App\Http\Controllers\Controller;
use App\Services\Blog\Slug\BlogPostSlugLookupService;
use App\Services\Web\Blog\BlogArticlePageService;
use App\Services\Web\Blog\BlogStructuredDataBuilder;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Page d'un article du blog. Le chemin normal ne fait qu'une lecture de l'article ; ce n'est que
 * lorsqu'aucun article publie ne porte le slug qu'on cherche pourquoi : ancien slug (301 vers
 * l'adresse actuelle), article supprime (410), ou rien du tout (404, brouillons compris).
 */
final class BlogPostController extends Controller
{
    public function __invoke(
        string $slug,
        BlogArticlePageService $articles,
        BlogPostSlugLookupService $lookup,
        BlogStructuredDataBuilder $structuredData,
        LocalizedUrlGenerator $urls,
    ): View|RedirectResponse {
        $locale = app()->getLocale();
        $page = $articles->page($locale, $slug);

        if ($page === null) {
            $resolution = $lookup->resolve($locale, $slug);

            if ($resolution->outcome === BlogSlugOutcome::Moved && $resolution->currentSlug !== null) {
                return redirect()->to($urls->route('blog.show', ['slug' => $resolution->currentSlug], $locale), 301);
            }

            // Page d'erreur : aucun equivalent a annoncer, le selecteur de langue ramene au hub.
            $urls->declareNoAlternates($urls->inAllLocales('blog.index'));

            abort($resolution->outcome === BlogSlugOutcome::Gone ? 410 : 404);
        }

        $urls->declareAlternates($page->alternates);

        return view('web.blog.show', [
            'page' => $page,
            'articleNode' => $structuredData->article($page, $locale),
        ]);
    }
}
