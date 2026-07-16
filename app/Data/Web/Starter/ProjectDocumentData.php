<?php

declare(strict_types=1);

namespace App\Data\Web\Starter;

/**
 * View-model d'un document telechargeable de l'espace "mon projet" : uniquement ce que la vue affiche
 * (libelle, nom de fichier, URL de telechargement portee par le token), sans exposer le modele Eloquent.
 */
final readonly class ProjectDocumentData
{
    public function __construct(
        public string $label,
        public string $filename,
        public string $downloadUrl,
    ) {}
}
