<?php

declare(strict_types=1);

return [

    /*
     | Auteur des articles. Un seul auteur, donc pas de table : nom, qualite par langue, et route de la
     | page qui le presente. La qualite est celle de la signature de ses e-mails Festilaw ; elle n'est
     | JAMAIS presentee comme juriste sur ce site (c'est son autre statut, hors Festilaw).
     */
    'author' => [
        'name' => 'Laëtitia Redonet',
        'job_title' => [
            'en' => 'EU Market Access & Compliance Advisor',
            'fr' => 'Conseillère en accès au marché européen et conformité',
            'es' => 'Asesora de acceso al mercado europeo y cumplimiento normativo',
        ],
        'profile_route' => 'about',
    ],

    /*
     | Nombre d'articles par page sur le hub et les pages de categorie.
     */
    'per_page' => 12,

    /*
     | Slugs. Longueur au-dela de laquelle le slug genere est coupe (entre deux mots), et mots interdits
     | pour un article parce qu'ils servent de segment d'adresse (/blog/category/..., ?page=) dans l'une
     | des langues.
     */
    'slug' => [
        'max_length' => 75,
        'reserved' => ['category', 'categorie', 'categoria', 'page'],
    ],

    /*
     | Document de contenu (JSON de l'editeur). Les plafonds protegent le serveur : un document qui les
     | depasse est refuse. `image_sizes` est l'attribut sizes des images du texte (largeur de la colonne
     | de lecture), ajuste avec la mise en page des pages publiques.
     */
    'content' => [
        'max_bytes' => 200_000,
        'max_nodes' => 3_000,
        'max_depth' => 12,
        'image_sizes' => '(min-width: 800px) 760px, 100vw',
    ],

    /*
     | Images. Variantes WebP produites a l'envoi (jamais agrandies), JPEG de partage pour l'image
     | principale et l'image de partage (les reseaux sociaux lisent mal le WebP), limites d'envoi, et
     | delai apres lequel une image sans article est supprimee par la purge planifiee.
     |
     | `max_pixels` borne la memoire : le traitement (GD) consomme environ 19 Mo par megapixel (mesure du
     | 13/11/2026 : 228 Mo pour une photo de 12 megapixels). 25 megapixels = environ 480 Mo au plus fort.
     */
    'images' => [
        'disk' => 'public',
        'directory' => 'blog',
        'widths' => [480, 768, 1200, 1600],
        'webp_quality' => 82,
        'share' => [
            'width' => 1200,
            'height' => 630,
            'jpeg_quality' => 85,
        ],
        'max_upload_kilobytes' => 10_240,
        'max_pixels' => 25_000_000,
        'min_featured' => [
            'width' => 1200,
            'height' => 630,
        ],
        'orphan_retention_hours' => 24,
    ],

];
