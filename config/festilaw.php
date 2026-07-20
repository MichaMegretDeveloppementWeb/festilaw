<?php

declare(strict_types=1);

return [

    /*
     | Langues proposees par le selecteur (traduction visuelle uniquement, cf. SetLocale). L'ordre
     | definit l'affichage. La langue canonique / par defaut vient de config('app.locale') (APP_LOCALE) :
     | le site n'est PAS un multilingue reference (un seul jeu d'URLs, pas de hreflang).
     */
    'supported_locales' => ['en', 'fr', 'es'],

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
    'notification_email' => env('FESTILAW_NOTIFICATION_EMAIL') ?: 'team@festilaw.com',

    /*
     | Parcours STARTER (Creator Pack). Montant en centimes ; liste des pieces obligatoires
     | pour qu'un dossier soit "complet" (a confirmer avec la cliente, QO-5/D1).
     */
    'starter' => [
        'amount_cents' => (int) env('FESTILAW_STARTER_AMOUNT_CENTS', 33300),
        'required_documents' => ['turnover_proof', 'technical_documentation'],
        // Duree de validite du lien de reprise du dossier (jours).
        'resume_ttl_days' => (int) env('FESTILAW_STARTER_RESUME_TTL_DAYS', 30),
        // Purge RGPD : delai (jours) apres expiration du lien avant de supprimer un dossier
        // abandonne (jamais paye) et ses fichiers televerses. Les dossiers payes sont conserves.
        'abandoned_retention_days' => (int) env('FESTILAW_STARTER_ABANDONED_RETENTION_DAYS', 90),
    ],

    /*
     | Parcours PRO : cotisation annuelle (contrat Pack Pro) + numero WhatsApp Business (LV2).
     */
    'pro' => [
        'amount_cents' => (int) env('FESTILAW_PRO_AMOUNT_CENTS', 120000),
        'whatsapp_url' => env('FESTILAW_WHATSAPP_URL'),
    ],

    /*
     | Parcours SCALE : paiement de l'audit (deduit du contrat final) + agenda de reservation.
     */
    'scale' => [
        'audit_amount_cents' => (int) env('FESTILAW_SCALE_AUDIT_CENTS', 7500),
        'calendar_url' => env('FESTILAW_SCALE_CALENDAR_URL', 'https://calendar.app.google/w8ZejYQLkZfgAo3F7'),
    ],

];
