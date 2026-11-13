<?php

declare(strict_types=1);

return [

    /*
     | Auteur des articles. Un seul auteur, donc pas de table : nom et qualite par langue. La qualite est
     | celle de la signature de ses e-mails Festilaw ; elle n'est JAMAIS presentee comme juriste sur ce site
     | (c'est son autre statut, hors Festilaw). La page d'un article affiche le nom seul ; la qualite ne sert
     | qu'aux donnees enrichies. Pas de lien : aucune page du site ne presente l'auteur.
     */
    'author' => [
        'name' => 'Laëtitia Redonet',
        'job_title' => [
            'en' => 'EU Market Access & Compliance Advisor',
            'fr' => 'Conseillère en accès au marché européen et conformité',
            'es' => 'Asesora de acceso al mercado europeo y cumplimiento normativo',
        ],
    ],

    /*
     | Adresses publiques. Segment de l'adresse d'une categorie dans chaque langue
     | (/blog/category/..., /fr/blog/categorie/..., /es/blog/categoria/...). Ces mots sont aussi des slugs
     | reserves pour un article (cf. slug.reserved).
     */
    'routes' => [
        'category_segment' => [
            'en' => 'category',
            'fr' => 'categorie',
            'es' => 'categoria',
        ],
    ],

    /*
     | Nombre d'articles par page sur le hub et les pages de categorie. La premiere page du hub en montre
     | un de plus : le dernier article y est affiche "a la une", au-dessus de la grille.
     */
    'per_page' => 12,

    /*
     | Nombre d'articles du bloc "derniers articles" de l'accueil, et d'articles associes en bas d'un article.
     */
    'home_latest' => 3,
    'related' => 3,

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
     | de lecture, marges de la page deduites : sans elles, un telephone chargerait une variante trop
     | grande), ajuste avec la mise en page des pages publiques. Il est ecrit dans le HTML enregistre
     | de chaque article : apres l'avoir change, relancer `php artisan festilaw:blog-rerender`.
     */
    'content' => [
        'max_bytes' => 200_000,
        'max_nodes' => 3_000,
        'max_depth' => 12,
        'image_sizes' => '(min-width: 1048px) 920px, (min-width: 400px) 90vw, calc(100vw - 40px)',
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
