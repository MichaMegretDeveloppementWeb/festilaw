<?php

declare(strict_types=1);

namespace App\Data\Admin\Blog;

/**
 * Tout ce que l'ouverture du formulaire d'un article charge, en une fois : les valeurs des champs,
 * l'affichage, et le contenu des trois langues remis au navigateur.
 */
final readonly class BlogPostFormOpening
{
    /**
     * @param  array<string, array<string, mixed>|null>  $contents  document de contenu par langue (null = vide)
     * @param  list<array{where: string|null, statement: string, check: string}>  $attentionPoints  les doutes de la
     *                                                                                              verification d'un
     *                                                                                              brouillon genere
     */
    public function __construct(
        public BlogPostFormState $state,
        public BlogPostFormView $view,
        public array $contents,
        public array $attentionPoints = [],
    ) {}
}
