<?php

declare(strict_types=1);

return [

    /*
     | Locales supportees par le site (ADR-003). L'ordre definit l'affichage du selecteur.
     | La locale par defaut vient de config('app.locale') (APP_LOCALE).
     */
    'supported_locales' => ['en', 'fr', 'es'],

    /*
     | Locales reellement publiees (traduites). Les autres sont servies mais passees en noindex
     | et exclues du hreflang et du sitemap, jusqu'a ce que leurs traductions existent (Jalon i18n).
     */
    'published_locales' => ['en'],

    /*
     | Libelles affiches dans le selecteur de langue.
     */
    'locale_labels' => [
        'en' => 'EN',
        'fr' => 'FR',
        'es' => 'ES',
    ],

    /*
     | Adresse qui recoit les notifications (chaque soumission de formulaire, paiement...).
     | Valeur par defaut a confirmer avec Festilaw (voir questions-cliente.md B3).
     */
    'notification_email' => env('FESTILAW_NOTIFICATION_EMAIL', 'team@festilaw.com'),

];
