<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

use App\Enums\Blog\BlogPostOrigin;
use App\Enums\Blog\BlogPostStatus;
use Carbon\CarbonImmutable;

/**
 * Ce que le formulaire AFFICHE d'un article enregistre, relu a chaque rendu : statut, dates, ce qui
 * bloque la publication, articles lies, apercus des images.
 */
final readonly class BlogPostFormView
{
    /**
     * @param  list<string>  $blockers  ce qui empeche de publier, par exemple "titre (espagnol)"
     * @param  list<string>  $warnings  ce qui est conseille sans etre obligatoire
     * @param  array<string, string>  $publicUrls  page publique par langue, pour un article publie
     * @param  list<array{id: int, title: ?string, status: BlogPostStatus}>  $related  articles lies, dans leur ordre
     * @param  array<int, array{id: int, url: ?string, alt: array<string, string>}>  $images  apercu et descriptions des images de l'article, par identifiant
     * @param  list<string>  $missing  les champs qui empechent de publier, "champ" ou "champ:langue" ("featured_image", "title:es") :
     *                                 un point ambre marque leur libelle (demande de Micha du 24/11)
     */
    public function __construct(
        public BlogPostStatus $status,
        public BlogPostOrigin $origin,
        public ?CarbonImmutable $publishedAt,
        public ?CarbonImmutable $scheduledFor,
        public array $blockers,
        public array $warnings,
        public array $publicUrls,
        public array $related,
        public array $images,
        public ?int $generationId = null,
        public array $missing = [],
    ) {}

    /** Le champ manque-t-il pour publier ? $locale nul pour un champ commun aux langues (categorie, image principale). */
    public function isMissing(string $field, ?string $locale = null): bool
    {
        return in_array($locale === null ? $field : $field.':'.$locale, $this->missing, true);
    }

    public function isReady(): bool
    {
        return $this->blockers === [];
    }
}
