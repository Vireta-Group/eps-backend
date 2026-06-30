<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\RegisterRequest;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

#[Group('Registration')]
class TenantRegistrationController extends Controller
{
    public function register(RegisterRequest $request): JsonResponse
    {
        $validated = $request->validated();

        try {
            return DB::transaction(function () use ($validated) {
                $slug = Str::slug($validated['name_en']).'-'.Str::lower(Str::random(6));

                $trialPlan = Plan::where('slug', 'trial')->first();

                $tenant = Tenant::create([
                    'name_en' => $validated['name_en'],
                    'name_bn' => $validated['name_bn'],
                    'school_type' => $validated['school_type'],
                    'slug' => $slug,
                    'subdomain' => $slug,
                    'current_plan_id' => $trialPlan?->id,
                    'tenant_status' => 'setup',
                    'onboarding_step' => 1,
                    'onboarding_done' => false,
                ]);

                $user = User::create([
                    'tenant_id' => $tenant->id,
                    'name_en' => $validated['admin_name_en'],
                    'name_bn' => $validated['admin_name_bn'],
                    'email' => $validated['email'] ?? null,
                    'phone' => $validated['phone'],
                    'password_hash' => Hash::make($validated['admin_password']),
                    'user_type' => 'tenant_admin',
                    'user_status' => 'active',
                ]);

                $tenant->update(['created_by' => $user->id]);

                $token = $user->createToken('registration-token')->plainTextToken;

                return response()->json([
                    'status' => 'success',
                    'message' => 'Registration successful.',
                    'data' => [
                        'tenant' => [
                            'id' => $tenant->id,
                            'name_en' => $tenant->name_en,
                            'name_bn' => $tenant->name_bn,
                            'slug' => $tenant->slug,
                            'onboarding_step' => $tenant->onboarding_step,
                            'onboarding_done' => $tenant->onboarding_done,
                        ],
                        'admin' => [
                            'id' => $user->id,
                            'name_en' => $user->name_en,
                            'name_bn' => $user->name_bn,
                            'email' => $user->email,
                            'phone' => $user->phone,
                        ],
                        'token' => $token,
                    ],
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Registration failed. Please try again.',
            ], 500);
        }
    }

    /**
     * @param  Request  $request  Request containing `value` query param.
     * @return JsonResponse{field: string, value: string, available: bool, message: string}
     */
    public function checkEmail(Request $request): JsonResponse
    {
        $email = $request->query('value');

        if (! $email) {
            return response()->json(['message' => 'The value query parameter is required.'], 422);
        }

        $exists = User::where('email', $email)->exists();

        return response()->json([
            'field' => 'email',
            'value' => $email,
            'available' => ! $exists,
            'message' => $exists ? 'This email is already registered.' : 'Email is available.',
        ]);
    }

    /**
     * @param  Request  $request  Request containing `value` query param.
     * @return JsonResponse{field: string, value: string, available: bool, message: string}
     */
    public function checkPhone(Request $request): JsonResponse
    {
        $phone = $request->query('value');

        if (! $phone) {
            return response()->json(['message' => 'The value query parameter is required.'], 422);
        }

        $exists = User::where('phone', $phone)->exists();

        return response()->json([
            'field' => 'phone',
            'value' => $phone,
            'available' => ! $exists,
            'message' => $exists ? 'This phone number is already registered.' : 'Phone number is available.',
        ]);
    }
}
