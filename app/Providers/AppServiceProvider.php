<?php

namespace App\Providers;

use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if (class_exists(Scramble::class)) {
            Scramble::afterOpenApiGenerated(function ($openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
        }
    }
}
