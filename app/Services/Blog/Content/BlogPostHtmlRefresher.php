<?php

declare(strict_types=1);

namespace App\Services\Blog\Content;

use App\Models\BlogPost;
use Illuminate\Database\Eloquent\Collection;

/**
 * Regenere le HTML enregistre d'un article a partir de son document, dans chaque langue. Necessaire
 * quand ce dont le rendu depend change : la description d'une image (cuite dans le HTML), ou le
 * moteur de rendu lui-meme. N'ouvre aucune transaction : l'appelant (Action ou commande) decide.
 */
final readonly class BlogPostHtmlRefresher
{
    private const CHUNK_SIZE = 50;

    public function __construct(private BlogContentRenderer $renderer) {}

    /**
     * Les relations `translations` et `images` de l'article doivent etre chargees.
     *
     * @param  bool  $persist  false = compter ce qui changerait, sans ecrire
     * @return int nombre de langues dont le HTML change
     */
    public function refresh(BlogPost $post, bool $persist = true): int
    {
        $changed = 0;

        foreach ($post->translations as $translation) {
            $html = $this->renderer->render($translation->content, $translation->locale, $post->images);
            $html = $html === '' ? null : $html;

            if ($html === $translation->content_html) {
                continue;
            }

            if ($persist) {
                $translation->update(['content_html' => $html]);
            }

            $changed++;
        }

        return $changed;
    }

    /**
     * Tous les articles, par lots, chacun avec ses textes et ses images charges d'un coup (pas de
     * requete par article dans la boucle).
     *
     * @return array{posts: int, changed: int}
     */
    public function refreshAll(bool $persist = true): array
    {
        $posts = 0;
        $changed = 0;

        BlogPost::query()
            ->with(['translations', 'images'])
            ->chunkById(self::CHUNK_SIZE, function (Collection $chunk) use ($persist, &$posts, &$changed): void {
                foreach ($chunk as $post) {
                    $posts++;
                    $changed += $this->refresh($post, $persist);
                }
            });

        return ['posts' => $posts, 'changed' => $changed];
    }
}
