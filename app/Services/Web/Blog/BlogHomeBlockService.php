<?php

declare(strict_types=1);

namespace App\Services\Web\Blog;

use App\Data\Web\Blog\BlogPostCardData;
use App\Repositories\Blog\PublishedBlogPostCardRepository;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Les derniers articles du bloc de la page d'accueil, dans une langue.
 *
 * Plan de requetes (verifie par BlogQueryBudgetTest) : 2 (les articles, puis leurs images) ; 1 seule
 * quand aucun article n'est publie.
 *
 * Le bloc est secondaire : si la lecture echoue, il est masque et l'erreur journalisee, l'accueil
 * s'affiche quand meme.
 */
final readonly class BlogHomeBlockService
{
    public function __construct(
        private PublishedBlogPostCardRepository $posts,
        private BlogPostCardService $cards,
        private int $limit = 3,
    ) {}

    /**
     * @return list<BlogPostCardData> liste vide = bloc masque
     */
    public function latest(string $locale): array
    {
        try {
            return $this->cards->fromRows($this->posts->listPublished($locale, 0, $this->limit), $locale);
        } catch (Throwable $e) {
            Log::error('Home blog block failed to load; the block is hidden.', ['exception' => $e]);

            return [];
        }
    }
}
