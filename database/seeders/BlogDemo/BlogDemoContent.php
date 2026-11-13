<?php

declare(strict_types=1);

namespace Database\Seeders\BlogDemo;

/**
 * Textes des articles de demonstration du blog (BlogDemoSeeder). Ils servent a montrer la mise en page :
 * ils n'affirment rien de juridique et chaque article s'ouvre sur la mention "contenu de demonstration".
 * Les slugs sont ecrits ici pour que les adresses restent les memes d'une execution a l'autre.
 */
final class BlogDemoContent
{
    /**
     * @return array<string, array{name: array<string, string>, slug: array<string, string>, description: array<string, string>}>
     */
    public static function categories(): array
    {
        return [
            'basics' => [
                'name' => ['en' => 'GPSR basics', 'fr' => 'Comprendre le GPSR', 'es' => 'Entender el GPSR'],
                'slug' => ['en' => 'gpsr-basics', 'fr' => 'comprendre-le-gpsr', 'es' => 'entender-el-gpsr'],
                'description' => [
                    'en' => 'The essentials of EU product safety rules, explained for sellers outside the EU.',
                    'fr' => 'L\'essentiel des règles européennes de sécurité des produits, expliqué aux vendeurs hors UE.',
                    'es' => 'Lo esencial de las normas europeas de seguridad de los productos, explicado para vendedores de fuera de la UE.',
                ],
            ],
            'labelling' => [
                'name' => ['en' => 'Labelling and documents', 'fr' => 'Étiquetage et documents', 'es' => 'Etiquetado y documentos'],
                'slug' => ['en' => 'labelling-and-documents', 'fr' => 'etiquetage-et-documents', 'es' => 'etiquetado-y-documentos'],
                'description' => [
                    'en' => 'Labels, warnings and the documents that go with a product.',
                    'fr' => 'Étiquettes, avertissements et documents qui accompagnent un produit.',
                    'es' => 'Etiquetas, advertencias y documentos que acompañan a un producto.',
                ],
            ],
            'marketplaces' => [
                'name' => ['en' => 'Marketplaces', 'fr' => 'Places de marché', 'es' => 'Marketplaces'],
                'slug' => ['en' => 'marketplaces', 'fr' => 'places-de-marche', 'es' => 'marketplaces'],
                'description' => [
                    'en' => 'Selling to EU customers through marketplaces or your own shop.',
                    'fr' => 'Vendre aux clients européens sur une place de marché ou sur sa propre boutique.',
                    'es' => 'Vender a clientes europeos en un marketplace o en tu propia tienda.',
                ],
            ],
            'responsible-person' => [
                'name' => ['en' => 'EU Responsible Person', 'fr' => 'Personne Responsable dans l\'UE', 'es' => 'Persona Responsable en la UE'],
                'slug' => ['en' => 'eu-responsible-person', 'fr' => 'personne-responsable-ue', 'es' => 'persona-responsable-ue'],
                'description' => [
                    'en' => 'What an EU Responsible Person is for, and how to work with one.',
                    'fr' => 'À quoi sert une Personne Responsable dans l\'UE, et comment travailler avec elle.',
                    'es' => 'Para qué sirve una Persona Responsable en la UE y cómo trabajar con ella.',
                ],
            ],
            'news' => [
                'name' => ['en' => 'News', 'fr' => 'Actualités', 'es' => 'Novedades'],
                'slug' => ['en' => 'news', 'fr' => 'actualites', 'es' => 'novedades'],
                'description' => [
                    'en' => 'News from Festilaw.',
                    'fr' => 'Les nouvelles de Festilaw.',
                    'es' => 'Las novedades de Festilaw.',
                ],
            ],
        ];
    }

