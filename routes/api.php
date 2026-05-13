<?php

use App\Http\Controllers\Api\TenantRegistrationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;


Route::post('/register', [TenantRegistrationController::class, 'register']);



