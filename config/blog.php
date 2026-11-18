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
         | Liste des etapes executees : `editorial` (le vrai pipeline, SC6) ou `simulated` (etapes factices,
         | sans appel a un modele, REFUSEES en production, pour le developpement et les tests).
         */
        'pipeline' => env('BLOG_AI_PIPELINE', 'editorial'),

        /*
         | Fournisseur des agents du pipeline editorial, tel que le nomme `config/ai.php`. Tous les modeles
         | passent par OpenRouter ; un compte direct chez un editeur se brancherait ici, sans changer le code.
         */
        'provider' => 'openrouter',

        /*
         | Dossier des consignes des agents (fichiers .md versionnes, en anglais), lues par BlogPromptLibrary.
         */
        'prompts_directory' => resource_path('prompts/blog'),

        /*
         | Valeurs de depart des reglages du back-office (cle `blog.ai` de la table settings). Tant que
         | rien n'y est enregistre, ce sont elles qui s'appliquent. La generation automatique est eteinte
         | par defaut : rien ne part sans que quelqu'un l'ait allumee. `notification_email` nul = l'adresse
         | des notifications internes (festilaw.notification_email). Le plafond du mois est en cents de
         | dollar, la monnaie du fournisseur. Il ne bloque rien : il avertit a `monthly_warning_percent` puis
         | une fois depasse (decision de Micha du 17/11). Il n'y a plus de plafond par article (decision du
         | 18/11) : voir `safety_stop_percent`.
         |
         | Modeles : les moins chers qui savent faire chaque tache (prix au 17/11 : Haiku 5.5 a 0,10 $ et
         | 0,50 $ par million de jetons, GPT-5 Image Mini a environ 1,3 centime l'image). Au premier essai
         | reel, Sonnet 5.5 a chaque etape coutait plus d'un dollar l'article.
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
                'topic' => 'anthropic/claude-haiku-5.5',
                'research' => 'anthropic/claude-haiku-5.5',
                'writing' => 'anthropic/claude-haiku-5.5',
                'verification' => 'anthropic/claude-haiku-5.5',
                'translation' => 'anthropic/claude-haiku-5.5',
                'image' => 'openai/gpt-5-image-mini',
            ],
            'image_mode' => 'stock',
            'max_images' => 1,
            'article_words' => 1_000,
            'notification_email' => null,
            'transparency_notice' => ['en' => '', 'fr' => '', 'es' => ''],
        ],

        /*
         | Longueur du texte d'un article, en mots du texte anglais (decision de Micha du 17/11 : de 400 a
         | 2 000 mots, 1 000 par defaut). Les consignes demandent la cible a `asked_tolerance_percent` pres ;
         | la redaction accepte un ecart de `accepted_tolerance_percent`, et demande une correction au-dela.
         | Au premier article reel, les consignes demandaient 600 a 800 mots et le modele en a ecrit 522.
         | La longueur se choisit par paliers de `step_words` (decision de Micha du 18/11).
         */
        'article_length' => [
            'min_words' => 400,
            'max_words' => 2_000,
            'step_words' => 200,
            'asked_tolerance_percent' => 10,
            'accepted_tolerance_percent' => 20,
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
         |
         | Deux mesures facultatives, pour le cout estime d'un article (generation 77 du 17/11) :
         | `price_margin_percent`, ce que le modele coute vraiment par rapport au catalogue (Haiku 5.5 au prix
         | du catalogue, DeepSeek V4.1 Flash 2,8 fois plus cher en ecriture chez Wafer), et `reasoning_tokens`,
         | la reflexion d'un modele qui ignore l'effort demande (DeepSeek, de 1 800 a 11 200 jetons par appel
         | aux generations 77 et 78, comptee jusqu'a 12 000).
         */
        'models' => [
            'anthropic/claude-opus-5.5' => ['name' => 'Claude Opus 5.5', 'provider' => 'Anthropic', 'input' => 400, 'output' => 2_000],
            'anthropic/claude-sonnet-5.5' => ['name' => 'Claude Sonnet 5.5', 'provider' => 'Anthropic', 'input' => 200, 'output' => 1_000],
            'anthropic/claude-haiku-5.5' => ['name' => 'Claude Haiku 5.5', 'provider' => 'Anthropic', 'input' => 10, 'output' => 50, 'price_margin_percent' => 100],
            'openai/gpt-6.1-sol' => ['name' => 'GPT-6.1 Sol', 'provider' => 'OpenAI', 'input' => 200, 'output' => 1_000],
            'openai/gpt-6.1-terra' => ['name' => 'GPT-6.1 Terra', 'provider' => 'OpenAI', 'input' => 100, 'output' => 500],
            'openai/gpt-6-luna' => ['name' => 'GPT-6 Luna', 'provider' => 'OpenAI', 'input' => 10, 'output' => 50],
            'google/gemini-4-carbon' => ['name' => 'Gemini 4 Carbon', 'provider' => 'Google', 'input' => 200, 'output' => 1_000],
            'google/gemini-4-hydrogen' => ['name' => 'Gemini 4 Hydrogen', 'provider' => 'Google', 'input' => 150, 'output' => 750],
            'google/gemini-3.1-flash-lite' => ['name' => 'Gemini 3.1 Flash Lite', 'provider' => 'Google', 'input' => 25, 'output' => 150],
            'x-ai/grok-4.7' => ['name' => 'Grok 4.7', 'provider' => 'SpaceXAI', 'input' => 200, 'output' => 600],
            'mistralai/mistral-large-4-0' => ['name' => 'Mistral Large 4', 'provider' => 'Mistral', 'input' => 70, 'output' => 210],
            'deepseek/deepseek-v4.1-flash' => ['name' => 'DeepSeek V4.1 Flash', 'provider' => 'DeepSeek', 'input' => 5, 'output' => 20, 'price_margin_percent' => 300, 'reasoning_tokens' => [1_800, 12_000]],
        ],

        /*
         | Modeles qui generent des images : les seuls proposes pour l'etape des images. Noms releves au
         | catalogue d'OpenRouter le 16/11/2026. Prix d'une image paysage mesures le 17/11 : GPT-5 Image Mini
         | environ 1,3 centime (1536 x 1024), Nano Banana 2 de 6,7 a 10 centimes. Gemini 3.1 Flash Lite Image
         | n'est pas propose : OpenRouter refuse le reglage de resolution que le SDK lui envoie.
         |
         | `reserve_microusd` : ce qu'on reserve sur le plafond par article avant de demander une image (une
         | image ne part que si ce montant tient dans ce qui reste). Mesure du 17/11 doublee pour GPT-5 Image
         | Mini et Nano Banana 2 ; pour les trois autres, deduit du prix public d'un jeton d'image (17/11)
         | rapporte a ces deux mesures.
         */
        'image_models' => [
            'openai/gpt-5-image-mini' => ['name' => 'GPT-5 Image Mini', 'provider' => 'OpenAI', 'reserve_microusd' => 30_000],
            'google/gemini-3.1-flash-image' => ['name' => 'Nano Banana 2', 'provider' => 'Google', 'reserve_microusd' => 120_000],
            'google/gemini-nano-banana-2.1' => ['name' => 'Nano Banana 2.1', 'provider' => 'Google', 'reserve_microusd' => 60_000],
            'google/gemini-3-pro-image' => ['name' => 'Nano Banana Pro', 'provider' => 'Google', 'reserve_microusd' => 240_000],
            'openai/gpt-5.4-image-2' => ['name' => 'GPT-5.4 Image 2', 'provider' => 'OpenAI', 'reserve_microusd' => 60_000],
        ],

        /*
         | Recherche web des agents (outil `openrouter:web_search`). Le moteur Exa est impose : la recherche
         | native d'Anthropic, choisie sinon pour Haiku, rend des pages dont la taille ne se regle pas (47 000
         | jetons pour deux recherches le 17/11). Exa rend des extraits, au plus `max_results` par recherche
         | et `max_characters` par extrait, soit environ 2 000 jetons par recherche. Prix public d'Exa au
         | 17/11 : 0,007 $ par recherche, dix resultats compris (documentation d'OpenRouter). Le contenu des
         | extraits est facture en plus, comme du texte lu.
         */
        'web_search' => [
            'engine' => 'exa',
            'max_results' => 5,
            'max_characters' => 1_500,
            'price_microusd' => 7_000,
        ],

        /*
         | Reflexion du modele avant de repondre (`reasoning.effort` d'OpenRouter), par etape. Elle est
         | facturee comme du texte ecrit et compte dans la meme limite que la reponse : au 17/11, celle de
         | Haiku 5.5 (effort moyen par defaut) a coupe le dossier de faits. `none` pour les etapes qui ne font
         | que mettre des notes en forme, `low` ailleurs.
         */
        'reasoning' => [
            'default' => 'low',
            'topic' => 'none',
            'research' => 'none',
        ],

        /*
         | Arret de securite : avant chaque appel, son cout maximal (texte envoye, extraits de recherche,
         | longueur maximale de la reponse, prix des recherches) doit tenir dans ce qui reste. La part des
         | jetons est multipliee par cette marge : le prix d'un modele change selon le fournisseur qui repond
         | (le 17/11, DeepSeek V4.1 Flash facture 0,567 $ le million de jetons ecrits chez Wafer, pour 0,20 $
         | au catalogue).
         */
        'reservation_margin' => 3,

        /*
         | Le plafond du mois avertit (ecran et e-mail) quand la depense en atteint ce pourcentage, puis
         | une fois qu'elle le depasse. Chaque e-mail part une fois par mois.
         */
        'monthly_warning_percent' => 80,

        /*
         | Arret de securite d'une generation, en pourcentage du haut de son cout annonce au lancement
         | (decision de Micha du 18/11). Rien ne refuse plus un lancement : l'estimation dit ce que l'article
         | coutera, et la generation ne s'arrete que si son cout allait depasser ce seuil, contre une derive
         | grave (« pas une estimation a 30 centimes qui coute 3 dollars »). Le plafond par article, qu'elle
         | remplace, bloquait sans proteger : une image a coute 0,0498 $ pour une reserve de 0,03 $.
         */
        'safety_stop_percent' => 200,

        /*
         | Sources affichees sous un article (decision de Micha du 18/11) : les trois plus credibles, au plus
         | cinq quand le redacteur dit pourquoi une quatrieme ou une cinquieme est necessaire. Les articles
         | 77 a 80 en affichaient de 5 a 13. La recherche, elle, n'est pas plafonnee.
         */
        'displayed_sources' => [
            'max' => 3,
            'max_justified' => 5,
        ],

        /*
         | Cout estime d'un article (BlogGenerationCostForecaster), affiche avant le lancement. Profil de
         | chaque etape, par appel, cale sur la generation 77 du 17/11 (journal « Blog AI call. », brouillon
         | 97 de 522 mots), puis sur la 78 du 18/11 (brouillon 98, 929 mots rediges) : jetons lus (fixes, et
         | par millier de mots du texte), jetons visibles ecrits, reflexion basse et haute, nombre d'appels
         | bas et haut (correction, second passage ; les nouveaux essais, des incidents, ne comptent pas),
         | recherches web. La reflexion haute de la verification vient de la 78 (5 200 jetons, Haiku 5.5
         | compris). Les jetons lus de la redaction et de la verification comptent la fiche des regles de
         | l'UE (`resources/prompts/blog/eu-product-rules.md`, environ 1 700 jetons), ceux des versions
         | francaise et espagnole les chartes de presse du 18/11 (environ 1 900 et 1 600 jetons de plus).
         | Un appel apres le premier relit en plus la reponse precedente. La reflexion de chaque etape est celle d'un modele qui respecte l'effort
         | demande (Haiku 5.5) ; un modele qui l'ignore porte la sienne au catalogue (`reasoning_tokens`).
         | Les jetons sont comptes au prix mesure du modele (`price_margin_percent` du catalogue) ; un modele
         | jamais mesure compte au catalogue en bas et a `default_price_margin_percent` en haut.
         */
        'forecast' => [
            'default_price_margin_percent' => 150,
            'steps' => [
                'topic_scan' => ['setting' => 'topic', 'input' => 9_500, 'output' => 1_400, 'reasoning' => [200, 1_000], 'calls' => [1, 1], 'searches' => 2],
                'topic' => ['setting' => 'topic', 'input' => 7_900, 'output' => 500, 'reasoning' => [0, 0], 'calls' => [1, 1]],
                'research_notes' => ['setting' => 'research', 'input' => 22_800, 'output' => 2_800, 'reasoning' => [250, 1_000], 'calls' => [1, 1], 'searches' => 3],
                'research' => ['setting' => 'research', 'input' => 8_500, 'output' => 2_650, 'reasoning' => [0, 0], 'calls' => [1, 1]],
                'writing' => ['setting' => 'writing', 'input' => 11_400, 'output_per_1000_words' => 2_100, 'reasoning' => [1_000, 3_000], 'calls' => [1, 2]],
                'verification' => ['setting' => 'verification', 'input' => 8_700, 'input_per_1000_words' => 2_100, 'output_per_1000_words' => 3_100, 'reasoning' => [1_500, 5_500], 'calls' => [1, 2]],
                'style' => ['setting' => 'writing', 'input' => 5_300, 'input_per_1000_words' => 2_100, 'output_per_1000_words' => 1_750, 'reasoning' => [1_000, 3_000], 'calls' => [1, 2]],
                'translation_fr' => ['setting' => 'translation', 'input' => 7_400, 'input_per_1000_words' => 2_100, 'output_per_1000_words' => 2_700, 'reasoning' => [1_000, 3_000], 'calls' => [1, 3]],
                'translation_es' => ['setting' => 'translation', 'input' => 7_100, 'input_per_1000_words' => 2_100, 'output_per_1000_words' => 2_400, 'reasoning' => [1_000, 3_000], 'calls' => [1, 3]],
            ],
        ],

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
         | Relecture du style : au-dela de ce nombre de phrases qui portent encore un signe de texte genere
         | (BlogWritingSignalsInspector, liste signals-en.md), un second passage, cible sur ces phrases, est
         | demande au modele. Un seul. Au premier essai reel (brouillon 96), 13 phrases sur 55 en portaient.
         | Zero : une seule phrase suffit, car ce passage coute environ 0,2 centime avec Haiku 5.5.
         */
        'style' => [
            'max_flagged_sentences' => 0,
        ],

        /*
         | Generation automatique : heure (UTC) du controle quotidien « un lot est-il du ? », et nombre
         | d'echecs consecutifs qui declenche l'alerte au prestataire technique.
         */
        'schedule_time' => '06:00',
        'consecutive_failures_alert' => 3,

        /*
         | Banc d'essai des modeles (festilaw:blog-bench, SC7, cahier des charges § 13). Il rejoue le pipeline sur
         | des sujets fixes et plusieurs configurations de modeles, sans rien creer dans le site. Le sujet et la
         | recherche sont faits une fois par sujet avec `shared_models`, puis partages : seule l'ecriture change
         | d'une configuration a l'autre. Une image est generee par sujet, d'apres le premier article ecrit.
         | Sujets et configurations choisis par Micha le 18/11 : trois de fond et deux d'actualite, au moins un
         | sur une plateforme et un sur un type de produit. Chaque article s'arrete par securite, comme une vraie
         | generation, au double du haut annonce pour sa configuration. Les resultats vont dans
         | storage/app/private/`directory`.
         */
        'bench' => [
            'directory' => 'blog-bench',
            'article_words' => 1_000,
            'shared_models' => [
                'topic' => 'anthropic/claude-haiku-5.5',
                'research' => 'anthropic/claude-haiku-5.5',
            ],
            'image_model' => 'openai/gpt-5-image-mini',
            'topics' => [
                'responsible-person' => ['kind' => 'evergreen', 'topic' => 'Who can be the EU responsible person for a seller established outside the EU'],
                'amazon-listing' => ['kind' => 'evergreen', 'topic' => 'The product safety information an Amazon listing must show to EU buyers'],
                'toys' => ['kind' => 'evergreen', 'topic' => 'Selling toys into the EU from outside the EU: CE marking, warnings and contact details'],
                'marketplace-controls' => ['kind' => 'news', 'topic' => 'The 2026 coordinated controls of online marketplaces by EU product safety authorities, and what they found'],
                'product-liability' => ['kind' => 'news', 'topic' => 'The new EU product liability rules that apply from 9 December 2026'],
            ],
            'configurations' => [
                'current' => [
                    'name' => 'Haiku 5.5 + DeepSeek V4.1 Flash',
                    'models' => ['writing' => 'deepseek/deepseek-v4.1-flash', 'verification' => 'anthropic/claude-haiku-5.5', 'translation' => 'deepseek/deepseek-v4.1-flash'],
                ],
                'haiku' => [
                    'name' => 'Haiku 5.5',
                    'models' => ['writing' => 'anthropic/claude-haiku-5.5', 'verification' => 'anthropic/claude-haiku-5.5', 'translation' => 'anthropic/claude-haiku-5.5'],
                ],
                'luna' => [
                    'name' => 'Haiku 5.5 + GPT-6 Luna',
                    'models' => ['writing' => 'openai/gpt-6-luna', 'verification' => 'anthropic/claude-haiku-5.5', 'translation' => 'openai/gpt-6-luna'],
                ],
                'sonnet' => [
                    'name' => 'Haiku 5.5 + Sonnet 5.5',
                    'models' => ['writing' => 'anthropic/claude-sonnet-5.5', 'verification' => 'anthropic/claude-haiku-5.5', 'translation' => 'anthropic/claude-sonnet-5.5'],
                ],
            ],
        ],

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
