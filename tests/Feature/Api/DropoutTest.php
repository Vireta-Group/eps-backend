<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\DropoutRecord;
use App\Models\ReadmissionRecord;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DropoutTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $token;

    private Student $student;

    private AcademicYear $year;

    private SchoolClass $class;

    private SchoolSection $section;

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

        $this->student = Student::factory()->withTenant($this->tenant)->create();
        $this->class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $this->year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $this->section = SchoolSection::factory()->withClass($this->class)->create();
    }

    public function test_list_dropouts(): void
    {
        DropoutRecord::factory()->withTenant($this->tenant)->count(3)->create();

        $response = $this->withToken($this->token)
            ->getJson('/api/dropouts');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_record_dropout(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/dropouts', [
                'student_id' => $this->student->id,
                'dropout_date' => '2026-06-15',
                'dropout_category' => 'financial',
                'reason' => 'Unable to pay tuition fees',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertFalse($this->student->fresh()->is_active);

        $this->assertDatabaseHas('dropout_records', [
            'student_id' => $this->student->id,
            'dropout_category' => 'financial',
        ]);
    }

    public function test_show_dropout(): void
    {
        $dropout = DropoutRecord::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->getJson("/api/dropouts/{$dropout->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $dropout->id,
                ],
            ]);
    }

    public function test_readmit_dropout(): void
    {
        $dropout = DropoutRecord::factory()->withTenant($this->tenant)->create([
            'student_id' => $this->student->id,
        ]);

        $this->student->update(['is_active' => false]);

        $response = $this->withToken($this->token)
            ->postJson("/api/dropouts/{$dropout->id}/readmit", [
                'readmission_date' => '2026-07-01',
                'new_academic_year_id' => $this->year->id,
                'new_class_id' => $this->class->id,
                'new_section_id' => $this->section->id,
                'fee_settled' => true,
                'reason' => 'Financial issues resolved',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertTrue($this->student->fresh()->is_active);

        $this->assertDatabaseHas('readmission_records', [
            'dropout_record_id' => $dropout->id,
            'student_id' => $this->student->id,
            'fee_settled' => true,
        ]);

        $this->assertDatabaseHas('student_academic_histories', [
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'school_section_id' => $this->section->id,
            'is_current' => true,
        ]);
    }

    public function test_readmission_history(): void
    {
        $dropout = DropoutRecord::factory()->withTenant($this->tenant)->create([
            'student_id' => $this->student->id,
        ]);

        ReadmissionRecord::factory()->withTenant($this->tenant)->count(2)->create([
            'dropout_record_id' => $dropout->id,
            'student_id' => $this->student->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/dropouts/{$dropout->id}/readmissions");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_analytics(): void
    {
        DropoutRecord::factory()->withTenant($this->tenant)->count(5)->create([
            'dropout_category' => 'financial',
            'dropout_date' => now()->format('Y-m-d'),
        ]);

        DropoutRecord::factory()->withTenant($this->tenant)->count(3)->create([
            'dropout_category' => 'family',
            'dropout_date' => now()->format('Y-m-d'),
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/dropouts/analytics?year='.now()->year);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'total_dropouts' => 8,
                ],
            ]);

        $data = $response->json('data');
        $this->assertEquals(5, $data['by_category']['financial']);
        $this->assertEquals(3, $data['by_category']['family']);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/dropouts');

        $response->assertUnauthorized();
    }
}
