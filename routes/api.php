<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\OnboardingWizardController;
use App\Http\Controllers\Api\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [TenantRegistrationController::class, 'register']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('setup')->group(function () {
        Route::get('/status', [OnboardingWizardController::class, 'status']);
        Route::get('/step/{step}', [OnboardingWizardController::class, 'showStep']);
        Route::post('/step/{step}', [OnboardingWizardController::class, 'saveStep']);
        Route::post('/finish', [OnboardingWizardController::class, 'finish']);
    });
});