    /**
     * Du plus recent au plus ancien. `days_ago` date la publication ; `inline_image` ajoute une image
     * dans le texte.
     *
     * @return list<array{key: string, category: string, days_ago: int, inline_image?: bool, title: array<string, string>, slug: array<string, string>, excerpt: array<string, string>}>
     */
    public static function posts(): array
    {
        return [
            [
                'key' => 'gpsr-in-plain-language', 'category' => 'basics', 'days_ago' => 2, 'inline_image' => true,
                'title' => [
                    'en' => 'The GPSR in plain language: where to start',
                    'fr' => 'Le GPSR en clair : par où commencer',
                    'es' => 'El GPSR en pocas palabras: por dónde empezar',
                ],
                'slug' => ['en' => 'gpsr-in-plain-language', 'fr' => 'le-gpsr-en-clair', 'es' => 'el-gpsr-en-pocas-palabras'],
                'excerpt' => [
                    'en' => 'A first tour of the vocabulary and the questions to ask yourself before selling a product to customers in the European Union.',
                    'fr' => 'Un premier tour du vocabulaire et des questions à se poser avant de vendre un produit à des clients de l\'Union européenne.',
                    'es' => 'Un primer repaso del vocabulario y de las preguntas que conviene hacerse antes de vender un producto a clientes de la Unión Europea.',
                ],
            ],
            [
                'key' => 'product-label-checklist', 'category' => 'labelling', 'days_ago' => 6,
                'title' => [
                    'en' => 'Product label checklist before you ship to the EU',
                    'fr' => 'Étiquette produit : la liste à vérifier avant d\'expédier vers l\'UE',
                    'es' => 'Etiqueta de producto: lista de comprobación antes de enviar a la UE',
                ],
                'slug' => ['en' => 'product-label-checklist', 'fr' => 'etiquette-produit-liste-a-verifier', 'es' => 'etiqueta-de-producto-lista-de-comprobacion'],
                'excerpt' => [
                    'en' => 'A simple way to review a label line by line, and to spot what is missing before the parcel leaves.',
                    'fr' => 'Une méthode simple pour relire une étiquette ligne par ligne et repérer ce qui manque avant le départ du colis.',
                    'es' => 'Un método sencillo para revisar una etiqueta línea por línea y detectar lo que falta antes de que salga el paquete.',
                ],
            ],
            [
                'key' => 'responsible-person-role', 'category' => 'responsible-person', 'days_ago' => 9,
                'title' => [
                    'en' => 'What an EU Responsible Person does day to day',
                    'fr' => 'Le rôle d\'une Personne Responsable dans l\'UE, au quotidien',
                    'es' => 'Qué hace una Persona Responsable en la UE en el día a día',
                ],
                'slug' => ['en' => 'what-an-eu-responsible-person-does', 'fr' => 'role-personne-responsable-ue', 'es' => 'que-hace-una-persona-responsable-en-la-ue'],
                'excerpt' => [
                    'en' => 'Behind the name on the label: the tasks, the exchanges with the seller and the moments when the role really matters.',
                    'fr' => 'Derrière le nom sur l\'étiquette : les tâches, les échanges avec le vendeur et les moments où ce rôle compte vraiment.',
                    'es' => 'Detrás del nombre en la etiqueta: las tareas, los intercambios con el vendedor y los momentos en que este papel importa de verdad.',
                ],
            ],
            [
                'key' => 'etsy-sellers-outside-eu', 'category' => 'marketplaces', 'days_ago' => 13,
                'title' => [
                    'en' => 'Selling on Etsy from outside the EU: getting organised',
                    'fr' => 'Vendre sur Etsy depuis l\'extérieur de l\'UE : s\'organiser',
                    'es' => 'Vender en Etsy desde fuera de la UE: cómo organizarse',
                ],
                'slug' => ['en' => 'selling-on-etsy-from-outside-the-eu', 'fr' => 'vendre-sur-etsy-hors-ue', 'es' => 'vender-en-etsy-desde-fuera-de-la-ue'],
                'excerpt' => [
                    'en' => 'How small creative shops prepare their listings and their product information for European buyers.',
                    'fr' => 'Comment les petites boutiques créatives préparent leurs fiches et leurs informations produit pour les acheteurs européens.',
                    'es' => 'Cómo las pequeñas tiendas creativas preparan sus fichas y la información de sus productos para los compradores europeos.',
                ],
            ],
            [
                'key' => 'technical-file-basics', 'category' => 'labelling', 'days_ago' => 17, 'inline_image' => true,
                'title' => [
                    'en' => 'Your product\'s technical file: what goes in it',
                    'fr' => 'Le dossier technique de votre produit : que contient-il ?',
                    'es' => 'El expediente técnico de tu producto: qué incluye',
                ],
                'slug' => ['en' => 'product-technical-file-basics', 'fr' => 'dossier-technique-produit', 'es' => 'expediente-tecnico-del-producto'],
                'excerpt' => [
                    'en' => 'A folder, a few documents and a habit: keeping the information about your product in one place.',
                    'fr' => 'Un dossier, quelques documents et une habitude : garder au même endroit les informations sur votre produit.',
                    'es' => 'Una carpeta, unos cuantos documentos y una costumbre: guardar en un mismo lugar la información sobre tu producto.',
                ],
            ],
            [
                'key' => 'handmade-candles', 'category' => 'basics', 'days_ago' => 21,
                'title' => [
                    'en' => 'Handmade candles and the EU market: questions sellers ask',
                    'fr' => 'Bougies artisanales et marché européen : les questions des vendeurs',
                    'es' => 'Velas artesanales y mercado europeo: las preguntas de los vendedores',
                ],
                'slug' => ['en' => 'handmade-candles-eu-market', 'fr' => 'bougies-artisanales-marche-europeen', 'es' => 'velas-artesanales-mercado-europeo'],
                'excerpt' => [
                    'en' => 'The questions candle makers send us most often, gathered in one place.',
                    'fr' => 'Les questions que les créateurs de bougies nous posent le plus souvent, réunies au même endroit.',
                    'es' => 'Las preguntas que más nos hacen los creadores de velas, reunidas en un mismo lugar.',
                ],
            ],
            [
                'key' => 'amazon-listing-fields', 'category' => 'marketplaces', 'days_ago' => 26,
                'title' => [
                    'en' => 'Amazon listings: the compliance fields explained',
                    'fr' => 'Fiches Amazon : les champs de conformité expliqués',
                    'es' => 'Fichas de Amazon: los campos de conformidad explicados',
                ],
                'slug' => ['en' => 'amazon-listing-compliance-fields', 'fr' => 'fiches-amazon-champs-de-conformite', 'es' => 'fichas-de-amazon-campos-de-conformidad'],
                'excerpt' => [
                    'en' => 'A field-by-field look at the information a listing asks for, and where to find it in your own documents.',
                    'fr' => 'Un parcours, champ par champ, des informations demandées par une fiche, et où les trouver dans vos propres documents.',
                    'es' => 'Un recorrido, campo por campo, por la información que pide una ficha y dónde encontrarla en tus propios documentos.',
                ],
            ],
            [
                'key' => 'choosing-responsible-person', 'category' => 'responsible-person', 'days_ago' => 30,
                'title' => [
                    'en' => 'Choosing an EU Responsible Person: questions to ask',
                    'fr' => 'Choisir sa Personne Responsable dans l\'UE : les questions à poser',
                    'es' => 'Elegir una Persona Responsable en la UE: preguntas que conviene hacer',
                ],
                'slug' => ['en' => 'choosing-an-eu-responsible-person', 'fr' => 'choisir-sa-personne-responsable-ue', 'es' => 'elegir-una-persona-responsable-en-la-ue'],
                'excerpt' => [
                    'en' => 'What to ask before you sign: scope, response times, languages and what happens when a product changes.',
                    'fr' => 'Ce qu\'il faut demander avant de signer : périmètre, délais de réponse, langues et suite donnée quand un produit change.',
                    'es' => 'Qué preguntar antes de firmar: alcance, plazos de respuesta, idiomas y qué ocurre cuando un producto cambia.',
                ],
            ],
            [
                'key' => 'jewellery-small-batches', 'category' => 'basics', 'days_ago' => 35,
                'title' => [
                    'en' => 'Jewellery in small batches: preparing your product information',
                    'fr' => 'Bijoux en petites séries : préparer ses informations produit',
                    'es' => 'Joyería en pequeñas series: preparar la información del producto',
                ],
                'slug' => ['en' => 'jewellery-small-batches-product-information', 'fr' => 'bijoux-petites-series-informations-produit', 'es' => 'joyeria-pequenas-series-informacion-del-producto'],
                'excerpt' => [
                    'en' => 'When every piece is a little different, a clear routine helps to describe materials and batches without losing time.',
                    'fr' => 'Quand chaque pièce est un peu différente, une routine claire aide à décrire matières et séries sans perdre de temps.',
                    'es' => 'Cuando cada pieza es un poco distinta, una rutina clara ayuda a describir materiales y series sin perder tiempo.',
                ],
            ],
            [
                'key' => 'safety-warnings-wording', 'category' => 'labelling', 'days_ago' => 41, 'inline_image' => true,
                'title' => [
                    'en' => 'Writing safety warnings customers actually read',
                    'fr' => 'Rédiger des avertissements de sécurité que les clients lisent vraiment',
                    'es' => 'Redactar advertencias de seguridad que los clientes sí leen',
                ],
                'slug' => ['en' => 'writing-safety-warnings', 'fr' => 'rediger-des-avertissements-de-securite', 'es' => 'redactar-advertencias-de-seguridad'],
                'excerpt' => [
                    'en' => 'Short sentences, the right place on the packaging and the language of the buyer: a few writing habits.',
                    'fr' => 'Des phrases courtes, le bon endroit sur l\'emballage et la langue de l\'acheteur : quelques habitudes de rédaction.',
                    'es' => 'Frases cortas, el lugar adecuado en el embalaje y el idioma del comprador: algunos hábitos de redacción.',
                ],
            ],
            [
                'key' => 'online-shop-eu-customers', 'category' => 'marketplaces', 'days_ago' => 47,
                'title' => [
                    'en' => 'Your own online shop and EU customers: a practical checklist',
                    'fr' => 'Votre boutique en ligne et les clients européens : la liste pratique',
                    'es' => 'Tu tienda online y los clientes europeos: lista práctica',
                ],
                'slug' => ['en' => 'online-shop-eu-customers-checklist', 'fr' => 'boutique-en-ligne-clients-europeens', 'es' => 'tienda-online-clientes-europeos'],
                'excerpt' => [
                    'en' => 'Product pages, contact details and order e-mails: where product information appears when you sell from your own site.',
                    'fr' => 'Pages produit, coordonnées et e-mails de commande : où apparaissent les informations produit quand on vend depuis son propre site.',
                    'es' => 'Páginas de producto, datos de contacto y correos de pedido: dónde aparece la información del producto cuando vendes desde tu propio sitio.',
                ],
            ],
            [
                'key' => 'textiles-and-accessories', 'category' => 'basics', 'days_ago' => 54,
                'title' => [
                    'en' => 'Textiles and accessories: what to gather before listing',
                    'fr' => 'Textiles et accessoires : ce qu\'il faut réunir avant la mise en ligne',
                    'es' => 'Textiles y accesorios: qué reunir antes de publicar',
                ],
                'slug' => ['en' => 'textiles-accessories-before-listing', 'fr' => 'textiles-accessoires-avant-mise-en-ligne', 'es' => 'textiles-accesorios-antes-de-publicar'],
                'excerpt' => [
                    'en' => 'Composition, care instructions, suppliers: the information worth collecting while the product is still on your table.',
                    'fr' => 'Composition, entretien, fournisseurs : les informations à rassembler tant que le produit est encore sur votre table.',
                    'es' => 'Composición, cuidados, proveedores: la información que conviene reunir mientras el producto sigue sobre tu mesa.',
                ],
            ],
            [
                'key' => 'when-an-authority-gets-in-touch', 'category' => 'responsible-person', 'days_ago' => 62,
                'title' => [
                    'en' => 'When an authority gets in touch: who answers and how',
                    'fr' => 'Quand une autorité vous contacte : qui répond, et comment',
                    'es' => 'Cuando una autoridad se pone en contacto: quién responde y cómo',
                ],
                'slug' => ['en' => 'when-an-authority-gets-in-touch', 'fr' => 'quand-une-autorite-vous-contacte', 'es' => 'cuando-una-autoridad-se-pone-en-contacto'],
                'excerpt' => [
                    'en' => 'A request arrives about one of your products. Who receives it, who replies, and what you will be asked for.',
                    'fr' => 'Une demande arrive au sujet de l\'un de vos produits. Qui la reçoit, qui répond, et ce que l\'on vous demandera.',
                    'es' => 'Llega una solicitud sobre uno de tus productos. Quién la recibe, quién responde y qué se te pedirá.',
                ],
            ],
            [
                'key' => 'welcome-to-the-festilaw-blog', 'category' => 'news', 'days_ago' => 70,
                'title' => [
                    'en' => 'Welcome to the Festilaw blog',
                    'fr' => 'Bienvenue sur le blog Festilaw',
                    'es' => 'Bienvenidos al blog de Festilaw',
                ],
                'slug' => ['en' => 'welcome-to-the-festilaw-blog', 'fr' => 'bienvenue-sur-le-blog-festilaw', 'es' => 'bienvenidos-al-blog-de-festilaw'],
                'excerpt' => [
                    'en' => 'Why we are opening this space, what you will find here and how often we will publish.',
                    'fr' => 'Pourquoi nous ouvrons cet espace, ce que vous y trouverez et à quel rythme nous publierons.',
                    'es' => 'Por qué abrimos este espacio, qué encontrarás en él y con qué frecuencia publicaremos.',
                ],
            ],
        ];
    }

