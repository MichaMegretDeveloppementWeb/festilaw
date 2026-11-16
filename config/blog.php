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

        /*
         | Largeurs reduites d'une image du texte, en pixels (largeur maximale a l'ecran). Une image sans
         | largeur prend toute la colonne. Memes valeurs dans resources/css/web/blog/prose.css et dans
         | resources/css/admin/blog-editor.css : les changer aux trois endroits, puis relancer
         | `php artisan festilaw:blog-rerender`.
         */
        'image_widths' => ['medium' => 560, 'small' => 360],
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
        // Source non decoupee d'une image du texte, gardee pour un recadrage ulterieur : largeur maximale.
        'source_width' => 2_400,
        // Plus petite partie d'image qu'un recadrage peut garder, en pixels.
        'min_crop' => [
            'width' => 200,
            'height' => 120,
        ],
        'max_upload_kilobytes' => 10_240,
        'max_pixels' => 25_000_000,
        'min_featured' => [
            'width' => 1200,
            'height' => 630,
        ],
        'orphan_retention_hours' => 24,
    ],

    /*
     | Generation d'articles par IA. Une generation est une ligne de blog_generations, traitee etape par
     | etape par un worker que le planificateur relance chaque minute (routes/console.php).
     */
    'ai' => [

        /*
         | Liste des etapes executees : `simulated` (etapes factices, sans appel a un modele, REFUSEES en
         | production) ou `editorial` (le vrai pipeline). Le pipeline editorial n'existe pas encore : il
         | arrive avec le sous-chantier suivant, qui changera cette valeur par defaut.
         */
        'pipeline' => env('BLOG_AI_PIPELINE', 'simulated'),

        /*
         | Valeurs de depart des reglages du back-office (cle `blog.ai` de la table settings). Tant que
         | rien n'y est enregistre, ce sont elles qui s'appliquent. La generation automatique est eteinte
         | par defaut : rien ne part sans que quelqu'un l'ait allumee. `notification_email` nul = l'adresse
         | des notifications internes (festilaw.notification_email). Le plafond de depense est en cents de
         | dollar : le fournisseur facture en dollars.
         */
        'defaults' => [
            'enabled' => false,
            'period_count' => 1,
            'period_unit' => 'week',
            'articles_per_run' => 1,
            'pending_drafts_cap' => 3,
            'monthly_spend_cap_cents' => 1_000,
            'priority_topics' => [],
            'avoided_topics' => [],
            'models' => [
                'topic' => 'anthropic/claude-sonnet-5.5',
                'research' => 'anthropic/claude-sonnet-5.5',
                'writing' => 'anthropic/claude-sonnet-5.5',
                'verification' => 'anthropic/claude-sonnet-5.5',
                'translation' => 'anthropic/claude-sonnet-5.5',
                'image' => 'google/gemini-3.1-flash-image',
            ],
            'image_mode' => 'stock',
            'max_images' => 1,
            'notification_email' => null,
            'transparency_notice' => ['en' => '', 'fr' => '', 'es' => ''],
        ],

        /*
         | Modeles de texte proposes au back-office : la liste est FERMEE, l'ecran des reglages n'accepte
         | aucun autre identifiant (la personne choisit, elle ne saisit pas). `name` et `provider` sont le
         | nom commercial et l'editeur, tels que le catalogue d'OpenRouter les ecrit.
         |
         | `input` et `output` : prix en CENTS de dollar par million de jetons lus et ecrits, releves au
         | catalogue d'OpenRouter le 16/11/2026 (DeepSeek arrondi au cent). Ils servent a deux choses : le
         | prix indicatif affiche a cote du modele, et l'ESTIMATION d'un cout quand le fournisseur ne renvoie
         | pas le cout reel (l'etape est alors marquee « cout estime »). La qualite de ces modeles n'est pas
         | presumee : le banc d'essai tranche, et resserrera cette liste.
         */
        'models' => [
            'anthropic/claude-opus-5.5' => ['name' => 'Claude Opus 5.5', 'provider' => 'Anthropic', 'input' => 400, 'output' => 2_000],
            'anthropic/claude-sonnet-5.5' => ['name' => 'Claude Sonnet 5.5', 'provider' => 'Anthropic', 'input' => 200, 'output' => 1_000],
            'anthropic/claude-haiku-5.5' => ['name' => 'Claude Haiku 5.5', 'provider' => 'Anthropic', 'input' => 10, 'output' => 50],
            'openai/gpt-6.1-sol' => ['name' => 'GPT-6.1 Sol', 'provider' => 'OpenAI', 'input' => 200, 'output' => 1_000],
            'openai/gpt-6.1-terra' => ['name' => 'GPT-6.1 Terra', 'provider' => 'OpenAI', 'input' => 100, 'output' => 500],
            'openai/gpt-6-luna' => ['name' => 'GPT-6 Luna', 'provider' => 'OpenAI', 'input' => 10, 'output' => 50],
            'google/gemini-4-carbon' => ['name' => 'Gemini 4 Carbon', 'provider' => 'Google', 'input' => 200, 'output' => 1_000],
            'google/gemini-4-hydrogen' => ['name' => 'Gemini 4 Hydrogen', 'provider' => 'Google', 'input' => 150, 'output' => 750],
            'google/gemini-3.1-flash-lite' => ['name' => 'Gemini 3.1 Flash Lite', 'provider' => 'Google', 'input' => 25, 'output' => 150],
            'x-ai/grok-4.7' => ['name' => 'Grok 4.7', 'provider' => 'SpaceXAI', 'input' => 200, 'output' => 600],
            'mistralai/mistral-large-4-0' => ['name' => 'Mistral Large 4', 'provider' => 'Mistral', 'input' => 70, 'output' => 210],
            'deepseek/deepseek-v4.1-flash' => ['name' => 'DeepSeek V4.1 Flash', 'provider' => 'DeepSeek', 'input' => 5, 'output' => 20],
        ],

        /*
         | Modeles qui generent des images : les seuls proposes pour l'etape des images. Noms releves au
         | catalogue d'OpenRouter le 16/11/2026. Aucun prix : celui d'une image n'a pas ete releve.
         */
        'image_models' => [
            'google/gemini-3.1-flash-image' => ['name' => 'Nano Banana 2', 'provider' => 'Google'],
            'google/gemini-nano-banana-2.1' => ['name' => 'Nano Banana 2.1', 'provider' => 'Google'],
            'google/gemini-3-pro-image' => ['name' => 'Nano Banana Pro', 'provider' => 'Google'],
            'openai/gpt-5.4-image-2' => ['name' => 'GPT-5.4 Image 2', 'provider' => 'OpenAI'],
        ],

        /*
         | Cout estime d'une recherche web, en millioniemes de dollar (10 000 = 0,01 $). Unite a confirmer
         | par l'essai reel : ne sert, comme la table ci-dessus, qu'au cout de repli.
         */
        'web_search_microusd' => 10_000,

        /*
         | Worker. Il cherche une generation a traiter toutes les `poll_seconds`. A vide, il s'arrete des
         | qu'un worker plus recent a pris le relais (verifie toutes les `handoff_check_seconds`), ou au
         | bout de `idle_max_seconds` sans releve : le planificateur en relance un chaque minute, il y en a
         | donc toujours un a l'ecoute.
         |
         | Aucun processus long : passe `max_lifetime_seconds` de vie, un worker qui traite une generation la
         | rend apres l'etape en cours, et le worker suivant, deja a l'ecoute, la reprend. Une generation de
         | dix minutes passe ainsi par plusieurs workers courts ; le plus long processus dure cette valeur
         | plus une etape. L'hebergement ne dit nulle part combien de temps il tolere un processus.
         */
        'worker' => [
            'poll_seconds' => 2,
            'handoff_check_seconds' => 10,
            'idle_max_seconds' => 90,
            'max_lifetime_seconds' => 60,
        ],

        /*
         | Etapes. Nombre d'essais avant l'echec de la generation, attente entre deux essais, et marge
         | ajoutee au delai de l'etape pour la duree du verrou : passe ce delai, la generation est tenue
         | pour abandonnee par son worker et reprise par un autre.
         */
        'step' => [
            'max_attempts' => 3,
            'retry_wait_seconds' => 5,
            'lock_margin_seconds' => 60,
        ],

        /*
         | Generation automatique : heure (UTC) du controle quotidien « un lot est-il du ? », et nombre
         | d'echecs consecutifs qui declenche l'alerte au prestataire technique.
         */
        'schedule_time' => '06:00',
        'consecutive_failures_alert' => 3,

        /*
         | Etapes factices (pipeline `simulated`) : duree de chaque etape, cout fictif par etape, et etape
         | a faire echouer ou a faire s'arreter sans sujet, pour essayer la relance et l'abandon a l'ecran.
         */
        'simulation' => [
            'seconds_per_step' => (int) env('BLOG_AI_SIMULATION_SECONDS', 3),
            'cost_microusd_per_step' => 12_500,
            'fail_step' => env('BLOG_AI_SIMULATION_FAIL_STEP'),
            'stop_without_topic' => (bool) env('BLOG_AI_SIMULATION_NO_TOPIC', false),
        ],
    ],

];
