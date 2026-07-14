<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentTimelineTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $token;

    private Student $student;

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

        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $this->student = Student::factory()->withTenant($this->tenant)->create();
    }

    public function test_can_view_timeline(): void
    {
        $this->student->timelineLogs()->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'student.created',
            'description' => 'Student admitted',
            'created_by' => $this->user->id,
        ]);

        $this->student->timelineLogs()->create([
            'tenant_id' => $this->tenant->id,
            'event_type' => 'document.uploaded',
            'description' => 'Birth certificate uploaded',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/students/{$this->student->student_id}/timeline");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_timeline_unauthenticated_fails(): void
    {
        $response = $this->getJson("/api/students/{$this->student->student_id}/timeline");

        $response->assertUnauthorized();
    }
}
