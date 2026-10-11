<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\SalaryStructure;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherPayslip;
use App\Models\TeacherSalary;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalaryTest extends TestCase
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

    // ── Salary Structures ────────────────────────────────────────────────

    public function test_list_salary_structures(): void
    {
        SalaryStructure::factory()->withTenant($this->tenant)->create();

        $response = $this->getJson('/api/salary/structures', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    public function test_create_salary_structure(): void
    {
        $response = $this->postJson('/api/salary/structures', [
            'structure_name' => 'Grade 1',
            'basic_pay' => 50000,
            'house_rent' => 10000,
            'medical_allowance' => 5000,
            'transport_allowance' => 3000,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Salary structure created successfully.',
            ]);

        $this->assertDatabaseHas('salary_structures', [
            'structure_name' => 'Grade 1',
            'basic_pay' => 50000,
        ]);
    }

    public function test_create_salary_structure_validates_required(): void
    {
        $response = $this->postJson('/api/salary/structures', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);
    }

    public function test_update_salary_structure(): void
    {
        $structure = SalaryStructure::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/salary/structures/'.$structure->id.'/update', [
            'structure_name' => 'Updated Grade',
            'basic_pay' => 60000,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Salary structure updated successfully.',
            ]);

        $this->assertDatabaseHas('salary_structures', [
            'id' => $structure->id,
            'structure_name' => 'Updated Grade',
        ]);
    }

    public function test_delete_salary_structure(): void
    {
        $structure = SalaryStructure::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/salary/structures/'.$structure->id.'/delete', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Salary structure deleted successfully.',
            ]);

        $this->assertDatabaseHas('salary_structures', [
            'id' => $structure->id,
            'is_active' => false,
        ]);
    }

    // ── Teacher Salaries ─────────────────────────────────────────────────

    public function test_create_teacher_salary(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/salary/teacher-salaries', [
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'basic_pay' => 50000,
            'house_rent' => 15000,
            'bank_account_no' => '123456789',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Teacher salary created successfully.',
            ]);

        $this->assertDatabaseHas('teacher_salaries', [
            'teacher_id' => $teacher->id,
            'basic_pay' => 50000,
        ]);
    }

    public function test_list_teacher_salaries(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        TeacherSalary::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'basic_pay' => 50000,
            'total' => 65000,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/salary/teacher-salaries', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    // ── Payslips ─────────────────────────────────────────────────────────

    public function test_generate_payslips(): void
    {
        $teacher = $this->createTeacher();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        TeacherSalary::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'basic_pay' => 50000,
            'house_rent' => 15000,
            'total' => 65000,
            'is_active' => true,
        ]);

        $response = $this->postJson('/api/salary/payslips/generate', [
            'month' => now()->month,
            'year' => now()->year,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422)
            ->assertJson(['status' => 'error']);

        $this->postJson('/api/teacher-attendance/finalize', [
            'month' => now()->month,
            'year' => now()->year,
        ], ['Authorization' => 'Bearer '.$this->token])->assertCreated();

        $response = $this->postJson('/api/salary/payslips/generate', [
            'month' => now()->month,
            'year' => now()->year,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_generate_payslips_applies_attendance_deductions(): void
    {
        $teacher = $this->createTeacher();

        TeacherSalary::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'basic_pay' => 50000,
            'house_rent' => 15000,
            'total' => 66000,
            'is_active' => true,
        ]);

        $start = now()->startOfMonth();

        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'attendance_date' => $start->toDateString(),
            'status' => 'present',
        ]);
        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'attendance_date' => $start->copy()->addDay()->toDateString(),
            'status' => 'absent',
        ]);
        TeacherAttendance::factory()->withTenant($this->tenant)->withTeacher($teacher)->create([
            'attendance_date' => $start->copy()->addDays(2)->toDateString(),
            'status' => 'absent',
        ]);

        $this->postJson('/api/teacher-attendance/finalize', [
            'month' => now()->month,
            'year' => now()->year,
        ], ['Authorization' => 'Bearer '.$this->token])->assertCreated();

        $response = $this->postJson('/api/salary/payslips/generate', [
            'month' => now()->month,
            'year' => now()->year,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated();

        $payslip = TeacherPayslip::where('teacher_id', $teacher->id)->firstOrFail();

        $this->assertArrayHasKey('absent', $payslip->deductions);
        $this->assertEquals(2, $payslip->attendance_summary['absent_days']);
        $this->assertGreaterThan(0, (float) $payslip->attendance_deduction);
    }

    public function test_list_payslips(): void
    {
        $teacher = $this->createTeacher();

        TeacherPayslip::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'month' => now()->month,
            'year' => now()->year,
            'basic_pay' => 50000,
            'gross_pay' => 65000,
            'total_deductions' => 5000,
            'net_pay' => 60000,
            'status' => 'pending',
        ]);

        $response = $this->getJson('/api/salary/payslips', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    public function test_mark_payslip_paid(): void
    {
        $teacher = $this->createTeacher();

        $payslip = TeacherPayslip::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'month' => now()->month,
            'year' => now()->year,
            'basic_pay' => 50000,
            'gross_pay' => 65000,
            'total_deductions' => 5000,
            'net_pay' => 60000,
            'status' => 'pending',
        ]);

        $response = $this->postJson('/api/salary/payslips/'.$payslip->id.'/mark-paid', [
            'payment_date' => today()->toDateString(),
            'payment_method' => 'bank_transfer',
            'transaction_id' => 'TXN123456',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Payslip marked as paid successfully.',
            ]);

        $this->assertDatabaseHas('teacher_payslips', [
            'id' => $payslip->id,
            'status' => 'paid',
        ]);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/salary/structures');

        $response->assertUnauthorized();
    }
}
