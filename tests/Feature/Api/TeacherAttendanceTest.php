<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceRegularization;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherAttendanceTest extends TestCase
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

    public function test_list_teacher_attendances(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'academic_year_id' => $year->id,
            'attendance_date' => today()->toDateString(),
        ]);

        $response = $this->getJson('/api/teacher-attendance', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    public function test_mark_teacher_attendance(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/teacher-attendance', [
            'teacher_id' => $teacher->id,
            'attendance_date' => today()->toDateString(),
            'check_in_time' => '09:00',
            'check_out_time' => '17:00',
            'status' => 'present',
            'location_name' => 'School Campus',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Teacher attendance marked successfully.',
            ]);

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'present',
        ]);
    }

    public function test_mark_attendance_validates_required(): void
    {
        $response = $this->postJson('/api/teacher-attendance', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);
    }

    public function test_show_teacher_attendance(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'academic_year_id' => $year->id,
        ]);

        $response = $this->getJson('/api/teacher-attendance/'.$attendance->id, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_list_regularizations(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'academic_year_id' => $year->id,
        ]);

        TeacherAttendanceRegularization::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'attendance_id' => $attendance->id,
            'attendance_date' => today()->toDateString(),
            'reason' => 'Was on field trip',
            'status' => 'pending',
            'requested_by' => $this->user->id,
        ]);

        $response = $this->getJson('/api/teacher-attendance/regularizations', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    public function test_request_regularization(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'academic_year_id' => $year->id,
            'status' => 'absent',
        ]);

        $response = $this->postJson('/api/teacher-attendance/regularizations', [
            'attendance_id' => $attendance->id,
            'reason' => 'Was present but forgot to mark',
            'new_status' => 'present',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Regularization request submitted successfully.',
            ]);
    }

    public function test_review_regularization_approve(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'academic_year_id' => $year->id,
            'status' => 'absent',
        ]);

        $regularization = TeacherAttendanceRegularization::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'attendance_id' => $attendance->id,
            'attendance_date' => $attendance->attendance_date,
            'old_status' => 'absent',
            'new_status' => 'present',
            'reason' => 'Was present but forgot to mark',
            'status' => 'pending',
            'requested_by' => $this->user->id,
        ]);

        $response = $this->postJson('/api/teacher-attendance/regularizations/'.$regularization->id.'/review', [
            'status' => 'approved',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Regularization request reviewed successfully.',
            ]);

        $this->assertDatabaseHas('teacher_attendances', [
            'id' => $attendance->id,
            'status' => 'present',
        ]);
    }

    public function test_daily_report(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->present()->create([
            'academic_year_id' => $year->id,
            'attendance_date' => today()->toDateString(),
        ]);

        $response = $this->getJson('/api/teacher-attendance/reports/daily', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'present' => 1,
                    ],
                ],
            ]);
    }

    public function test_monthly_report(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->present()->create([
            'academic_year_id' => $year->id,
            'attendance_date' => today()->toDateString(),
        ]);

        $response = $this->getJson('/api/teacher-attendance/reports/monthly', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/teacher-attendance');

        $response->assertUnauthorized();
    }
}
