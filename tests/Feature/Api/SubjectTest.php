<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubjectTest extends TestCase
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

    public function test_can_create_subject(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/academic/subjects', [
                'subject_name' => 'Mathematics',
                'subject_type' => 'theory',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'subject_name' => 'Mathematics',
                    'subject_type' => 'theory',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('subjects', [
            'tenant_id' => $this->tenant->id,
            'subject_name' => 'Mathematics',
        ]);
    }

    public function test_cannot_duplicate_subject_name(): void
    {
        Subject::factory()->withTenant($this->tenant)->create([
            'subject_name' => 'Mathematics',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/academic/subjects', [
                'subject_name' => 'Mathematics',
                'subject_type' => 'theory',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_duplicate_subject_code(): void
    {
        Subject::factory()->withTenant($this->tenant)->create([
            'subject_name' => 'Mathematics',
            'subject_code' => 'MTH101',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/academic/subjects', [
                'subject_name' => 'English',
                'subject_code' => 'MTH101',
                'subject_type' => 'theory',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_subjects(): void
    {
        Subject::factory()->withTenant($this->tenant)->create([
            'subject_name' => 'Mathematics',
        ]);
        Subject::factory()->withTenant($this->tenant)->create([
            'subject_name' => 'English',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/subjects');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_view_single_subject(): void
    {
        $subject = Subject::factory()->withTenant($this->tenant)->create([
            'subject_name' => 'Mathematics',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/academic/subjects/{$subject->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $subject->id,
                    'subject_name' => 'Mathematics',
                ],
            ]);
    }

    public function test_can_activate_subject(): void
    {
        $subject = Subject::factory()->withTenant($this->tenant)->inactive()->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/subjects/{$subject->id}/activate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue($subject->fresh()->is_active);
    }

    public function test_can_inactivate_subject(): void
    {
        $subject = Subject::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/subjects/{$subject->id}/inactivate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($subject->fresh()->is_active);
    }

    public function test_can_delete_subject(): void
    {
        $subject = Subject::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/subjects/{$subject->id}/delete", [
                'delete_reason' => 'Subject removed',
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $subject->refresh();

        $this->assertNotNull($subject->deleted_at);
        $this->assertEquals($this->user->id, $subject->deleted_by);
        $this->assertEquals('Subject removed', $subject->delete_reason);
    }

    public function test_deleted_subject_absent_from_list(): void
    {
        $subject = Subject::factory()->withTenant($this->tenant)->create();

        $this->withToken($this->token)
            ->postJson("/api/academic/subjects/{$subject->id}/delete");

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/subjects');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_access_fails(): void
    {
        $response = $this->getJson('/api/academic/subjects');

        $response->assertUnauthorized();
    }
}
