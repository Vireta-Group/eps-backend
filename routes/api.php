<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\FileUploadController;
use App\Http\Controllers\Api\OnboardingWizardController;
use App\Http\Controllers\Api\TenantRegistrationController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [TenantRegistrationController::class, 'register']);
Route::post('/check/email', [TenantRegistrationController::class, 'checkEmail']);
Route::post('/check/phone', [TenantRegistrationController::class, 'checkPhone']);

Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/upload', [FileUploadController::class, 'upload']);

    Route::prefix('auth')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });

    Route::prefix('setup')->group(function () {
        Route::get('/status', [OnboardingWizardController::class, 'status']);
        Route::get('/step/{step}', [OnboardingWizardController::class, 'showStep']);
        Route::post('/step/1', [OnboardingWizardController::class, 'saveStep1']);
        Route::post('/step/2', [OnboardingWizardController::class, 'saveStep2']);
        Route::post('/step/3', [OnboardingWizardController::class, 'saveStep3']);
        Route::post('/step/4', [OnboardingWizardController::class, 'saveStep4']);
        Route::post('/step/{step}', [OnboardingWizardController::class, 'saveStep']);
        Route::post('/finish', [OnboardingWizardController::class, 'finish']);
    });
});