    /**
     * Corps d'un article de demonstration, en Markdown : il montre chaque style du contenu (intertitres,
     * gras, italique, lien, listes, citation). `:excerpt` est remplace par l'extrait de l'article.
     */
    public static function body(string $locale): string
    {
        return match ($locale) {
            'fr' => <<<'MARKDOWN'
            > **Contenu de démonstration.** Ce texte sert uniquement à présenter la mise en page du blog Festilaw. Ce n'est pas une information juridique et il sera remplacé avant l'ouverture du blog.

            :excerpt

            ## Ce que l'article abordera

            La version définitive déroulera le sujet pas à pas, avec des exemples tirés des questions que les vendeurs nous envoient. En attendant, ce paragraphe montre l'apparence d'un texte courant, avec **des mots en gras**, *des mots en italique* et [un lien vers la page des tarifs](/fr/pricing).

            - Un premier point, court et concret
            - Un deuxième point, un peu plus détaillé, pour voir comment une ligne plus longue passe à la ligne sur un téléphone
            - Un troisième point

            ## Comment les étapes sont présentées

            ### Une liste numérotée

            1. Rassembler les informations que vous avez déjà sur votre produit
            2. Repérer ce qui manque
            3. Tout garder au même endroit

            ### Une remarque mise en avant

            > Une citation ou une remarque importante s'affiche ainsi, à l'écart du reste du texte.

            ## Pour finir

            Le dernier paragraphe résume l'article et indique l'étape suivante.
            MARKDOWN,
            'es' => <<<'MARKDOWN'
            > **Contenido de demostración.** Este texto solo sirve para mostrar el diseño del blog de Festilaw. No es información jurídica y se sustituirá antes de la apertura del blog.

            :excerpt

            ## Qué tratará el artículo

            La versión definitiva desarrollará el tema paso a paso, con ejemplos sacados de las preguntas que nos envían los vendedores. Mientras tanto, este párrafo muestra el aspecto de un texto normal, con **palabras en negrita**, *palabras en cursiva* y [un enlace a la página de precios](/es/pricing).

            - Un primer punto, breve y concreto
            - Un segundo punto, algo más detallado, para ver cómo una línea más larga salta de línea en un teléfono
            - Un tercer punto

            ## Cómo se presentan los pasos

            ### Una lista numerada

            1. Reunir la información que ya tienes sobre tu producto
            2. Detectar lo que falta
            3. Guardarlo todo en un mismo lugar

            ### Una observación destacada

            > Una cita o una observación importante se muestra así, separada del resto del texto.

            ## Para terminar

            El último párrafo resume el artículo e indica el paso siguiente.
            MARKDOWN,
            default => <<<'MARKDOWN'
            > **Demonstration content.** This text is only here to preview the layout of the Festilaw blog. It is not legal information and it will be replaced before the blog opens.

            :excerpt

            ## What the article will cover

            The final version will go through the topic step by step, with examples taken from the questions sellers send us. In the meantime, this paragraph shows what regular text looks like, with **words in bold**, *words in italics* and [a link to the pricing page](/pricing).

            - A first point, short and concrete
            - A second point, with a little more detail, to see how a longer line wraps on a phone
            - A third point

            ## How the steps are presented

            ### A numbered list

            1. Gather the information you already have about your product
            2. Spot what is missing
            3. Keep everything in one place

            ### A highlighted remark

            > A quotation or an important remark is displayed like this, set apart from the rest of the text.

            ## To finish

            The last paragraph sums up the article and points to the next step.
            MARKDOWN,
        };
    }

    /**
     * Description de l'image principale d'un article, par langue.
     *
     * @return array<string, string>
     */
    public static function imageAlt(): array
    {
        return [
            'en' => 'Demonstration picture',
            'fr' => 'Image de démonstration',
            'es' => 'Imagen de demostración',
        ];
    }
}
