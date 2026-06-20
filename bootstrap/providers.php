<?php

use App\Providers\AppServiceProvider;
use Laravel\Horizon\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    HorizonServiceProvider::class,
    App\Providers\HorizonServiceProvider::class,
];
