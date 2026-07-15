<?php

declare(strict_types=1);

return [

    /*
     | Provider de signature actif (un seul a la fois). Par defaut 'fake' : aucun appel externe,
     | le tunnel fonctionne sans cle. Passer a 'signwell' quand la cle API est fournie.
     */
    'default' => env('SIGNATURE_DRIVER', 'fake'),

    'drivers' => [

        /*
         | SignWell (prestataire retenu) : pay-per-use, sans abonnement. La cle API est generee une
         | fois (Settings > API) et porte l'application. test_mode : documents de test gratuits, aucun
         | email envoye · pour valider l'integration avant la prod. Les 25 premiers documents/mois
         | reels sont gratuits. La signature est confirmee au retour (checkStatus) ou par webhook.
         */
        'signwell' => [
            'api_key' => env('SIGNWELL_API_KEY'),
            // Optionnel : id d'application API (si le compte en a plusieurs).
            'api_application_id' => env('SIGNWELL_API_APPLICATION_ID'),
            'api_base_url' => env('SIGNWELL_API_BASE_URL', 'https://www.signwell.com/api/v1'),
            // Mode test : documents gratuits, aucun email. Mettre false en prod.
            'test_mode' => (bool) env('SIGNWELL_TEST_MODE', true),
        ],

        /*
         | Zoho Sign (OAuth2 serveur-a-serveur). Datacenter par defaut : Europe (.eu). Tous les
         | domaines doivent rester sur le meme datacenter que le compte (sinon INVALID_OAUTHTOKEN).
         | Le refresh_token est genere une fois (self client) et ne change pas ; l'access token
         | (1 h) est obtenu a la volee et mis en cache. Cf. checklist de configuration.
         */
        'zoho' => [
            'client_id' => env('ZOHO_SIGN_CLIENT_ID'),
            'client_secret' => env('ZOHO_SIGN_CLIENT_SECRET'),
            'refresh_token' => env('ZOHO_SIGN_REFRESH_TOKEN'),
            // Template du contrat cree dans Zoho Sign (avec un champ signataire).
            'template_id' => env('ZOHO_SIGN_TEMPLATE_ID'),
            // Secret HMAC du webhook (Settings > Developer > Webhooks), pour verifier l'authenticite.
            'webhook_secret' => env('ZOHO_SIGN_WEBHOOK_SECRET'),
            // Domaines datacenter (par defaut .eu ; passer en .com si le compte est aux US).
            'accounts_url' => env('ZOHO_ACCOUNTS_URL', 'https://accounts.zoho.eu'),
            'api_base_url' => env('ZOHO_SIGN_API_BASE_URL', 'https://sign.zoho.eu/api/v1'),
            // Mode test : documents filigranes, ne consomme pas de credit (dev). Mettre false en prod.
            'testing' => (bool) env('ZOHO_SIGN_TESTING', false),
        ],
    ],

    'fake' => [
        // URL de retour du faux parcours de signature (branchee sur la route de dev plus tard).
        'signing_url' => env('SIGNATURE_FAKE_SIGNING_URL'),
    ],

];
