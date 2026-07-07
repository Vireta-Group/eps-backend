<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AcademicYearTest extends TestCase
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

    public function test_can_create_academic_year(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/academic/years', [
                'year_name' => '2025-2026',
                'start_date' => '2025-01-01',
                'end_date' => '2026-12-31',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'year_name' => '2025-2026',
                    'year_status' => 'upcoming',
                    'is_current' => false,
                ],
            ]);

        $this->assertDatabaseHas('academic_years', [
            'tenant_id' => $this->tenant->id,
            'year_name' => '2025-2026',
        ]);
    }

    public function test_cannot_create_overlapping_dates(): void
    {
        AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2024-2025',
            'start_date' => '2024-01-01',
            'end_date' => '2025-12-31',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/academic/years', [
                'year_name' => '2025-2026',
                'start_date' => '2025-06-01',
                'end_date' => '2026-06-01',
            ]);

        $response->assertStatus(422);
    }

    public function test_cannot_duplicate_year_name(): void
    {
        AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2025-2026',
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/academic/years', [
                'year_name' => '2025-2026',
                'start_date' => '2026-01-01',
                'end_date' => '2027-12-31',
            ]);

        $response->assertStatus(422);
    }

    public function test_can_list_years(): void
    {
        AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2024-2025',
        ]);
        AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2025-2026',
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/academic/years');

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_view_single_year(): void
    {
        $year = AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2025-2026',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/academic/years/{$year->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $year->id,
                    'year_name' => '2025-2026',
                ],
            ]);
    }

    public function test_can_activate_year(): void
    {
        $year1 = AcademicYear::factory()->withTenant($this->tenant)->current()->create([
            'year_name' => '2023-2024',
        ]);

        $year2 = AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2024-2025',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/years/{$year2->id}/activate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue($year2->fresh()->is_current);
        $this->assertEquals('active', $year2->fresh()->year_status);

        $this->assertFalse($year1->fresh()->is_current);
        $this->assertEquals('archived', $year1->fresh()->year_status);
        $this->assertTrue($year1->fresh()->is_locked);
    }

    public function test_can_archive_year(): void
    {
        $year = AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2024-2025',
            'is_current' => true,
            'year_status' => 'active',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/years/{$year->id}/archive");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $year->refresh();

        $this->assertEquals('archived', $year->year_status);
        $this->assertTrue($year->is_locked);
        $this->assertFalse($year->is_current);
    }

    public function test_can_copy_year(): void
    {
        $source = AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2024-2025',
            'start_date' => '2024-01-01',
            'end_date' => '2025-12-31',
        ]);

        $target = AcademicYear::factory()->withTenant($this->tenant)->create([
            'year_name' => '2025-2026',
            'start_date' => '2025-01-01',
            'end_date' => '2026-12-31',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/academic/years/{$target->id}/copy", [
                'copy_scope' => 'class_section_only',
                'copied_from_year_id' => $source->id,
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $target->refresh();

        $this->assertEquals($source->id, $target->copied_from_year_id);
        $this->assertEquals('class_section_only', $target->copy_scope);
    }

    public function test_unauthenticated_access_fails(): void
    {
        $response = $this->getJson('/api/academic/years');

        $response->assertUnauthorized();
    }
}
