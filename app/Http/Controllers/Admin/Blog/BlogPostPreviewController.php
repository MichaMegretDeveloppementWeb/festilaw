<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Blog;

use App\Http\Controllers\Controller;
use App\Services\Admin\Blog\BlogPostPreviewService;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Http\Response;

/**
 * Apercu d'un article depuis le back-office (connexion obligatoire) : la page publique de l'article,
 * dans la langue demandee, quel que soit son statut. Jamais indexee ; le selecteur de langue mene a
 * l'apercu des autres langues.
 */
final class BlogPostPreviewController extends Controller
{
    public function __invoke(int $post, string $locale, BlogPostPreviewService $previews, LocalizedUrlGenerator $urls): Response
    {
        // L'adresse de l'apercu n'est pas une adresse par langue : la langue de la page vient du parametre.
        app()->setLocale($locale);

        $page = $previews->page($post, $locale);

        abort_if($page === null, 404);

        // Aucun equivalent a annoncer aux moteurs ; le selecteur de langue reste dans l'apercu.
        $urls->declareNoAlternates($page->alternates);

        return response()
            ->view('web.blog.show', ['page' => $page, 'articleNode' => null, 'isPreview' => true])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
