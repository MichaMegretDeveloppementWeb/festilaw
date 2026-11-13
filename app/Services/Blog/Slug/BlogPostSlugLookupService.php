<?php

declare(strict_types=1);

namespace App\Services\Blog\Slug;

use App\Data\Blog\BlogSlugResolution;
use App\Repositories\Blog\BlogPostSlugRepository;

/**
 * Resout un slug d'article demande par un visiteur. Ordre : slug vivant d'un article publie (200),
 * sinon ancien slug dont l'article est publie (301, directement vers le slug actuel : un seul saut),
 * sinon ancien slug d'un article supprime (410), sinon introuvable (404). Un brouillon ou un article
 * programme n'est jamais revele.
 */
final readonly class BlogPostSlugLookupService
{
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    public function __construct(private BlogPostSlugRepository $slugs) {}

    public function resolve(string $locale, string $slug): BlogSlugResolution
    {
        // Un slug mal forme (majuscules, accents) n'existe pas : pas de requete, et pas de seconde adresse
        // pour la meme page a cause d'une comparaison insensible a la casse cote base.
        if (preg_match(self::SLUG_PATTERN, $slug) !== 1) {
            return BlogSlugResolution::notFound();
        }

        $postId = $this->slugs->findPublishedPostId($locale, $slug);

        if ($postId !== null) {
            return BlogSlugResolution::found($postId);
        }

        $retired = $this->slugs->findRetired($locale, $slug);

        if ($retired === null) {
            return BlogSlugResolution::notFound();
        }

        if ($retired->blog_post_id === null) {
            return BlogSlugResolution::gone();
        }

        $currentSlug = $this->slugs->currentSlugIfPublished($retired->blog_post_id, $locale);

        return $currentSlug === null
            ? BlogSlugResolution::notFound()
            : BlogSlugResolution::moved($retired->blog_post_id, $currentSlug);
    }
}
