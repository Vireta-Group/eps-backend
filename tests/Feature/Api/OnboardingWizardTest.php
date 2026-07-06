<?php

namespace Tests\Feature\Api;

use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class OnboardingWizardTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create([
            'onboarding_step' => 1,
            'onboarding_done' => false,
            'tenant_status' => 'setup',
        ]);

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    private function authHeaders(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    public function test_can_get_wizard_status(): void
    {
        $response = $this->withToken($this->token)
            ->getJson('/api/setup/status');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'current_step' => 1,
                    'total_steps' => 4,
                    'completed_steps' => [],
                    'onboarding_done' => false,
                ],
            ]);
    }

    public function test_can_save_step_1(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/setup/step/1', [
                'name_en' => 'Updated School Name',
                'name_bn' => 'আপডেট স্কুল',
                'school_type' => 'High School',
                'phone_primary' => '01711111111',
                'email' => 'school@test.com',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertEquals(2, $this->tenant->fresh()->onboarding_step);
        $this->assertEquals('Updated School Name', $this->tenant->fresh()->name_en);
    }

    public function test_cannot_save_future_step(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/setup/step/4', [
                'fee_types' => [['name' => 'Tuition', 'amount' => 1000, 'frequency' => 'monthly']],
            ]);

        $response->assertStatus(422);
    }

    public function test_can_review_saved_step(): void
    {
        TenantSetting::updateOrCreate(
            [
                'tenant_id' => $this->tenant->id,
                'group_key' => 'step_3',
            ],
            ['settings' => ['classes' => [['name' => 'Class One']]]]
        );

        $this->tenant->update(['onboarding_step' => 4]);

        $response = $this->withToken($this->token)
            ->getJson('/api/setup/step/3');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'step' => 3,
                    'name' => 'class_section_subject_address',
                ],
            ]);
    }

    public function test_can_save_step_3_with_address_and_principal(): void
    {
        $this->tenant->update(['onboarding_step' => 3]);

        $data = [
            'classes' => [['name' => 'Six', 'sections' => ['A', 'B']]],
            'subjects' => [['name' => 'English', 'code' => 'ENG101', 'type' => 'theory']],
            'address' => [
                'division' => 'Dhaka',
                'district' => 'Dhaka',
                'upazila' => 'Mirpur',
            ],
            'principal' => [
                'name_en' => 'Mr. Principal',
                'mobile' => '01700000001',
                'email' => 'principal@school.com',
            ],
        ];

        $response = $this->withToken($this->token)
            ->postJson('/api/setup/step/3', $data);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('tenant_addresses', [
            'tenant_id' => $this->tenant->id,
            'address_type' => 'main',
            'district' => 'Dhaka',
        ]);

        $this->assertDatabaseHas('tenant_contacts', [
            'tenant_id' => $this->tenant->id,
            'contact_type' => 'principal',
            'name_en' => 'Mr. Principal',
        ]);

        $this->assertEquals(4, $this->tenant->fresh()->onboarding_step);
    }

    public function test_can_save_step_4_with_branding(): void
    {
        $this->tenant->update(['onboarding_step' => 4]);

        $response = $this->withToken($this->token)
            ->postJson('/api/setup/step/4', [
                'fee_types' => [['name' => 'Tuition', 'amount' => 1000, 'frequency' => 'monthly']],
                'notifications' => ['sms' => true, 'email' => true],
                'primary_color' => '#2563EB',
                'secondary_color' => '#64748B',
                'font_family' => 'Inter, sans-serif',
            ]);

        $response->assertOk();

        $this->assertDatabaseHas('tenant_brandings', [
            'tenant_id' => $this->tenant->id,
            'primary_color' => '#2563EB',
        ]);
    }

    public function test_can_complete_onboarding(): void
    {
        $this->tenant->update([
            'onboarding_step' => 4,
            'onboarding_done' => false,
            'tenant_status' => 'setup',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/setup/finish');

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'tenant' => [
                        'onboarding_done' => true,
                    ],
                ],
            ]);

        $this->assertTrue($this->tenant->fresh()->onboarding_done);
        $this->assertEquals('active', $this->tenant->fresh()->tenant_status);
    }

    public function test_cannot_finish_before_all_steps(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/setup/finish');

        $response->assertStatus(422);
    }

    public function test_unauthenticated_cannot_access_wizard(): void
    {
        $response = $this->getJson('/api/setup/status');
        $response->assertStatus(401);
    }
}
