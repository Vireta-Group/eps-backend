<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\LoginRequest;
use App\Http\Resources\Api\TenantResource;
use App\Http\Resources\Api\UserResource;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

#[Group('Authentication', description: 'Login, logout, token refresh, and profile.', weight: 20)]
class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::where('phone', $request->phone)->first();

        if (! $user || ! Hash::check($request->password, $user->password_hash)) {
            throw ValidationException::withMessages([
                'phone' => ['The provided credentials are incorrect.'],
            ]);
        }

        if (! $user->roles()->exists()) {
            throw ValidationException::withMessages([
                'phone' => ['Your account has no active role. Contact support.'],
            ]);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => new UserResource($user),
                'token' => $token,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Logged out successfully.',
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->user()->load(['tenant', 'roles']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'user' => new UserResource($user),
                'tenant' => $user->tenant ? new TenantResource($user->tenant) : null,
                'roles' => $user->roles->pluck('slug'),
            ],
        ]);
    }
}
