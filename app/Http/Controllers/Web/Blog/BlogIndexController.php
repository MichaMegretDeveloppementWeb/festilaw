<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Blog;

use App\Http\Controllers\Controller;
use App\Services\Web\Blog\BlogHubPageService;
use App\Services\Web\Blog\BlogStructuredDataBuilder;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Page d'accueil du blog (hub). Controller fin : la page est assemblee par BlogHubPageService. Une
 * erreur de lecture remonte au gestionnaire global (page 500 de la langue), rien n'etant a preserver
 * sur une simple lecture.
 */
final class BlogIndexController extends Controller
{
    public function __invoke(
        Request $request,
        BlogHubPageService $hub,
        BlogStructuredDataBuilder $structuredData,
        LocalizedUrlGenerator $urls,
    ): View {
        $locale = app()->getLocale();
        $page = $hub->page($locale, max(1, (int) $request->query('page', '1')));

        if ($page === null) {
            // Page au-dela de la derniere : pas d'equivalent a annoncer, le selecteur ramene au hub.
            $urls->declareNoAlternates($urls->inAllLocales('blog.index'));

            abort(404);
        }

        $urls->declareAlternates($page->alternates);

        return view('web.blog.index', [
            'page' => $page,
            'blogNode' => $structuredData->blog($locale),
        ]);
    }
}
