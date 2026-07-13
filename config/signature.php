<?php

declare(strict_types=1);

return [

    /*
     | Provider de signature actif (un seul a la fois). Par defaut 'fake' : aucun appel externe,
     | le tunnel fonctionne sans cle. Passer a 'zoho' quand les cles sont fournies.
     */
    'default' => env('SIGNATURE_DRIVER', 'fake'),

    'drivers' => [
        'zoho' => [
            'api_key' => env('ZOHO_SIGN_API_KEY'),
            'base_url' => env('ZOHO_SIGN_BASE_URL'),
        ],
    ],

    'fake' => [
        // URL de retour du faux parcours de signature (branchee sur la route de dev plus tard).
        'signing_url' => env('SIGNATURE_FAKE_SIGNING_URL'),
    ],

];
