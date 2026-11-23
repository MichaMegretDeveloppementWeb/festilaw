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
         | Modeles : ceux que les essais des 18 et 19/11 recommandent (SC7, docs/comparaison-modeles.md du chantier).
         | GPT-5.4 Image 2 pour les images generees, retenu par Micha a l'essai d'images du 19/11 (C9) : le moins
         | cher, le plus rapide, et le seul qui ne dessine pas de faux texte.
         | Haiku 5.5 pour le sujet. GPT-6 Luna pour la recherche, ses dossiers etant plus riches et plus precis
         | que ceux de Haiku sur 3 paires sur 5 (1 pour Haiku). Luna aussi pour la redaction et les versions :
         | trois a quatre fois plus rapide que DeepSeek V4.1 Flash pour un cout voisin, au prix d'une langue un
         | peu moins vivante (choix de Micha). GPT-6.1 Terra pour la verification et le controle des versions :
         | il attrapait 10 erreurs connues sur 11, contre 1 pour Haiku. Au niveau Moyen, un article d'environ
         | 1 000 mots coute de 0,22 a 0,26 $, recherches, preuves et traductions comprises (decision du 20/11).
         */
        'defaults' => [
            'enabled' => false,
            'period_count' => 1,
            'period_unit' => 'week',
            'articles_per_run' => 1,
            'pending_drafts_cap' => 3,
            'monthly_spend_cap_cents' => 1_000,
            'models' => [
                'topic' => 'anthropic/claude-haiku-5.5',
                'research' => 'openai/gpt-6-luna',
                'writing' => 'openai/gpt-6-luna',
                'verification' => 'openai/gpt-6.1-terra',
                'translation' => 'openai/gpt-6-luna',
                'image' => 'openai/gpt-5.4-image-2',
            ],
            'image_mode' => 'stock',
            'max_images' => 1,
            'article_words' => 1_000,
            'notification_email' => null,
            'effort' => 'medium',
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
         | catalogue d'OpenRouter le 16/11/2026 (DeepSeek arrondi au cent). Depuis le 23/11, ce sont des prix de
         | SECOURS : la verification du matin releve le prix du jour de chaque modele (BlogAiPriceTable), qui sert
         | au prix indicatif affiche a cote du modele, a l'estimation d'un article, a l'arret de securite et au
         | cout d'une etape quand le fournisseur ne renvoie pas le cout reel (marque « cout estime »). Ces prix-ci
         | ne servent que si le releve manque ou a plus d'une semaine, ou si un prix du jour est trop loin d'eux
         | pour etre cru (plus de 5 fois). La qualite de ces modeles n'est pas presumee : le banc d'essai tranche.
         |
         | Trois mesures facultatives, pour le cout estime d'un article, relevees sur les 17 articles du banc
         | d'essai du 18/11 (storage/app/private/blog-bench/2026-11-18-sc7 et journal « Blog AI call. ») :
         | - `price_margin_percent`, ce que le modele coute vraiment par rapport au catalogue : Haiku 5.5,
         |   Sonnet 5.5 et DeepSeek V4.1 Flash au prix du catalogue, GPT-6 Luna 14 % au-dessus, GPT-6.1 Terra
         |   20 % en dessous (77 a 84 % du catalogue a la verification et au controle des versions, etalonnage
         |   du 19/11). DeepSeek coutait 2,8 fois son catalogue chez Wafer le 17/11 ; le routage au meilleur
         |   prix l'a ramene au catalogue ;
         | - `token_factor_percent`, combien de jetons le modele compte pour un meme texte, par rapport a
         |   DeepSeek et a GPT-6 Luna : environ une fois et demie plus pour Anthropic, en lecture comme en
         |   ecriture ;
         | - `reasoning_tokens`, la reflexion par appel d'un modele mesure : une paire pour un modele qui ignore
         |   l'effort demande (DeepSeek de 2 200 a 8 500 jetons, Sonnet aucune), une paire par effort pour un
         |   modele qui le suit a sa facon, et pour un effort une paire par etape quand elles different
         |   (`default`, puis l'etape). Mesures de l'etalonnage du 19/11 (generations 89, 91, 92 et 93) : Luna
         |   presque aucune en `low`, environ 2 000 jetons pour rediger en `medium` et 9 000 en `high`, moins
         |   de 2 300 par appel pour les autres etapes ; Terra environ 3 000 pour verifier en `high`. Etalonnage
         |   du 20/11 (generations 96 a 98, nouveaux niveaux) : Luna redige avec 4 000 a 9 000 jetons en `high`,
         |   cherche les preuves avec 7 000 a 8 500 (10 700 pour quinze recherches) et les sources avec 4 000 a
         |   6 500 ; Terra controle chaque version avec 1 000 a 2 000. Terra a coute ce jour-la de 85 a 112 % de
         |   son catalogue : sa marge reste celle du 19/11, et ses fourchettes de reflexion couvrent l'ecart.
         | `lowest_effort` : le plus petit effort qu'un modele accepte, quand il pourrait refuser `none`
         | (DeepSeek, par prudence : rien ne dit qu'il l'accepte).
         */
        'models' => [
            'anthropic/claude-opus-5.5' => ['name' => 'Claude Opus 5.5', 'provider' => 'Anthropic', 'input' => 400, 'output' => 2_000, 'token_factor_percent' => 145],
            'anthropic/claude-sonnet-5.5' => ['name' => 'Claude Sonnet 5.5', 'provider' => 'Anthropic', 'input' => 200, 'output' => 1_000, 'price_margin_percent' => 100, 'token_factor_percent' => 145, 'reasoning_tokens' => [0, 0]],
            'anthropic/claude-haiku-5.5' => ['name' => 'Claude Haiku 5.5', 'provider' => 'Anthropic', 'input' => 10, 'output' => 50, 'price_margin_percent' => 100, 'token_factor_percent' => 145],
            'openai/gpt-6.1-sol' => ['name' => 'GPT-6.1 Sol', 'provider' => 'OpenAI', 'input' => 200, 'output' => 1_000],
            'openai/gpt-6.1-terra' => ['name' => 'GPT-6.1 Terra', 'provider' => 'OpenAI', 'input' => 100, 'output' => 500, 'price_margin_percent' => 80, 'reasoning_tokens' => ['low' => [30, 200], 'medium' => [250, 750], 'high' => ['default' => [250, 1_700], 'verification' => [1_000, 3_100], 'version_check_fr' => [1_000, 2_200], 'version_check_es' => [1_000, 2_200]]]],
            'openai/gpt-6-luna' => ['name' => 'GPT-6 Luna', 'provider' => 'OpenAI', 'input' => 10, 'output' => 50, 'price_margin_percent' => 114, 'reasoning_tokens' => ['low' => [0, 300], 'medium' => ['default' => [250, 900], 'writing' => [1_900, 2_300], 'research_notes' => [650, 1_000], 'fact_search' => [1_500, 4_000]], 'high' => ['default' => [700, 4_000], 'writing' => [4_000, 10_000], 'research_notes' => [3_500, 7_000], 'fact_search' => [4_000, 11_000]]]],
            'google/gemini-4-carbon' => ['name' => 'Gemini 4 Carbon', 'provider' => 'Google', 'input' => 200, 'output' => 1_000],
            'google/gemini-4-hydrogen' => ['name' => 'Gemini 4 Hydrogen', 'provider' => 'Google', 'input' => 150, 'output' => 750],
            'google/gemini-3.1-flash-lite' => ['name' => 'Gemini 3.1 Flash Lite', 'provider' => 'Google', 'input' => 25, 'output' => 150],
            'x-ai/grok-4.7' => ['name' => 'Grok 4.7', 'provider' => 'SpaceXAI', 'input' => 200, 'output' => 600],
            'mistralai/mistral-large-4-0' => ['name' => 'Mistral Large 4', 'provider' => 'Mistral', 'input' => 70, 'output' => 210],
            'deepseek/deepseek-v4.1-flash' => ['name' => 'DeepSeek V4.1 Flash', 'provider' => 'DeepSeek', 'input' => 5, 'output' => 20, 'price_margin_percent' => 100, 'reasoning_tokens' => [2_200, 8_500], 'lowest_effort' => 'low'],
        ],

        /*
         | Modeles qui generent des images : les seuls proposes pour l'etape des images. Noms releves au
         | catalogue d'OpenRouter le 16/11/2026. Gemini 3.1 Flash Lite Image n'est pas propose : OpenRouter
         | refuse le reglage de resolution que le SDK lui envoie.
         |
         | `price_microusd` : le prix d'une image paysage, bas et haut, tel qu'on l'a mesure. Il sert a
         | l'estimation. GPT-5.4 Image 2 a coute de 0,0053 a 0,0109 $ et Nano Banana 2.1 de 0,0604 a 0,0640 $ sur les memes prompts
         | (banc d'images du 19/11, `festilaw:blog-bench-images`).
         | `reserve_microusd` : ce qu'il doit rester avant l'arret de securite pour demander une image. Pour
         | Nano Banana 2, mesure du 17/11 (6,7 a 10 centimes) doublee. Pour Nano Banana 2.1, sa mesure du 19/11
         | arrondie au-dessus. Pour les deux autres, deduit du prix public d'un jeton d'image (17/11) rapporte
         | a ces mesures. Sans prix mesure, l'estimation compte la moitie de la reserve en bas et la reserve en
         | haut.
         */
        'image_models' => [
            'google/gemini-3.1-flash-image' => ['name' => 'Nano Banana 2', 'provider' => 'Google', 'reserve_microusd' => 120_000],
            'google/gemini-nano-banana-2.1' => ['name' => 'Nano Banana 2.1', 'provider' => 'Google', 'price_microusd' => [60_400, 64_000], 'reserve_microusd' => 80_000],
            'google/gemini-3-pro-image' => ['name' => 'Nano Banana Pro', 'provider' => 'Google', 'reserve_microusd' => 240_000],
            'openai/gpt-5.4-image-2' => ['name' => 'GPT-5.4 Image 2', 'provider' => 'OpenAI', 'price_microusd' => [5_300, 10_900], 'reserve_microusd' => 60_000],
        ],

        /*
         | Recherche web des agents (outil `openrouter:web_search`). Le moteur Exa est impose : la recherche
         | native d'Anthropic, choisie sinon pour Haiku, rend des pages dont la taille ne se regle pas (47 000
         | jetons pour deux recherches le 17/11). Exa rend des extraits, au plus `max_results` par recherche
         | et `max_characters` par extrait, soit environ 2 000 jetons par recherche. Prix public d'Exa au
         | 17/11 : 0,007 $ par recherche, dix resultats compris (documentation d'OpenRouter). Prix mesure le
         | 19/11 sur six generations : 6 470 a 6 580 millioniemes de dollar, d'ou `price_microusd`. Le contenu
         | des extraits est facture en plus, comme du texte lu.
         */
        /*
         | Controle des photos de la banque d'images (remarque de Micha du 20/11) : le modele du choix du sujet
         | regarde au plus ce nombre de photos par recherche, et ne garde que la premiere qui convient a
         | l'article, decrite telle qu'elle est. Environ 0,03 centime par photo regardee.
         */
        'stock_photo_checks' => 4,

        'web_search' => [
            'engine' => 'exa',
            'max_results' => 5,
            'max_characters' => 1_500,
            'price_microusd' => 6_500,
        ],

        /*
         | Niveaux d'effort d'une generation (demande de Micha du 19/11, chantier C8) : faible, moyen, eleve,
         | moyen par defaut. Chacun fixe, par etape, la reflexion demandee au modele (`reasoning.effort`
         | d'OpenRouter, facturee comme du texte ecrit et comptee dans la meme limite que la reponse) et le
         | nombre de recherches web, a 0,65 centime l'une. Regle de Micha (19/11, apres l'etalonnage) : baisser
         | d'un niveau baisse a la fois la reflexion et la profondeur des recherches, et monter les monte.
         | Le 20/11, Micha a juge le Moyen trop timore (7,7 a 9 centimes) : il doit vraiment travailler plus,
         | pour un haut de fourchette vers 0,30 $, l'Eleve vers 0,40 $, et le Faible monte un peu. L'argent va
         | aux recherches et a la recherche des preuves (`fact_search`), ou il sert le plus a un article de
         | conformite : avec GPT-6 Luna, lire et reflechir coutent tres peu.
         | - Faible : 2 recherches de pistes, 3 de sources et 3 de preuves, reflexion `medium`, `low` pour le
         |   choix du sujet et le dossier, qui ne font que mettre des notes en forme. C'etait le Moyen du 19/11,
         |   plus les preuves. Sans aucune reflexion, GPT-6 Luna rendait un JSON illisible a la relecture du
         |   style (generations 88 et 90 du 19/11) : aucun niveau ne descend a `none`.
         | - Moyen : 4, 10 et 8 recherches, reflexion `high`, `medium` pour le choix du sujet et le dossier.
         | - Eleve : 6, 18 et 15 recherches, reflexion `high` partout.
         | Le controle des photos de la banque (`photo_check`) reste un cran en dessous, `high` excepte.
         | Ces niveaux sont lus par l'appel au modele, les etapes qui cherchent sur le web et l'estimation du
         | cout (BlogEffortLevels).
         */
        'effort' => [
            'levels' => [
                'low' => [
                    'reasoning' => ['default' => 'medium', 'topic' => 'low', 'research' => 'low', 'photo_check' => 'low'],
                    'searches' => ['topic_scan' => 2, 'research_notes' => 3, 'fact_search' => 3],
                ],
                'medium' => [
                    'reasoning' => ['default' => 'high', 'topic' => 'medium', 'research' => 'medium', 'photo_check' => 'medium'],
                    'searches' => ['topic_scan' => 4, 'research_notes' => 10, 'fact_search' => 8],
                ],
                'high' => [
                    'reasoning' => ['default' => 'high', 'photo_check' => 'medium'],
                    'searches' => ['topic_scan' => 6, 'research_notes' => 18, 'fact_search' => 15],
                ],
            ],
        ],

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
         | Plancher de l'arret de securite, en millioniemes de dollar (decision de Micha du 19/11) : 2 centimes. Un
         | appel a l'IA reserve d'avance le prix de la plus longue reponse permise, environ 1,5 centime pour une
         | traduction avec GPT-6 Luna, quelle que soit la longueur du texte. Le double du cout annonce d'un texte
         | court (0,47 centime) refusait donc toute traduction assistee courte, sans rien depenser. Un article
         | ordinaire n'est pas touche : son arret de securite est bien au-dessus.
         */
        'safety_stop_floor_microusd' => 20_000,

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
         | Cout estime d'un article (BlogGenerationCostForecaster), annonce avant le lancement et garde sur la
         | generation. Profil de chaque etape, par appel, cale sur les 17 articles du banc d'essai du 18/11
         | (quatre configurations, journal « Blog AI call. ») : jetons lus (fixes, et par millier de mots du
         | texte, et par recherche web), jetons visibles ecrits, reflexion basse et haute par effort demande,
         | nombre d'appels bas et haut (correction, second passage ; les nouveaux essais, des incidents, ne
         | comptent pas). Le nombre de recherches vient du niveau d'effort (`effort.levels`). Les jetons
         | sont comptes a la facon de DeepSeek et de GPT-6 Luna ; un modele d'Anthropic les multiplie par son
         | `token_factor_percent`. La relecture du style et les deux versions font toujours deux appels, car
         | le second passage part des qu'une phrase garde un signe de texte genere. Un appel apres le premier
         | relit en plus la reponse precedente. La reflexion de chaque etape est celle d'un modele qui respecte
         | l'effort demande (Haiku 5.5), par effort : `low` est mesure ; `medium` vaut 2,5 fois `low` et `high`
         | 4 fois (rapport des parts de sortie qu'OpenRouter donne a ces efforts), estimations prudentes pour un
         | modele que le banc n'a pas mesure ; `none` ne reflechit pas. Un modele mesure porte la sienne au
         | catalogue (`reasoning_tokens`). Les jetons sont comptes au prix mesure du modele
         | (`price_margin_percent`) ; un modele jamais mesure compte au catalogue en bas et a
         | `default_price_margin_percent` en haut. Avec ce profil, chacun des 17 articles du banc tombe dans
         | la fourchette de sa configuration au niveau Faible, et le haut ne depasse pas une fois et demie le
         | bas. Avec les modeles recommandes, chaque niveau d'effort encadre ce que l'etalonnage du 19/11 a
         | coute (BlogGenerationCostForecastTest), et depuis le 20/11 les generations 96 a 98 des nouveaux niveaux.
         | La recherche des preuves (`fact_search`) lit environ 8 000 jetons par recherche, ses extraits s'ajoutant
         | a chaque tour. La recherche de pistes (`topic_scan`), absente du banc ou les sujets etaient imposes,
         | garde la mesure de la generation 77 ramenee aux jetons de ce profil.
         | Les jetons lus par la recherche sur le web suivent le nombre de recherches : environ 13 000 pour
         | deux, 19 000 pour trois, 32 000 a 40 000 pour cinq (19/11).
         */
        'forecast' => [
            'default_price_margin_percent' => 150,
            'steps' => [
                'topic_scan' => ['setting' => 'topic', 'input' => 2_550, 'input_per_search' => 2_000, 'output' => 1_000, 'reasoning' => ['low' => [200, 1_000], 'medium' => [500, 2_500], 'high' => [800, 4_000]], 'calls' => [1, 1]],
                'topic' => ['setting' => 'topic', 'input' => 4_750, 'output' => 450, 'reasoning' => ['low' => [200, 1_000], 'medium' => [500, 2_500], 'high' => [800, 4_000]], 'calls' => [1, 1]],
                'research_notes' => ['setting' => 'research', 'input' => 1_500, 'input_per_search' => 6_000, 'output' => 1_800, 'reasoning' => ['low' => [250, 600], 'medium' => [625, 1_500], 'high' => [1_000, 2_400]], 'calls' => [1, 1]],
                'research' => ['setting' => 'research', 'input' => 6_000, 'output' => 1_800, 'reasoning' => ['low' => [250, 600], 'medium' => [625, 1_500], 'high' => [1_000, 2_400]], 'calls' => [1, 1]],
                'writing' => ['setting' => 'writing', 'input' => 11_400, 'output_per_1000_words' => 1_700, 'reasoning' => ['low' => [1_200, 3_200], 'medium' => [3_000, 8_000], 'high' => [4_800, 12_800]], 'calls' => [1, 2]],
                'fact_search' => ['setting' => 'research', 'input' => 3_000, 'input_per_1000_words' => 2_000, 'input_per_search' => 8_000, 'output' => 1_200, 'reasoning' => ['low' => [250, 600], 'medium' => [625, 1_500], 'high' => [1_000, 2_400]], 'calls' => [1, 1]],
                'verification' => ['setting' => 'verification', 'input' => 6_700, 'input_per_1000_words' => 1_500, 'output_per_1000_words' => 1_680, 'reasoning' => ['low' => [300, 4_400], 'medium' => [750, 11_000], 'high' => [1_200, 17_600]], 'calls' => [1, 2]],
                'style' => ['setting' => 'writing', 'input' => 5_700, 'input_per_1000_words' => 1_500, 'output_per_1000_words' => 1_350, 'reasoning' => ['low' => [400, 1_400], 'medium' => [1_000, 3_500], 'high' => [1_600, 5_600]], 'calls' => [2, 2]],
                'translation_fr' => ['setting' => 'translation', 'input' => 8_300, 'input_per_1000_words' => 1_500, 'output_per_1000_words' => 1_800, 'reasoning' => ['low' => [1_000, 5_000], 'medium' => [2_500, 12_500], 'high' => [4_000, 20_000]], 'calls' => [2, 2]],
                'version_check_fr' => ['setting' => 'verification', 'input' => 2_200, 'input_per_1000_words' => 3_300, 'output' => 1_000, 'reasoning' => ['low' => [300, 2_000], 'medium' => [750, 5_000], 'high' => [1_200, 8_000]], 'calls' => [1, 1]],
                'version_check_es' => ['setting' => 'verification', 'input' => 2_200, 'input_per_1000_words' => 3_300, 'output' => 1_000, 'reasoning' => ['low' => [300, 2_000], 'medium' => [750, 5_000], 'high' => [1_200, 8_000]], 'calls' => [1, 1]],
                'photo_check' => ['setting' => 'topic', 'input' => 1_500, 'output' => 150, 'reasoning' => ['low' => [0, 300], 'medium' => [0, 750]], 'calls' => [1, 4], 'stock_photos' => true],
                'translation_es' => ['setting' => 'translation', 'input' => 7_700, 'input_per_1000_words' => 1_500, 'output_per_1000_words' => 1_750, 'reasoning' => ['low' => [1_000, 5_000], 'medium' => [2_500, 12_500], 'high' => [4_000, 20_000]], 'calls' => [2, 2]],
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
         | Apres une panne passagere du fournisseur (429, 5xx, coupure), l'attente est plus longue
         | (`transient_wait_seconds`, avant le 2e puis le 3e essai) et le worker rend la generation au lieu de
         | dormir. Une generation automatique encore en echec pour cette raison est relancee une seule fois,
         | `auto_relaunch_after_minutes` plus tard (controle du 20/11) : une panne de quelques minutes a l'heure
         | du lot ne coute plus l'article de la periode.
         */
        'step' => [
            'max_attempts' => 3,
            'retry_wait_seconds' => 5,
            'lock_margin_seconds' => 60,
            'transient_wait_seconds' => [120, 600],
            'auto_relaunch_after_minutes' => 60,
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
         | Generation automatique : nombre de generations d'articles terminees sans brouillon, d'affilee, qui
         | declenche l'alerte au prestataire technique. Le controle « un lot est-il du ? » passe chaque heure de
         | 6 h a 20 h (UTC, routes/console.php).
         */
        'consecutive_failures_alert' => 3,

        /*
         | Verification quotidienne des services de la generation (festilaw:blog-health-check, controle du
         | 20/11), sans aucun appel a un modele. Chaque probleme est journalise en erreur, donc repris par le
         | rapport d'erreurs du matin tant qu'il dure. Elle releve aussi le prix du jour de chaque modele de texte
         | du catalogue, que l'estimation emploie a la place des prix `input` et `output` de `models` (decision
         | du 23/11) ; ces derniers ne servent plus que de secours.
         | - `prices_valid_days` : au-dela, les prix releves sont trop vieux, l'estimation revient au tableau ;
         | - `key_remaining_percent` : sous ce reste de la limite de la cle OpenRouter, la limite approche ;
         | - `expiry_warning_days` : un modele qu'OpenRouter retire dans moins de ces jours est signale ;
         | - `stalled_after_minutes` : une generation en attente ou en cours qui n'a pas bouge depuis ce temps.
         */
        'health' => [
            'prices_valid_days' => 7,
            'key_remaining_percent' => 20,
            'expiry_warning_days' => 30,
            'stalled_after_minutes' => 120,
        ],

        /*
         | La reference des regles europeennes (resources/prompts/blog/eu-product-rules.md) prime sur le dossier de
         | recherche. Elle vieillit avec le droit : passee la date de relecture, la verification quotidienne le
         | signale au prestataire technique et le tableau de bord a la cliente, jusqu'a sa relecture. Les deux
         | dates changent ensemble, avec la ligne de date du fichier.
         */
        'rules_reference' => [
            'checked_on' => '2026-11-19',
            'review_by' => '2027-11-19',
        ],

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
            // Plafond d'une verification rejouee par festilaw:blog-bench-verify (reserve de GPT-6.1 Terra : environ 0,33 $).
            'verification_cap_cents' => 50,
            'article_words' => 1_000,
            // Les modeles du site depuis le 19/11 ; --research-model en essaie un autre pour la recherche.
            'shared_models' => [
                'topic' => 'anthropic/claude-haiku-5.5',
                'research' => 'openai/gpt-6-luna',
            ],
            'image_model' => 'openai/gpt-5.4-image-2',
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
                // Configuration retenue apres le banc du 18/11 : DeepSeek ecrit, GPT-6.1 Terra verifie et controle les versions.
                'recommended' => [
                    'name' => 'Haiku 5.5 + DeepSeek V4.1 Flash + GPT-6.1 Terra',
                    'models' => ['writing' => 'deepseek/deepseek-v4.1-flash', 'verification' => 'openai/gpt-6.1-terra', 'translation' => 'deepseek/deepseek-v4.1-flash'],
                ],
                // Essai du 19/11 : Luna, trois fois plus rapide que DeepSeek, verifie par Terra.
                'luna-terra' => [
                    'name' => 'Haiku 5.5 + GPT-6 Luna + GPT-6.1 Terra',
                    'models' => ['writing' => 'openai/gpt-6-luna', 'verification' => 'openai/gpt-6.1-terra', 'translation' => 'openai/gpt-6-luna'],
                ],
                // Essai du 19/11 : un redacteur bon marche jamais essaye, Mistral etant reconnu pour son francais.
                'mistral-terra' => [
                    'name' => 'Haiku 5.5 + Mistral Large 4 + GPT-6.1 Terra',
                    'models' => ['writing' => 'mistralai/mistral-large-4-0', 'verification' => 'openai/gpt-6.1-terra', 'translation' => 'mistralai/mistral-large-4-0'],
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
