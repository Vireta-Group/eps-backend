<?php

namespace Tests\Feature\Api;

use App\Models\IdCardTemplate;
use App\Models\Role;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdCardTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();

        $this->user = User::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $schoolAdmin = Role::where('slug', 'school_admin')->first();

        if ($schoolAdmin) {
            DB::table('user_roles')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $this->user->id,
                'role_id' => $schoolAdmin->id,
                'scope_type' => 'tenant',
                'scope_id' => $this->tenant->id,
                'assigned_at' => now(),
                'status' => 'active',
            ]);
        }

        $this->token = $this->user->createToken('test-token')->plainTextToken;
    }

    public function test_can_create_template(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/students/id-card/templates', [
                'template_name' => 'Standard',
                'front_layout' => '{"logo":"top","photo":"center","info":"bottom"}',
                'is_default' => true,
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'template_name' => 'Standard',
                ],
            ]);

        $this->assertDatabaseHas('id_card_templates', [
            'tenant_id' => $this->tenant->id,
            'template_name' => 'Standard',
        ]);
    }

    public function test_can_list_templates(): void
    {
        $tenant = $this->tenant;

        IdCardTemplate::factory()->create([
            'tenant_id' => $tenant->id,
            'template_name' => 'Standard',
            'is_default' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/students/id-card/templates');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_generate_id_card(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        IdCardTemplate::factory()->create([
            'tenant_id' => $this->tenant->id,
            'template_name' => 'Standard',
            'is_default' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/students/{$student->student_id}/id-card");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'student' => [
                        'id' => $student->id,
                    ],
                ],
            ]);
    }
}
