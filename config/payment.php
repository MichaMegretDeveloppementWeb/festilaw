<?php

declare(strict_types=1);

return [

    /*
     | Providers de paiement actifs (plusieurs possibles, l'acheteur choisit au checkout).
     | Par defaut 'fake' : aucun appel externe, le tunnel tourne sans cle.
     | Pour activer Stripe : PAYMENT_PROVIDERS=stripe (ou 'stripe,paypal' plus tard).
     */
    'enabled' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PAYMENT_PROVIDERS', 'fake')),
    ))),

    'drivers' => [
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

    'fake' => [
        // URL de retour du faux checkout (branchee sur la route de dev plus tard).
        'redirect_url' => env('PAYMENT_FAKE_REDIRECT_URL'),
    ],

];
