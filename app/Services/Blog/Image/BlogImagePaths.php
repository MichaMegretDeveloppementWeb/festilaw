<?php

declare(strict_types=1);

namespace App\Services\Blog\Image;

use App\Models\BlogImage;

/**
 * Chemins et adresses des fichiers d'une image du blog. Seul endroit qui connait la convention de
 * nommage : un dossier par image ({dossier}/{uuid}/), une variante WebP par largeur, un JPEG de
 * partage eventuel. Calcul pur : aucun acces au disque (c'est le role de BlogImageStorage).
 */
final readonly class BlogImagePaths
{
    public function __construct(private string $directory = 'blog') {}

    /** Dossier racine des images du blog sur le disque. */
    public function root(): string
    {
        return $this->directory;
    }

    public function directory(string $uuid): string
    {
        return "{$this->directory}/{$uuid}";
    }

    public function variant(string $uuid, string $basename, int $width): string
    {
        return "{$this->directory}/{$uuid}/{$basename}-{$width}.webp";
    }

    public function share(string $uuid, string $basename): string
    {
        return "{$this->directory}/{$uuid}/{$basename}-share.jpg";
    }

    public function variantOf(BlogImage $image, int $width): string
    {
        return $this->variant($image->uuid, $image->basename, $width);
    }

    public function shareOf(BlogImage $image): string
    {
        return $this->share($image->uuid, $image->basename);
    }

    /**
     * Adresse publique d'un fichier, relative a la racine du site (/storage/...) : elle reste valable
     * quel que soit le domaine, et le HTML enregistre ne depend pas de APP_URL.
     */
    public function url(string $path): string
    {
        return '/storage/'.$path;
    }
}
