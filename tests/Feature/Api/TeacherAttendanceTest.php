<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceRegularization;
use App\Models\TeacherFingerprint;
use App\Models\TeacherFingerprintLog;
use App\Models\TeacherLeaveApplication;
use App\Models\TeacherSalary;
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

    public function test_bulk_mark_teacher_attendance(): void
    {
        $teacherA = $this->createTeacher();
        $teacherB = $this->createTeacher();

        $response = $this->postJson('/api/teacher-attendance/bulk', [
            'date' => today()->toDateString(),
            'records' => [
                ['teacher_id' => $teacherA->id, 'status' => 'present'],
                ['teacher_id' => $teacherB->id],
            ],
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson(['status' => 'success']);

        $this->assertCount(2, $response->json('data'));
        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacherA->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacherB->id,
            'status' => 'present',
        ]);
    }

    public function test_attendance_sheet_defaults_to_present(): void
    {
        $this->createTeacher();

        $response = $this->getJson('/api/teacher-attendance/sheet', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'default_status' => 'present',
                ],
            ]);

        $this->assertSame('present', $response->json('data.teachers.0.status'));
    }

    public function test_pending_attendance_list(): void
    {
        $marked = $this->createTeacher();
        $unmarked = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($marked)->create([
            'academic_year_id' => $year->id,
            'attendance_date' => today()->toDateString(),
        ]);

        $response = $this->getJson('/api/teacher-attendance/pending', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => ['pending_count' => 1],
            ]);

        $this->assertSame($unmarked->id, $response->json('data.teachers.0.teacher_id'));
    }

    public function test_update_within_edit_window(): void
    {
        $teacher = $this->createTeacher();

        $this->postJson('/api/teacher-attendance', [
            'teacher_id' => $teacher->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'present',
        ], ['Authorization' => 'Bearer '.$this->token])->assertCreated();

        $attendance = TeacherAttendance::where('teacher_id', $teacher->id)->firstOrFail();

        $response = $this->postJson('/api/teacher-attendance/'.$attendance->id.'/update', [
            'status' => 'late',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('teacher_attendances', [
            'id' => $attendance->id,
            'status' => 'late',
            'edit_count' => 1,
        ]);
    }

    public function test_update_after_window_requires_admin(): void
    {
        $teacher = $this->createTeacher();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'status' => 'absent',
            'editable_until' => now()->subHour(),
        ]);

        $plainUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $plainToken = $plainUser->createToken('test')->plainTextToken;

        $response = $this->postJson('/api/teacher-attendance/'.$attendance->id.'/update', [
            'status' => 'present',
            'reason' => 'Correction',
        ], ['Authorization' => 'Bearer '.$plainToken]);

        $response->assertForbidden();
    }

    public function test_update_after_window_as_admin_requires_reason_and_logs_audit(): void
    {
        $teacher = $this->createTeacher();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'status' => 'absent',
            'editable_until' => now()->subHour(),
        ]);

        $withoutReason = $this->postJson('/api/teacher-attendance/'.$attendance->id.'/update', [
            'status' => 'present',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $withoutReason->assertStatus(422);

        $response = $this->postJson('/api/teacher-attendance/'.$attendance->id.'/update', [
            'status' => 'present',
            'reason' => 'Teacher was on official duty',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();

        $this->assertDatabaseHas('teacher_attendances', [
            'id' => $attendance->id,
            'status' => 'present',
        ]);
        $this->assertDatabaseHas('teacher_attendance_edits', [
            'attendance_id' => $attendance->id,
            'reason' => 'Teacher was on official duty',
        ]);
    }

    public function test_audit_trail_endpoint(): void
    {
        $teacher = $this->createTeacher();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create();

        $this->postJson('/api/teacher-attendance/'.$attendance->id.'/update', [
            'status' => 'late',
            'reason' => 'Update',
        ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

        $response = $this->getJson('/api/teacher-attendance/'.$attendance->id.'/audit', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertCount(1, $response->json('data'));
    }

    public function test_mark_accepts_extended_statuses(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teacher-attendance', [
            'teacher_id' => $teacher->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'work_from_home',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'status' => 'work_from_home',
        ]);
    }

    public function test_leave_period_report(): void
    {
        $teacher = $this->createTeacher();

        $application = TeacherLeaveApplication::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'start_date' => today()->toDateString(),
            'end_date' => today()->toDateString(),
            'total_days' => 1,
            'status' => 'approved',
        ]);

        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'academic_year_id' => $application->academic_year_id,
            'attendance_date' => today()->toDateString(),
            'status' => 'on_leave',
            'leave_application_id' => $application->id,
        ]);

        $response = $this->getJson('/api/teacher-attendance/reports/leave-period?from='.today()->toDateString().'&to='.today()->toDateString(), [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.total', 1);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/teacher-attendance');

        $response->assertUnauthorized();
    }

    public function test_finalize_month_locks_attendance(): void
    {
        $teacher = $this->createTeacher();
        $attendance = TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'attendance_date' => now()->startOfMonth()->toDateString(),
            'status' => 'present',
        ]);

        $response = $this->postJson('/api/teacher-attendance/finalize', [
            'month' => now()->month,
            'year' => now()->year,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson(['status' => 'success']);

        $this->assertNotNull($attendance->fresh()->locked_at);

        $this->assertDatabaseHas('teacher_attendance_finalizations', [
            'tenant_id' => $this->tenant->id,
            'month' => now()->month,
            'year' => now()->year,
        ]);
    }

    public function test_finalize_month_twice_conflicts(): void
    {
        $payload = ['month' => now()->month, 'year' => now()->year];
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->postJson('/api/teacher-attendance/finalize', $payload, $headers)->assertCreated();

        $this->postJson('/api/teacher-attendance/finalize', $payload, $headers)
            ->assertStatus(422)
            ->assertJson(['status' => 'error']);
    }

    public function test_cannot_mark_finalized_month(): void
    {
        $teacher = $this->createTeacher();
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->postJson('/api/teacher-attendance/finalize', [
            'month' => now()->month,
            'year' => now()->year,
        ], $headers)->assertCreated();

        $this->postJson('/api/teacher-attendance', [
            'teacher_id' => $teacher->id,
            'attendance_date' => now()->startOfMonth()->toDateString(),
            'status' => 'present',
        ], $headers)->assertStatus(422);
    }

    public function test_finalization_status(): void
    {
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->getJson('/api/teacher-attendance/finalization?month='.now()->month.'&year='.now()->year, $headers)
            ->assertOk()
            ->assertJsonPath('data.is_finalized', false);

        $this->postJson('/api/teacher-attendance/finalize', [
            'month' => now()->month,
            'year' => now()->year,
        ], $headers)->assertCreated();

        $this->getJson('/api/teacher-attendance/finalization?month='.now()->month.'&year='.now()->year, $headers)
            ->assertOk()
            ->assertJsonPath('data.is_finalized', true);
    }

    public function test_salary_preview_returns_adjustments(): void
    {
        $teacher = $this->createTeacher();

        TeacherSalary::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'basic_pay' => 40000,
            'house_rent' => 10000,
            'total' => 50000,
            'is_active' => true,
        ]);

        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'attendance_date' => now()->startOfMonth()->toDateString(),
            'status' => 'present',
        ]);
        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'attendance_date' => now()->startOfMonth()->addDay()->toDateString(),
            'status' => 'absent',
        ]);

        $response = $this->getJson('/api/teacher-attendance/salary-preview?teacher_id='.$teacher->id.'&month='.now()->month.'&year='.now()->year, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonPath('data.summary.absent_days', 1)
            ->assertJsonPath('data.summary.working_days', 2);

        $this->assertGreaterThan(0, (float) $response->json('data.total_deduction'));
    }

    public function test_update_attendance_settings(): void
    {
        $response = $this->postJson('/api/teacher-attendance/settings', [
            'late_deduction_mode' => 'fixed',
            'late_deduction_fixed_amount' => 250,
            'overtime_enabled' => true,
            'overtime_hourly_rate' => 300,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('teacher_attendance_settings', [
            'tenant_id' => $this->tenant->id,
            'late_deduction_mode' => 'fixed',
            'late_deduction_fixed_amount' => 250,
            'overtime_enabled' => true,
            'overtime_hourly_rate' => 300,
        ]);
    }

    public function test_enroll_fingerprint(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teacher-attendance/fingerprints', [
            'teacher_id' => $teacher->id,
            'finger_index' => 1,
            'template_data' => 'TEMPLATE-A',
            'label' => 'Right thumb',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('teacher_fingerprints', [
            'teacher_id' => $teacher->id,
            'finger_index' => 1,
            'is_active' => true,
        ]);
    }

    public function test_enroll_fingerprint_validates_index(): void
    {
        $teacher = $this->createTeacher();

        $this->postJson('/api/teacher-attendance/fingerprints', [
            'teacher_id' => $teacher->id,
            'finger_index' => 3,
        ], ['Authorization' => 'Bearer '.$this->token])->assertStatus(422);
    }

    public function test_list_and_delete_fingerprints(): void
    {
        $teacher = $this->createTeacher();
        $fingerprint = TeacherFingerprint::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'finger_index' => 1,
        ]);

        $this->getJson('/api/teacher-attendance/fingerprints?teacher_id='.$teacher->id, [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->postJson('/api/teacher-attendance/fingerprints/'.$fingerprint->id.'/delete', [], [
            'Authorization' => 'Bearer '.$this->token,
        ])->assertOk();

        $this->assertDatabaseMissing('teacher_fingerprints', ['id' => $fingerprint->id]);
    }

    public function test_punches_create_present_attendance(): void
    {
        $teacher = $this->createTeacher();
        $date = now()->startOfMonth()->addDays(5);

        $response = $this->postJson('/api/teacher-attendance/fingerprint-punches', [
            'punches' => [
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(9, 0)->toDateTimeString(), 'direction' => 'entry'],
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(17, 0)->toDateTimeString(), 'direction' => 'exit'],
            ],
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJsonPath('data.processed', 1);

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $date->toDateString(),
            'status' => 'present',
            'source' => 'fingerprint',
            'work_hours' => 8,
        ]);
    }

    public function test_punches_only_entry_marks_half_day(): void
    {
        $teacher = $this->createTeacher();
        $date = now()->startOfMonth()->addDays(6);

        $this->postJson('/api/teacher-attendance/fingerprint-punches', [
            'punches' => [
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(9, 0)->toDateTimeString(), 'direction' => 'entry'],
            ],
        ], ['Authorization' => 'Bearer '.$this->token])->assertCreated();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $date->toDateString(),
            'status' => 'half_day',
        ]);
    }

    public function test_punches_late_entry_marks_late(): void
    {
        $teacher = $this->createTeacher();
        $date = now()->startOfMonth()->addDays(7);

        $this->postJson('/api/teacher-attendance/settings', [
            'late_after_time' => '09:00',
        ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

        $this->postJson('/api/teacher-attendance/fingerprint-punches', [
            'punches' => [
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(9, 30)->toDateTimeString(), 'direction' => 'entry'],
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(17, 0)->toDateTimeString(), 'direction' => 'exit'],
            ],
        ], ['Authorization' => 'Bearer '.$this->token])->assertCreated();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $date->toDateString(),
            'status' => 'late',
        ]);
    }

    public function test_punches_detect_overtime(): void
    {
        $teacher = $this->createTeacher();
        $date = now()->startOfMonth()->addDays(8);

        $this->postJson('/api/teacher-attendance/settings', [
            'overtime_after_hours' => 8,
        ], ['Authorization' => 'Bearer '.$this->token])->assertOk();

        $this->postJson('/api/teacher-attendance/fingerprint-punches', [
            'punches' => [
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(9, 0)->toDateTimeString(), 'direction' => 'entry'],
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(19, 0)->toDateTimeString(), 'direction' => 'exit'],
            ],
        ], ['Authorization' => 'Bearer '.$this->token])->assertCreated();

        $this->assertDatabaseHas('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $date->toDateString(),
            'work_hours' => 10,
            'overtime_hours' => 2,
        ]);
    }

    public function test_process_punches_skips_finalized_month(): void
    {
        $teacher = $this->createTeacher();
        $date = now()->startOfMonth()->addDays(9);
        $headers = ['Authorization' => 'Bearer '.$this->token];

        $this->postJson('/api/teacher-attendance/finalize', [
            'month' => now()->month,
            'year' => now()->year,
        ], $headers)->assertCreated();

        $response = $this->postJson('/api/teacher-attendance/fingerprint-punches', [
            'punches' => [
                ['teacher_id' => $teacher->id, 'punch_time' => $date->copy()->setTime(9, 0)->toDateTimeString(), 'direction' => 'entry'],
            ],
        ], $headers)->assertCreated();

        $response->assertJsonPath('data.processed', 0);

        $this->assertDatabaseMissing('teacher_attendances', [
            'teacher_id' => $teacher->id,
            'attendance_date' => $date->toDateString(),
        ]);
    }

    public function test_process_punches_backfill_endpoint(): void
    {
        $teacher = $this->createTeacher();
        $date = now()->startOfMonth()->addDays(10);

        TeacherFingerprintLog::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'punch_time' => $date->copy()->setTime(9, 0),
            'processed' => false,
        ]);

        $response = $this->postJson('/api/teacher-attendance/fingerprint-punches/process', [
            'teacher_id' => $teacher->id,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJsonPath('data.processed', 1);
    }
}
