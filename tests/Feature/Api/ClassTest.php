<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ClassTest extends TestCase
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

    public function test_can_create_class(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/academic/classes', [
                'class_name' => 'Six',
                'class_type' => 'regular',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'class_name' => 'Six',
                    'class_type' => 'regular',
                    'is_active' => true,
                    'numeric_order' => 1,
                ],
            ]);

        $this->assertDatabaseHas('school_classes', [
            'tenant_id' => $this->tenant->id,
            'class_name' => 'Six',
            'numeric_order' => 1,
        ]);
    }

    public function test_cannot_duplicate_class_name(): void
    {
        SchoolClass::factory()->withTenant($this->tenant)->create([
            'class_name' => 'Six',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/academic/classes', [
                'class_name' => 'Six',
                'class_type' => 'regular',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_classes_sorted_by_numeric_order(): void
    {
        SchoolClass::factory()->withTenant($this->tenant)->withOrder(2)->create([
            'class_name' => 'Seven',
        ]);
        SchoolClass::factory()->withTenant($this->tenant)->withOrder(1)->create([
            'class_name' => 'Six',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/classes');

        $response->assertOk()
            ->assertJsonCount(2, 'data');

        $data = $response->json('data');
        $this->assertEquals('Six', $data[0]['class_name']);
        $this->assertEquals('Seven', $data[1]['class_name']);
    }

    public function test_can_view_single_class(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create([
            'class_name' => 'Six',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/academic/classes/{$class->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $class->id,
                    'class_name' => 'Six',
                ],
            ]);
    }

    public function test_can_activate_class(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->inactive()->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/classes/{$class->id}/activate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue($class->fresh()->is_active);
    }

    public function test_can_inactivate_class(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/classes/{$class->id}/inactivate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($class->fresh()->is_active);
    }

    public function test_can_filter_by_is_active(): void
    {
        SchoolClass::factory()->withTenant($this->tenant)->create([
            'class_name' => 'Six',
        ]);
        SchoolClass::factory()->withTenant($this->tenant)->inactive()->create([
            'class_name' => 'Seven',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/classes?is_active=true');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertEquals('Six', $response->json('data.0.class_name'));

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/classes?is_active=false');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
        $this->assertEquals('Seven', $response->json('data.0.class_name'));
    }

    public function test_can_delete_class(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/classes/{$class->id}/delete", [
                'delete_reason' => 'Class merged with another',
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $class->refresh();

        $this->assertNotNull($class->deleted_at);
        $this->assertEquals($this->user->id, $class->deleted_by);
        $this->assertEquals('Class merged with another', $class->delete_reason);
    }

    public function test_deleted_class_absent_from_list(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();

        $this->withToken($this->token)
            ->postJson("/api/academic/classes/{$class->id}/delete");

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/classes');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_access_fails(): void
    {
        $response = $this->getJson('/api/academic/classes');

        $response->assertUnauthorized();
    }
}
