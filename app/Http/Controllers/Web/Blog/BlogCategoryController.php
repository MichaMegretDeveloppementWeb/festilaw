<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Blog;

use App\Http\Controllers\Controller;
use App\Services\Web\Blog\BlogCategoryPageService;
use App\Services\Web\Blog\BlogStructuredDataBuilder;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page d'une categorie du blog. Un ancien slug de categorie est redirige (301) vers son adresse
 * actuelle ; un slug inconnu, une categorie sans article publie ou une page hors limites donnent 404.
 */
final class BlogCategoryController extends Controller
{
    public function __invoke(
        Request $request,
        string $slug,
        BlogCategoryPageService $categories,
        BlogStructuredDataBuilder $structuredData,
        LocalizedUrlGenerator $urls,
    ): View|RedirectResponse {
        $locale = app()->getLocale();
        $page = $categories->page($locale, $slug, max(1, (int) $request->query('page', '1')));

        if ($page === null) {
            $currentUrl = $categories->currentUrlForRetiredSlug($locale, $slug);

            if ($currentUrl !== null) {
                return redirect()->to($currentUrl, 301);
            }

            $urls->declareNoAlternates($urls->inAllLocales('blog.index'));

            abort(404);
        }

        $urls->declareAlternates($page->alternates);

        return view('web.blog.category', [
            'page' => $page,
            'collectionNode' => $structuredData->collection($page->name, $page->url, $page->metaDescription ?? $page->description, $locale),
        ]);
    }
}
