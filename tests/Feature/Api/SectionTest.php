<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SectionTest extends TestCase
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

    public function test_can_create_section(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson('/api/academic/sections', [
                'school_class_id' => $class->id,
                'section_name' => 'A',
                'shift' => 'morning',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'section_name' => 'A',
                    'shift' => 'morning',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('school_sections', [
            'tenant_id' => $this->tenant->id,
            'school_class_id' => $class->id,
            'section_name' => 'A',
        ]);
    }

    public function test_cannot_duplicate_section_name_in_same_class(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();

        SchoolSection::factory()->withClass($class)->create([
            'section_name' => 'A',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/academic/sections', [
                'school_class_id' => $class->id,
                'section_name' => 'A',
                'shift' => 'morning',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_sections(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();

        SchoolSection::factory()->withClass($class)->create([
            'section_name' => 'A',
        ]);
        SchoolSection::factory()->withClass($class)->create([
            'section_name' => 'B',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/sections?school_class_id='.$class->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_view_single_section(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create([
            'section_name' => 'A',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/academic/sections/{$section->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $section->id,
                    'section_name' => 'A',
                ],
            ]);
    }

    public function test_can_activate_section(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->inactive()->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/sections/{$section->id}/activate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue($section->fresh()->is_active);
    }

    public function test_can_inactivate_section(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/sections/{$section->id}/inactivate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($section->fresh()->is_active);
    }

    public function test_can_delete_section(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/sections/{$section->id}/delete", [
                'delete_reason' => 'Section merged',
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $section->refresh();

        $this->assertNotNull($section->deleted_at);
        $this->assertEquals($this->user->id, $section->deleted_by);
        $this->assertEquals('Section merged', $section->delete_reason);
    }

    public function test_deleted_section_absent_from_list(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();

        $this->withToken($this->token)
            ->postJson("/api/academic/sections/{$section->id}/delete");

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/sections');

        $response->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_unauthenticated_access_fails(): void
    {
        $response = $this->getJson('/api/academic/sections');

        $response->assertUnauthorized();
    }
}
