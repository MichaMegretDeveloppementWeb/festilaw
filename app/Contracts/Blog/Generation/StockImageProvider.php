<?php

declare(strict_types=1);

namespace App\Contracts\Blog\Generation;

use App\Data\Blog\Generation\StockImage;
use App\Exceptions\Blog\BlogEditorialException;

/**
 * Une banque d'images pour les articles generes (cahier des charges, § 11). Premiere implementation :
 * Pixabay. Une autre banque (Pexels) se brancherait derriere la meme interface.
 */
interface StockImageProvider
{
    /**
     * Photos horizontales, sans contenu adulte, assez grandes pour le fichier qu'on telechargera.
     *
     * @return list<StockImage>
     *
     * @throws BlogEditorialException quand la banque ne repond pas ou n'est pas configuree
     */
    public function search(string $query, int $minWidth, int $minHeight): array;

    /**
     * Telecharge la photo dans un fichier temporaire, que l'appelant supprime apres usage.
     *
     * @throws BlogEditorialException
     */
    public function download(StockImage $image): string;
}
