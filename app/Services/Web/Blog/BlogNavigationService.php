<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Repositories\Blog\PublishedBlogPostCardRepository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Dit si le blog a quelque chose a montrer : le lien "Blog" de l'en-tete et du pied de page n'apparait
 * qu'a partir du premier article publie.
 *
 * Une requete indexee par page, memorisee sur la requete HTTP (l'en-tete et le pied de page la
 * partagent). Pas de cache entre les requetes : le cache du site est en base de donnees, une lecture de
 * cache y couterait autant que la requete qu'elle eviterait, avec en plus une invalidation a tenir.
 *
 * Le blog est secondaire pour le reste du site : si la lecture echoue, le lien est masque et l'erreur
 * journalisee, la page s'affiche quand meme.
 */
final readonly class BlogNavigationService
{
    private const MEMO_ATTRIBUTE = 'blog.has_published_posts';

    public function __construct(
        private PublishedBlogPostCardRepository $posts,
        private Request $request,
    ) {}

    public function hasPublishedPosts(): bool
    {
        $memo = $this->request->attributes->get(self::MEMO_ATTRIBUTE);

        if (is_bool($memo)) {
            return $memo;
        }

        try {
            $hasPosts = $this->posts->existsPublished();
        } catch (Throwable $e) {
            Log::error('Blog navigation check failed; the blog link is hidden.', ['exception' => $e]);

            $hasPosts = false;
        }

        $this->request->attributes->set(self::MEMO_ATTRIBUTE, $hasPosts);

        return $hasPosts;
    }
}
