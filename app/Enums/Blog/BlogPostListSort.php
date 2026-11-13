<?php

declare(strict_types=1);

namespace App\Enums\Blog;

/**
 * Colonne de tri de la liste des articles du back-office. Valeur portee par l'adresse de la liste.
 */
enum BlogPostListSort: string
{
    /** Ordre de creation : c'est le tri par defaut, du plus recent au plus ancien. */
    case Recent = 'recent';
    case Title = 'title';
    case Status = 'status';
    case Category = 'category';
    case Date = 'date';
}
