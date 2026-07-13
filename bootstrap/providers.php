<?php

use App\Providers\AppServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\SignatureServiceProvider;

return [
    AppServiceProvider::class,
    PaymentServiceProvider::class,
    SignatureServiceProvider::class,
];
