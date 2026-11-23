<?php

declare(strict_types=1);

namespace App\Actions\Blog\Post;

use App\Repositories\Blog\BlogPostBacklinkRepository;
use App\Services\Blog\Content\BlogContentRenderer;
use App\Services\Blog\Content\BlogContentSchema;
use App\Services\Blog\Content\BlogLinkPolicy;
use App\Services\Web\Localization\LocalizedUrlGenerator;
use Illuminate\Support\Facades\DB;

/**
 * Quand un article est depublie ou supprime, les autres articles ne le montrent plus (decision de Micha du
 * 20/11) : chaque lien vers une de ses adresses, actuelles ou anciennes, dans les trois langues, est retire de
 * leur texte, qui reste. Le HTML de chaque version touchee est refait. Une republication ne remet pas les liens.
 * Les liens ecrits a la main, en chemin (`/fr/blog/...`) ou en adresse complete du site, sont traites comme ceux
 * de la generation.
 */
final readonly class DetachLinksToBlogPostAction
{
    public function __construct(
        private BlogPostBacklinkRepository $backlinks,
        private BlogContentRenderer $renderer,
        private BlogLinkPolicy $links,
        private LocalizedUrlGenerator $urls,
    ) {}

    /** @return int le nombre de versions d'autres articles dont un lien a ete retire */
    public function execute(int $postId): int
    {
        $slugs = $this->backlinks->slugsOf($postId);
        $paths = array_values(array_unique(array_map(fn (array $slug): string => $this->path($slug['locale'], $slug['slug']), $slugs)));

        if ($paths === []) {
            return 0;
        }

        return DB::transaction(function () use ($postId, $slugs, $paths): int {
            $changed = 0;

            foreach ($this->backlinks->translationsMentioning($postId, array_values(array_unique(array_column($slugs, 'slug')))) as $translation) {
                $removed = false;
                $document = $this->withoutLinksTo((array) $translation->content, $paths, $removed);

                if (! $removed) {
                    continue;
                }

                $html = $this->renderer->render($document, $translation->locale, $translation->post->images);
                $translation->update(['content' => $document, 'content_html' => $html === '' ? null : $html]);
                $changed++;
            }

            return $changed;
        });
    }

    /**
     * Le document sans les marques de lien qui menent a l'article ; le texte qu'elles portaient reste.
     *
     * @param  array<string, mixed>  $node
     * @param  list<string>  $paths
     * @return array<string, mixed>
     */
    private function withoutLinksTo(array $node, array $paths, bool &$removed): array
    {
        if (is_array($node['marks'] ?? null)) {
            $kept = array_values(array_filter($node['marks'], fn (mixed $mark): bool => ! $this->leadsTo($mark, $paths)));

            if (count($kept) !== count($node['marks'])) {
                $removed = true;

                if ($kept === []) {
                    unset($node['marks']);
                } else {
                    $node['marks'] = $kept;
                }
            }
        }

        if (is_array($node['content'] ?? null)) {
            foreach ($node['content'] as $index => $child) {
                if (is_array($child)) {
                    $node['content'][$index] = $this->withoutLinksTo($child, $paths, $removed);
                }
            }
        }

        return $node;
    }

    /** @param  list<string>  $paths */
    private function leadsTo(mixed $mark, array $paths): bool
    {
        if (! is_array($mark) || ($mark['type'] ?? null) !== BlogContentSchema::MARK_LINK) {
            return false;
        }

        $href = (string) ($mark['attrs']['href'] ?? '');

        if ($href === '' || $this->links->isExternal($href) || str_starts_with(strtolower($href), 'mailto:')) {
            return false;
        }

        $path = rtrim((string) parse_url($href, PHP_URL_PATH), '/');

        return in_array($path, $paths, true);
    }

    private function path(string $locale, string $slug): string
    {
        return rtrim((string) parse_url($this->urls->route('blog.show', ['slug' => $slug], $locale), PHP_URL_PATH), '/');
    }
}
