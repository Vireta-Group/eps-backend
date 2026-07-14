<?php

namespace App\Providers;

use App\Models\Student;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('student', function (string $value): Student {
            return Student::where('student_id', $value)->first()
                ?? Student::where('id', $value)->firstOrFail();
        });

        if (class_exists(Scramble::class)) {
            Scramble::afterOpenApiGenerated(function ($openApi) {
                $openApi->secure(
                    SecurityScheme::http('bearer')
                );
            });
        }
    }
}
