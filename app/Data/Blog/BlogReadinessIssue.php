<?php

declare(strict_types=1);

namespace App\Data\Blog;

/**
 * Un point qui manque a un article : quel champ, dans quelle langue (null = commun aux langues), et
 * pour quelle image le cas echeant.
 */
final readonly class BlogReadinessIssue
{
    public const TITLE = 'title';

    public const SLUG = 'slug';

    public const CONTENT = 'content';

    public const CATEGORY = 'category';

    public const FEATURED_IMAGE = 'featured_image';

    public const EXCERPT = 'excerpt';

    public const META_DESCRIPTION = 'meta_description';

    public const IMAGE_ALT = 'image_alt';

    public const SOURCES = 'sources';

    public function __construct(
        public string $field,
        public ?string $locale = null,
        public ?int $imageId = null,
    ) {}

    /** Libelle en francais pour le back-office, par exemple "titre (espagnol)". */
    public function label(): string
    {
        $field = match ($this->field) {
            self::TITLE => 'titre',
            self::SLUG => 'adresse',
            self::CONTENT => 'contenu',
            self::CATEGORY => 'catégorie',
            self::FEATURED_IMAGE => 'image principale',
            self::EXCERPT => 'extrait',
            self::META_DESCRIPTION => 'description Google',
            self::IMAGE_ALT => 'description d\'une image',
            self::SOURCES => 'sources',
            default => $this->field,
        };

        $language = match ($this->locale) {
            null => null,
            'en' => 'anglais',
            'fr' => 'français',
            'es' => 'espagnol',
            default => $this->locale,
        };

        return $language === null ? $field : "{$field} ({$language})";
    }
}
