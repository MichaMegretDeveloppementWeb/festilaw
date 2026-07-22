<?php

declare(strict_types=1);

return [

    /*
     | Provider de signature actif (un seul a la fois). SignWell est le seul prestataire integre.
     */
    'default' => env('SIGNATURE_DRIVER', 'signwell'),

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
    ],

];
