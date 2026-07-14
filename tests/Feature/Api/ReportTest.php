<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\BehaviourIncident;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentHealthRecord;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReportTest extends TestCase
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

    public function test_admission_report(): void
    {
        Student::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'admission_date' => now(),
        ]);

        $response = $this->getJson('/api/reports/admission', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonStructure([
            'data' => ['year', 'total_admission', 'monthly', 'by_class', 'by_gender'],
        ]);
    }

    public function test_student_summary(): void
    {
        Student::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        $response = $this->getJson('/api/reports/student-summary', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonStructure([
            'data' => ['total_students', 'active', 'inactive', 'by_class', 'by_gender', 'by_blood_group', 'by_religion'],
        ]);
    }

    public function test_health_summary(): void
    {
        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        StudentHealthRecord::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'record_date' => now(),
            'height_cm' => 150,
            'weight_kg' => 45,
            'bmi' => 20.0,
            'bmi_status' => 'normal',
        ]);

        $response = $this->getJson('/api/reports/health-summary', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonStructure([
            'data' => ['blood_group_distribution', 'vaccination_coverage', 'bmi_distribution', 'common_conditions'],
        ]);
    }

    public function test_attendance_summary(): void
    {
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
            'is_active' => true,
        ]);

        StudentAttendance::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => 'present',
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'school_section_id' => $section->id,
        ]);

        $response = $this->getJson('/api/reports/attendance-summary', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_behaviour_summary(): void
    {
        $student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        BehaviourIncident::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'incident_date' => now()->toDateString(),
            'incident_type' => 'fighting',
            'description' => 'Test incident',
            'severity' => 'moderate',
        ]);

        $response = $this->getJson('/api/reports/behaviour-summary', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/reports/admission');

        $response->assertUnauthorized();
    }
}
