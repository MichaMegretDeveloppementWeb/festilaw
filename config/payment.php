<?php

declare(strict_types=1);

return [

    /*
     | Providers de paiement actifs (plusieurs possibles, l'acheteur choisit au checkout).
     | Stripe est le seul prestataire integre (PAYMENT_PROVIDERS=stripe, ou 'stripe,paypal' plus tard).
     */
    'enabled' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PAYMENT_PROVIDERS', 'stripe')),
    ))),

    'drivers' => [
        'stripe' => [
            'secret_key' => env('STRIPE_SECRET_KEY'),
            'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        ],
    ],

];
