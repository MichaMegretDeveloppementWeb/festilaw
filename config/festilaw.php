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

    /*
     | Parcours STARTER (Creator Pack). Montant en centimes ; liste des pieces obligatoires
     | pour qu'un dossier soit "complet" (a confirmer avec la cliente, QO-5/D1).
     */
    'starter' => [
        'amount_cents' => (int) env('FESTILAW_STARTER_AMOUNT_CENTS', 33300),
        'required_documents' => ['turnover_proof', 'technical_documentation'],
    ],

    /*
     | Parcours SCALE : paiement de l'audit (deduit du contrat final).
     */
    'scale' => [
        'audit_amount_cents' => (int) env('FESTILAW_SCALE_AUDIT_CENTS', 7500),
    ],

];
