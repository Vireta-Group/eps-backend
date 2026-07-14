<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherLeaveApplication;
use App\Models\TeacherLeaveBalance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class LeaveTest extends TestCase
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

        $this->token = $this->user->createToken('test')->plainTextToken;
    }

    private function createTeacher(): Teacher
    {
        $teacherUser = User::factory()->create([
            'tenant_id' => $this->tenant->id,
            'user_type' => 'teacher',
        ]);

        return Teacher::factory()->withTenant($this->tenant)->withUser($teacherUser)->create();
    }

    // ── Leave Types ─────────────────────────────────────────────────────

    public function test_list_leave_types(): void
    {
        LeaveType::factory()->withTenant($this->tenant)->create();

        $response = $this->getJson('/api/leaves/types', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    public function test_create_leave_type(): void
    {
        $response = $this->postJson('/api/leaves/types', [
            'leave_type_name' => 'Annual Leave',
            'default_days' => 20,
            'is_paid' => true,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave type created successfully.',
            ]);

        $this->assertDatabaseHas('leave_types', [
            'leave_type_name' => 'Annual Leave',
            'default_days' => 20,
        ]);
    }

    public function test_update_leave_type(): void
    {
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/leaves/types/'.$leaveType->id.'/update', [
            'leave_type_name' => 'Updated Leave',
            'default_days' => 25,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave type updated successfully.',
            ]);

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'leave_type_name' => 'Updated Leave',
        ]);
    }

    public function test_delete_leave_type(): void
    {
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/leaves/types/'.$leaveType->id.'/delete', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave type deleted successfully.',
            ]);

        $this->assertDatabaseHas('leave_types', [
            'id' => $leaveType->id,
            'is_active' => false,
        ]);
    }

    // ── Leave Balances ──────────────────────────────────────────────────

    public function test_create_leave_balance(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/leaves/balances', [
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'leave_type_id' => $leaveType->id,
            'total_days' => 20,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave balance created successfully.',
            ]);

        $this->assertDatabaseHas('teacher_leave_balances', [
            'teacher_id' => $teacher->id,
            'leave_type_id' => $leaveType->id,
            'total_days' => 20,
            'remaining_days' => 20,
        ]);
    }

    public function test_list_leave_balances(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();

        TeacherLeaveBalance::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'leave_type_id' => $leaveType->id,
            'total_days' => 20,
            'used_days' => 5,
            'remaining_days' => 15,
        ]);

        $response = $this->getJson('/api/leaves/balances', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    // ── Leave Applications ──────────────────────────────────────────────

    public function test_apply_leave(): void
    {
        $teacher = $this->createTeacher();
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/leaves/apply', [
            'teacher_id' => $teacher->id,
            'leave_type_id' => $leaveType->id,
            'academic_year_id' => $year->id,
            'start_date' => today()->addDays(1)->toDateString(),
            'end_date' => today()->addDays(3)->toDateString(),
            'reason' => 'Going on a vacation.',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave application submitted successfully.',
            ]);

        $this->assertDatabaseHas('teacher_leave_applications', [
            'teacher_id' => $teacher->id,
            'status' => 'pending',
        ]);
    }

    public function test_apply_leave_validates_required(): void
    {
        $response = $this->postJson('/api/leaves/apply', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);
    }

    public function test_list_leave_applications(): void
    {
        $teacher = $this->createTeacher();
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        TeacherLeaveApplication::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'leave_type_id' => $leaveType->id,
            'academic_year_id' => $year->id,
        ]);

        $response = $this->getJson('/api/leaves/applications', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    public function test_review_leave_approve(): void
    {
        $teacher = $this->createTeacher();
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $application = TeacherLeaveApplication::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'leave_type_id' => $leaveType->id,
            'academic_year_id' => $year->id,
            'status' => 'pending',
            'total_days' => 3,
        ]);

        $response = $this->postJson('/api/leaves/applications/'.$application->id.'/review', [
            'status' => 'approved',
            'review_remarks' => 'Approved.',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave application reviewed successfully.',
            ]);

        $this->assertDatabaseHas('teacher_leave_applications', [
            'id' => $application->id,
            'status' => 'approved',
        ]);
    }

    public function test_review_leave_reject(): void
    {
        $teacher = $this->createTeacher();
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $application = TeacherLeaveApplication::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'leave_type_id' => $leaveType->id,
            'academic_year_id' => $year->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/leaves/applications/'.$application->id.'/review', [
            'status' => 'rejected',
            'review_remarks' => 'Not enough staff.',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave application reviewed successfully.',
            ]);

        $this->assertDatabaseHas('teacher_leave_applications', [
            'id' => $application->id,
            'status' => 'rejected',
        ]);
    }

    public function test_cancel_leave_application(): void
    {
        $teacher = $this->createTeacher();
        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $application = TeacherLeaveApplication::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'leave_type_id' => $leaveType->id,
            'academic_year_id' => $year->id,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/leaves/applications/'.$application->id.'/cancel', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Leave application cancelled successfully.',
            ]);

        $this->assertDatabaseHas('teacher_leave_applications', [
            'id' => $application->id,
            'status' => 'cancelled',
        ]);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/leaves/types');

        $response->assertUnauthorized();
    }
}
