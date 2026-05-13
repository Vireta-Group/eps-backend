<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class TenantRegistrationController extends Controller
{
    /**
     * Handle a new tenant registration.
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:200'],
            'name_bn' => ['required', 'string', 'max:200'],
            'school_type' => ['required', 'string', 'max:50'],
            'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
            'admin_name_en' => ['required', 'string', 'max:200'],
            'admin_name_bn' => ['required', 'string', 'max:200'],
            'admin_password' => ['required', 'string', Password::defaults()],
        ]);

        try {
            return DB::transaction(function () use ($validated) {
                // 1. Create Tenant
                $tenant = Tenant::create([
                    'name_en' => $validated['name_en'],
                    'name_bn' => $validated['name_bn'],
                    'school_type' => $validated['school_type'],
                    'tenant_status' => 'setup',
                    'onboarding_step' => 1,
                    'onboarding_done' => false,
                ]);

                // 2. Create Admin User
                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name_en' => $validated['admin_name_en'],
                    'name_bn' => $validated['admin_name_bn'],
                    'phone' => $validated['phone'],
                    'password_hash' => Hash::make($validated['admin_password']),
                    'user_type' => 'tenant_admin',
                    'user_status' => 'active',
                ]);

                // 3. Update Tenant created_by
                $tenant->update(['created_by' => $user->id]);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Registration successful! You can now access your dashboard.',
                    'data' => [
                        'tenant' => [
                            'id' => $tenant->id,
                            'name_en' => $tenant->name_en,
                            'name_bn' => $tenant->name_bn,
                        ],
                        'admin' => [
                            'name_en' => $user->name_en,
                            'name_bn' => $user->name_bn,
                            'phone' => $user->phone,
                        ],
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed. Please try again.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
