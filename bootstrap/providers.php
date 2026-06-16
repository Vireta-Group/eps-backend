<?php

use App\Providers\AppServiceProvider;
use Dedoc\Scramble\ScrambleServiceProvider;
use Laravel\Sanctum\SanctumServiceProvider;

return [
    AppServiceProvider::class,
    SanctumServiceProvider::class,
    ScrambleServiceProvider::class,
];
