<?php

namespace Tests\Feature\Api;

use App\Models\Plan;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use DatabaseTransactions;

    public function test_can_register_new_tenant(): void
    {
        $response = $this->postJson('/api/register', [
            'name_en' => 'Test School',
            'name_bn' => 'টেস্ট স্কুল',
            'school_type' => 'high_school',
            'email' => 'admin@test.com',
            'phone' => '01700000001',
            'admin_name_en' => 'Admin User',
            'admin_name_bn' => 'অ্যাডমিন ইউজার',
            'admin_password' => 'Password@123',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'status',
                'data' => [
                    'tenant' => ['id', 'name_en', 'name_bn', 'slug', 'onboarding_step', 'onboarding_done'],
                    'admin' => ['id', 'name_en', 'name_bn', 'email', 'phone'],
                    'token',
                ],
            ]);

        $this->assertDatabaseHas('tenants', [
            'name_en' => 'Test School',
            'onboarding_step' => 1,
            'onboarding_done' => false,
        ]);

        $this->assertDatabaseHas('users', [
            'phone' => '01700000001',
            'user_type' => 'tenant_admin',
        ]);
    }

    public function test_registration_assigns_trial_plan(): void
    {
        $response = $this->postJson('/api/register', [
            'name_en' => 'Test School 2',
            'name_bn' => 'টেস্ট স্কুল ২',
            'school_type' => 'college',
            'phone' => '01700000002',
            'admin_name_en' => 'Admin Two',
            'admin_name_bn' => 'অ্যাডমিন টু',
            'admin_password' => 'Password@123',
        ]);

        $response->assertStatus(201);

        $trialPlan = Plan::where('slug', 'trial')->first();
        $this->assertDatabaseHas('tenants', [
            'id' => $response->json('data.tenant.id'),
            'current_plan_id' => $trialPlan?->id,
        ]);
    }

    public function test_registration_without_email(): void
    {
        $response = $this->postJson('/api/register', [
            'name_en' => 'Test School 3',
            'name_bn' => 'টেস্ট স্কুল ৩',
            'school_type' => 'primary',
            'phone' => '01700000003',
            'admin_name_en' => 'Admin Three',
            'admin_name_bn' => 'অ্যাডমিন থ্রি',
            'admin_password' => 'Password@123',
        ]);

        $response->assertStatus(201);
    }

    public function test_registration_requires_required_fields(): void
    {
        $response = $this->postJson('/api/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name_en', 'name_bn', 'school_type', 'phone', 'admin_name_en', 'admin_name_bn', 'admin_password']);
    }

    public function test_registration_returns_token(): void
    {
        $response = $this->postJson('/api/register', [
            'name_en' => 'Token Test School',
            'name_bn' => 'টোকেন টেস্ট স্কুল',
            'school_type' => 'high_school',
            'phone' => '01700000004',
            'admin_name_en' => 'Admin Token',
            'admin_name_bn' => 'অ্যাডমিন টোকেন',
            'admin_password' => 'Password@123',
        ]);

        $response->assertStatus(201);
        $this->assertNotNull($response->json('data.token'));
    }
}
