<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class TestController extends Controller
{
    /**
     * Return a test message.
     */
    public function index(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Welcome to your first API in School SaaS!',
            'data' => [
                'app_name' => config('app.name'),
                'timezone' => config('app.timezone'),
                'time' => now()->toDateTimeString(),
            ],
        ]);
    }
}
