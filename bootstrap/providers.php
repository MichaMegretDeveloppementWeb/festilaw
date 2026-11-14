<?php

use App\Providers\AppServiceProvider;
use App\Providers\BlogGenerationServiceProvider;
use App\Providers\PaymentServiceProvider;
use App\Providers\SignatureServiceProvider;

return [
    AppServiceProvider::class,
    BlogGenerationServiceProvider::class,
    PaymentServiceProvider::class,
    SignatureServiceProvider::class,
];
