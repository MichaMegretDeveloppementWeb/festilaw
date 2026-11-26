<?php

declare(strict_types=1);

return [

    /*
     | Langue par defaut du site (APP_LOCALE) : ses pages publiques n'ont pas de prefixe d'adresse. A lire
     | ICI et non dans config('app.locale'), que Laravel reecrit a chaque app()->setLocale() : en cours de
     | requete, config('app.locale') est la langue COURANTE, pas la langue par defaut.
     */
    'default_locale' => env('APP_LOCALE', 'en'),

    /*
     | Langues du site. L'ordre definit l'affichage du selecteur. Les pages publiques des langues autres
     | que la langue par defaut sont servies sous /{langue} (routes/localized.php), avec hreflang et
     | sitemap. Le tunnel et l'espace client n'ont qu'une adresse : leur langue vient de la session
     | (cf. SetLocale).
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
     | Valeur des balises og:locale / og:locale:alternate (format langue_TERRITOIRE attendu par Open Graph).
     */
    'og_locales' => [
        'en' => 'en_US',
        'fr' => 'fr_FR',
        'es' => 'es_ES',
    ],

    /*
     | Adresse qui recoit les notifications (chaque soumission de formulaire, paiement...).
     | Valeur par defaut a confirmer avec Festilaw (voir questions-cliente.md B3).
     */
    'notification_email' => env('FESTILAW_NOTIFICATION_EMAIL') ?: 'team@festilaw.com',

    /*
     | Adresse de contact publique de Festilaw : pied de page, page de contact, mentions legales, politique de
     | confidentialite, donnees structurees et e-mails aux clients. Un seul reglage plutot que l'adresse ecrite a
     | chaque endroit (decision de Micha du 23/11). Distincte de l'adresse qui recoit les notifications, pour qu'un
     | changement de boite de reception ne publie jamais une autre adresse sur le site.
     */
    'contact_email' => env('FESTILAW_CONTACT_EMAIL') ?: 'team@festilaw.com',

    /*
     | Alertes techniques (taches automatiques a l'arret) : adresse du prestataire technique. A defaut,
     | l'adresse de notification ci-dessus.
     */
    'tech_alert_email' => env('FESTILAW_TECH_ALERT_EMAIL'),

    /*
     | Taches automatiques (cron unique `schedule:run` chaque minute) : au-dela de stale_after_minutes sans
     | battement, elles sont a l'arret (bandeau du back-office + alerte e-mail, SchedulerHealthService). Au-dela de
     | error_report_stale_hours sans rapport d'erreurs du matin mene a bien (envoye, ou rien a signaler), le
     | prestataire est prevenu directement, puisque c'est ce rapport qui porte tout le reste (constat du 26/11).
     */
    'scheduler' => [
        'stale_after_minutes' => (int) env('FESTILAW_SCHEDULER_STALE_MINUTES', 15),
        'error_report_stale_hours' => 26,
    ],

    /*
     | Parcours STARTER (Creator Pack). Montant en centimes ; liste des pieces obligatoires
     | pour qu'un dossier soit "complet" (valeurs de App\Enums\Document\DocumentType, lues via
     | SubmissionType::requiredDocuments()).
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
     | Parcours PRO : meme parcours en ligne self-service que Creator (cf. StarterJourney). Changent :
     | le tarif annuel (contrat Pack Pro) et la liste des pieces obligatoires : la documentation
     | technique seule, le justificatif de CA ne prouvant que l'eligibilite Creator (Festilaw, 04/10/2026).
     */
    'pro' => [
        'amount_cents' => (int) env('FESTILAW_PRO_AMOUNT_CENTS', 120000),
        'required_documents' => ['technical_documentation'],
    ],

    /*
     | Renouvellement annuel (cf. contrat) : l'annee de service court du 1er janvier au 31 decembre,
     | facturee chaque janvier au plein tarif. Le client dispose de grace_days jours pour regler avant
     | d'etre "en retard". Renouvellement manuel (rappel + paiement depuis le dossier), pas d'abonnement
     | Stripe.
     */
    'renewal' => [
        'grace_days' => (int) env('FESTILAW_RENEWAL_GRACE_DAYS', 30),
    ],

    /*
     | Plancher d'encaissement : montant minimum (centimes) qu'un prestataire accepte de prelever
     | (Stripe ~0,50 € en EUR). Le prorata de l'annee 1 ne descend jamais sous ce seuil, sinon le
     | checkout echoue de facon opaque. Ne mord en pratique que sur un tarif de test tres bas ;
     | l'usage normal (Creator/Pro) reste tres au-dessus.
     */
    'payment' => [
        'min_charge_cents' => (int) env('FESTILAW_MIN_CHARGE_CENTS', 50),
    ],

    /*
     | Parcours SCALE : paiement de l'audit (deduit du contrat final) puis reservation de la consultation
     | (page de reservation Google, integree a l'espace Scale une fois l'audit paye). calendar_url = URL LONGUE de la page
     | (https://calendar.google.com/calendar/appointments/schedules/<ID>) : seule celle-ci s'integre en
     | iframe ; le lien court calendar.app.google ne s'ouvre que dans un nouvel onglet.
     */
    'scale' => [
        'audit_amount_cents' => (int) env('FESTILAW_SCALE_AUDIT_CENTS', 7500),
        'calendar_url' => env('FESTILAW_SCALE_CALENDAR_URL', 'https://calendar.google.com/calendar/appointments/schedules/AcZssZ1WmGHBiY53WN8q2_e-D_7wwcfbnDIPVWczmXSDs0kSyK_xNpUMClzHbv7Lp24JvjmE-_LsAFGz'),
        // Duree de validite du lien magique de l'espace Scale (jours).
        'resume_ttl_days' => (int) env('FESTILAW_SCALE_RESUME_TTL_DAYS', 30),
    ],

    /*
     | Demandes de contact (politique de confidentialite) : supprimees apres retention_months mois sans
     | echange (reception, note interne ou e-mail envoye depuis le back-office), par la commande
     | festilaw:apply-privacy-retention.
     */
    'contact' => [
        'retention_months' => (int) env('FESTILAW_CONTACT_RETENTION_MONTHS', 12),
    ],

];
