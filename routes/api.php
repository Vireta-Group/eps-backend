<?php

use App\Http\Controllers\Api\AcademicYearController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\ClassController;
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

    Route::prefix('academic')->name('academic.')->group(function () {
        Route::get('/years', [AcademicYearController::class, 'index'])->name('years.index');
        Route::post('/years', [AcademicYearController::class, 'store'])->name('years.store');
        Route::get('/years/{year}', [AcademicYearController::class, 'show'])->name('years.show');
        Route::post('/years/{year}/activate', [AcademicYearController::class, 'activate'])->name('years.activate');
        Route::post('/years/{year}/archive', [AcademicYearController::class, 'archive'])->name('years.archive');
        Route::post('/years/{year}/copy', [AcademicYearController::class, 'copy'])->name('years.copy');

        Route::get('/classes', [ClassController::class, 'index'])->name('classes.index');
        Route::post('/classes', [ClassController::class, 'store'])->name('classes.store');
        Route::get('/classes/{school_class}', [ClassController::class, 'show'])->name('classes.show');
        Route::post('/classes/{school_class}/activate', [ClassController::class, 'activate'])->name('classes.activate');
        Route::post('/classes/{school_class}/inactivate', [ClassController::class, 'inactivate'])->name('classes.inactivate');
        Route::post('/classes/{school_class}/delete', [ClassController::class, 'delete'])->name('classes.delete');
    });
});
