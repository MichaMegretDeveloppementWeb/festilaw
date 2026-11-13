<?php

declare(strict_types=1);

namespace App\Services\Blog\Post;

use App\Data\Blog\BlogPostReadiness;
use App\Data\Blog\BlogReadinessIssue as Issue;
use App\Models\BlogPost;

/**
 * Dit si un article peut etre publie ou programme, et ce qui lui manque. Calcul pur sur un article
 * dont les relations `translations` et `images` sont deja chargees : aucune requete.
 *
 * Bloquant : titre, adresse ou contenu manquant dans l'une des langues du site (les trois langues
 * sont publiees ensemble), categorie ou image principale manquante. Alerte : extrait, description
 * Google, description d'une image ou sources manquants.
 */
final readonly class BlogPostReadinessCalculator
{
    /**
     * @param  list<string>  $locales  langues du site
     */
    public function __construct(private array $locales) {}

    public function calculate(BlogPost $post): BlogPostReadiness
    {
        $blockers = [];
        $warnings = [];

        foreach ($this->locales as $locale) {
            $translation = $post->translationFor($locale);

            if (blank($translation?->title)) {
                $blockers[] = new Issue(Issue::TITLE, $locale);
            }

            if (blank($translation?->slug)) {
                $blockers[] = new Issue(Issue::SLUG, $locale);
            }

            if (($translation?->word_count ?? 0) === 0) {
                $blockers[] = new Issue(Issue::CONTENT, $locale);
            }

            if (blank($translation?->excerpt)) {
                $warnings[] = new Issue(Issue::EXCERPT, $locale);
            }

            if (blank($translation?->meta_description)) {
                $warnings[] = new Issue(Issue::META_DESCRIPTION, $locale);
            }
        }

        if ($post->blog_category_id === null) {
            $blockers[] = new Issue(Issue::CATEGORY);
        }

        if ($post->featured_image_id === null) {
            $blockers[] = new Issue(Issue::FEATURED_IMAGE);
        }

        foreach ($post->images as $image) {
            foreach ($this->locales as $locale) {
                if ($image->altFor($locale) === '') {
                    $warnings[] = new Issue(Issue::IMAGE_ALT, $locale, $image->id);
                }
            }
        }

        if (blank($post->sources)) {
            $warnings[] = new Issue(Issue::SOURCES);
        }

        return new BlogPostReadiness($blockers, $warnings);
    }
}
