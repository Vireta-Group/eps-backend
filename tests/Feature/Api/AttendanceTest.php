<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\AttendancePeriod;
use App\Models\AttendanceRegularization;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class AttendanceTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $token;

    private SchoolClass $class;

    private AcademicYear $year;

    private SchoolSection $section;

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

        $this->class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $this->year = AcademicYear::factory()->withTenant($this->tenant)->create([
            'is_current' => true,
        ]);
        $this->section = SchoolSection::factory()->withClass($this->class)->create();
        $this->student = Student::factory()->withTenant($this->tenant)->create();
    }

    // ── Periods ─────────────────────────────────────────────────────────

    public function test_list_periods(): void
    {
        AttendancePeriod::create([
            'tenant_id' => $this->tenant->id,
            'period_name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'is_active' => true,
            'display_order' => 1,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/attendance/periods');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_period(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/attendance/periods', [
                'period_name' => 'Morning',
                'start_time' => '08:00',
                'end_time' => '10:00',
                'display_order' => 1,
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'period_name' => 'Morning',
                ],
            ]);

        $this->assertDatabaseHas('attendance_periods', [
            'tenant_id' => $this->tenant->id,
            'period_name' => 'Morning',
        ]);
    }

    public function test_update_period(): void
    {
        $period = AttendancePeriod::create([
            'tenant_id' => $this->tenant->id,
            'period_name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'is_active' => true,
            'display_order' => 1,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/attendance/periods/{$period->id}/update", [
                'period_name' => 'Morning Updated',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'period_name' => 'Morning Updated',
                ],
            ]);
    }

    public function test_delete_period(): void
    {
        $period = AttendancePeriod::create([
            'tenant_id' => $this->tenant->id,
            'period_name' => 'Morning',
            'start_time' => '08:00',
            'end_time' => '10:00',
            'is_active' => true,
            'display_order' => 1,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/attendance/periods/{$period->id}/delete");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($period->fresh()->is_active);
    }

    // ── Daily Attendance ────────────────────────────────────────────────

    public function test_mark_attendance_single(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/attendance', [
                'student_id' => $this->student->id,
                'status' => 'present',
                'date' => today()->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'present',
                ],
            ]);

        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $this->student->id,
            'status' => 'present',
        ]);
    }

    public function test_mark_attendance_bulk(): void
    {
        $student2 = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson('/api/attendance', [
                'attendances' => [
                    [
                        'student_id' => $this->student->id,
                        'status' => 'present',
                        'date' => today()->toDateString(),
                    ],
                    [
                        'student_id' => $student2->id,
                        'status' => 'absent',
                        'date' => today()->toDateString(),
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $this->student->id,
            'status' => 'present',
        ]);

        $this->assertDatabaseHas('student_attendances', [
            'student_id' => $student2->id,
            'status' => 'absent',
        ]);
    }

    public function test_mark_bulk_section(): void
    {
        $section = SchoolSection::factory()->withClass($this->class)->create();
        $student1 = Student::factory()->withTenant($this->tenant)->create();
        $student2 = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson('/api/attendance/bulk', [
                'school_section_id' => $section->id,
                'attendance_date' => today()->toDateString(),
                'default_status' => 'present',
            ]);

        $response->assertCreated()
            ->assertJson(['status' => 'success']);
    }

    public function test_show_attendance(): void
    {
        $attendance = StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'school_section_id' => $this->section->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'present',
            'source' => 'manual',
            'marked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/attendance/{$attendance->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $attendance->id,
                    'status' => 'present',
                ],
            ]);
    }

    // ── Regularization ──────────────────────────────────────────────────

    public function test_request_regularization(): void
    {
        $attendance = StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'school_section_id' => $this->section->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'absent',
            'source' => 'manual',
            'marked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson('/api/attendance/regularizations', [
                'attendance_id' => $attendance->id,
                'reason' => 'Was sick, have a doctor note.',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'old_status' => 'absent',
                    'status' => 'pending',
                ],
            ]);
    }

    public function test_review_regularization_approve(): void
    {
        $attendance = StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'school_section_id' => $this->section->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'absent',
            'source' => 'manual',
            'marked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $regularization = AttendanceRegularization::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'attendance_id' => $attendance->id,
            'attendance_date' => today()->toDateString(),
            'old_status' => 'absent',
            'new_status' => 'excused',
            'reason' => 'Was sick.',
            'status' => AttendanceRegularization::STATUS_PENDING,
            'requested_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/attendance/regularizations/{$regularization->id}/review", [
                'status' => 'approved',
                'review_remarks' => 'Valid doctor note provided.',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'approved',
                ],
            ]);

        $this->assertEquals('excused', $attendance->fresh()->status);
    }

    public function test_review_regularization_reject(): void
    {
        $attendance = StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'school_section_id' => $this->section->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'absent',
            'source' => 'manual',
            'marked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $regularization = AttendanceRegularization::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'attendance_id' => $attendance->id,
            'attendance_date' => today()->toDateString(),
            'old_status' => 'absent',
            'new_status' => 'excused',
            'reason' => 'Was sick.',
            'status' => AttendanceRegularization::STATUS_PENDING,
            'requested_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/attendance/regularizations/{$regularization->id}/review", [
                'status' => 'rejected',
                'review_remarks' => 'No valid proof provided.',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'status' => 'rejected',
                ],
            ]);

        $this->assertEquals('absent', $attendance->fresh()->status);
    }

    // ── Fingerprint Devices ─────────────────────────────────────────────

    public function test_list_fingerprint_devices(): void
    {
        FingerprintDevice::create([
            'tenant_id' => $this->tenant->id,
            'device_name' => 'Main Gate',
            'status' => 'active',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/attendance/fingerprint-devices');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_fingerprint_device(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/attendance/fingerprint-devices', [
                'device_name' => 'Main Gate Scanner',
                'ip_address' => '192.168.1.100',
                'port' => 8080,
                'location' => 'Main Entrance',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'device_name' => 'Main Gate Scanner',
                ],
            ]);

        $this->assertDatabaseHas('fingerprint_devices', [
            'tenant_id' => $this->tenant->id,
            'device_name' => 'Main Gate Scanner',
        ]);
    }

    // ── Fingerprint Logs ────────────────────────────────────────────────

    public function test_fingerprint_logs(): void
    {
        FingerprintLog::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'scan_time' => now(),
            'direction' => 'entry',
            'synced' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/attendance/fingerprint-logs');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_fingerprint_log(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/attendance/fingerprint-logs', [
                'student_id' => $this->student->id,
                'scan_time' => now()->toDateTimeString(),
                'direction' => 'entry',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'direction' => 'entry',
                ],
            ]);

        $this->assertDatabaseHas('fingerprint_logs', [
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
        ]);
    }

    // ── Reports ─────────────────────────────────────────────────────────

    public function test_daily_report(): void
    {
        StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'school_section_id' => $this->section->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'present',
            'source' => 'manual',
            'marked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/attendance/reports/daily?date='.today()->toDateString());

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'summary' => [
                        'total' => 1,
                        'present' => 1,
                    ],
                ],
            ]);
    }

    public function test_monthly_report(): void
    {
        StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'academic_year_id' => $this->year->id,
            'school_class_id' => $this->class->id,
            'school_section_id' => $this->section->id,
            'attendance_date' => today()->toDateString(),
            'status' => 'present',
            'source' => 'manual',
            'marked_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $yearMonth = today()->format('Y-m');

        $response = $this->withToken($this->token)
            ->getJson("/api/attendance/reports/monthly?year_month={$yearMonth}");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'year_month' => $yearMonth,
                ],
            ]);
    }

    // ── Auth ────────────────────────────────────────────────────────────

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/attendance');

        $response->assertUnauthorized();
    }
}
