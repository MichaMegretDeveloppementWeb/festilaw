<?php

declare(strict_types=1);

namespace App\Services\Admin\Blog;

use App\Data\Web\Blog\BlogArticlePageData;
use App\Repositories\Blog\BlogPostPreviewRepository;
use App\Services\Web\Blog\BlogArticlePageAssembler;
use Illuminate\Contracts\Routing\UrlGenerator;

/**
 * Apercu d'un article depuis le back-office : la meme page que le site public, pour un article lu par
 * son identifiant quel que soit son statut. Ses adresses sont celles de l'apercu (une par langue).
 *
 * Requetes : 1 (article et texte) + celles de BlogArticlePageAssembler.
 */
final readonly class BlogPostPreviewService
{
    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(
        private BlogPostPreviewRepository $posts,
        private BlogArticlePageAssembler $assembler,
        private UrlGenerator $url,
        private array $locales,
    ) {}

    /** Null quand l'article n'existe pas (ou n'a pas encore de texte dans cette langue). */
    public function page(int $postId, string $locale): ?BlogArticlePageData
    {
        $row = $this->posts->findById($postId, $locale);

        if ($row === null) {
            return null;
        }

        $previews = [];

        foreach ($this->locales as $previewLocale) {
            $previews[$previewLocale] = $this->url->route('admin.blog.posts.preview', ['post' => $postId, 'locale' => $previewLocale]);
        }

        return $this->assembler->assemble($row, $locale, $previews[$locale], $previews);
    }
}
