<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use DatabaseTransactions;

    private Tenant $tenant;

    private User $user;

    private string $token;

    private SchoolClass $class;

    private AcademicYear $year;

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

        $this->class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $this->year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $this->section = SchoolSection::factory()->withClass($this->class)->create();
    }

    public function test_can_create_student(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/students', [
                'name_en' => 'John Doe',
                'name_bn' => 'জন ডো',
                'father_name' => 'John Father',
                'mother_name' => 'Jane Mother',
                'date_of_birth' => '2010-01-15',
                'gender' => 'male',
                'blood_group' => 'O+',
                'religion' => 'Islam',
                'nationality' => 'Bangladeshi',
                'mobile' => '01712345678',
                'roll_number' => 1,
                'admission_date' => '2026-01-01',
                'academic_year_id' => $this->year->id,
                'school_class_id' => $this->class->id,
                'school_section_id' => $this->section->id,
                'student_type' => 'regular',
                'guardians' => [
                    [
                        'guardian_type' => 'father',
                        'name_en' => 'John Sr.',
                        'mobile' => '01712345679',
                        'is_primary' => true,
                    ],
                    [
                        'guardian_type' => 'mother',
                        'name_en' => 'Jane Doe',
                        'mobile' => '01712345680',
                    ],
                ],
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name_en' => 'John Doe',
                    'gender' => 'male',
                    'is_active' => true,
                ],
            ]);

        $this->assertDatabaseHas('students', [
            'tenant_id' => $this->tenant->id,
            'name_en' => 'John Doe',
        ]);

        $this->assertDatabaseHas('student_guardians', [
            'name_en' => 'John Sr.',
            'guardian_type' => 'father',
        ]);
    }

    public function test_can_list_students(): void
    {
        Student::factory()->withTenant($this->tenant)->count(3)->create();

        $response = $this->withToken($this->token)
            ->getJson('/api/students');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_can_list_students_by_class(): void
    {
        Student::factory()->withTenant($this->tenant)->count(2)->create();

        $otherClass = SchoolClass::factory()->withTenant($this->tenant)->create();
        Student::factory()->withTenant($this->tenant)->create()->academicHistories()->first()->update([
            'school_class_id' => $otherClass->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/students/by-class?class_id='.$this->class->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_list_students_by_section(): void
    {
        Student::factory()->withTenant($this->tenant)->count(2)->create();

        $otherSection = SchoolSection::factory()->withClass($this->class)->create();
        Student::factory()->withTenant($this->tenant)->create()->academicHistories()->first()->update([
            'school_section_id' => $otherSection->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/students/by-section?section_id='.$this->section->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_can_view_single_student(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->getJson("/api/students/{$student->student_id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $student->id,
                ],
            ]);
    }

    public function test_can_update_student_profile(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/update", [
                'name_en' => 'Updated Name',
                'father_name' => 'Updated Father',
                'mother_name' => 'Updated Mother',
                'mobile' => '01812345678',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'name_en' => 'Updated Name',
                    'father_name' => 'Updated Father',
                ],
            ]);

        $this->assertDatabaseHas('students', [
            'id' => $student->id,
            'name_en' => 'Updated Name',
            'mobile' => '01812345678',
        ]);
    }

    public function test_can_update_roll_number(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();
        $currentHistory = $student->academicHistories()->where('is_current', true)->first();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/update", [
                'roll_number' => 99,
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('student_academic_histories', [
            'id' => $currentHistory->id,
            'roll_number' => 99,
        ]);
    }

    public function test_partial_update_does_not_change_other_fields(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create([
            'name_en' => 'Original Name',
            'religion' => 'Islam',
            'nationality' => 'Bangladeshi',
        ]);

        $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/update", [
                'name_en' => 'Changed Name',
            ]);

        $student->refresh();

        $this->assertEquals('Changed Name', $student->name_en);
        $this->assertEquals('Islam', $student->religion);
        $this->assertEquals('Bangladeshi', $student->nationality);
    }

    public function test_update_roll_number_duplicate_fails(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $student2 = Student::factory()->withTenant($this->tenant)->create();
        $history2 = $student2->academicHistories()->where('is_current', true)->first();
        $history2->update(['roll_number' => 50]);

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/update", [
                'roll_number' => 50,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_change_class(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();
        $currentHistory = $student->academicHistories()->where('is_current', true)->first();

        $newClass = SchoolClass::factory()->withTenant($this->tenant)->create([
            'class_name' => 'Class Two',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/change-class", [
                'school_class_id' => $newClass->id,
                'roll_number' => 10,
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('student_academic_histories', [
            'id' => $currentHistory->id,
            'school_class_id' => $newClass->id,
            'roll_number' => 10,
        ]);
    }

    public function test_change_class_same_class_fails(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();
        $currentHistory = $student->academicHistories()->where('is_current', true)->first();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/change-class", [
                'school_class_id' => $currentHistory->school_class_id,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_change_section(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();
        $currentHistory = $student->academicHistories()->where('is_current', true)->first();

        $newSection = SchoolSection::factory()->withClass($this->class)->create([
            'section_name' => 'Section B',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/change-section", [
                'school_section_id' => $newSection->id,
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('student_academic_histories', [
            'id' => $currentHistory->id,
            'school_section_id' => $newSection->id,
        ]);
    }

    public function test_change_section_same_section_fails(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();
        $currentHistory = $student->academicHistories()->where('is_current', true)->first();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/change-section", [
                'school_section_id' => $currentHistory->school_section_id,
            ]);

        $response->assertStatus(422);
    }

    public function test_can_activate_student(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->inactive()->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/activate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue($student->fresh()->is_active);
    }

    public function test_can_inactivate_student(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/inactivate");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($student->fresh()->is_active);
    }

    public function test_can_delete_student(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/delete", [
                'delete_reason' => 'Transferred',
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $student->refresh();

        $this->assertNotNull($student->deleted_at);
        $this->assertEquals($this->user->id, $student->deleted_by);
    }

    public function test_can_assign_section(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $newSection = SchoolSection::factory()->withClass($this->class)->create([
            'section_name' => 'New',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/assign-section", [
                'school_section_id' => $newSection->id,
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_can_set_roll(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/set-roll", [
                'roll_number' => 5,
            ]);

        $response->assertOk()
            ->assertJson(['status' => 'success']);
    }

    public function test_can_add_guardian(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/guardians", [
                'guardian_type' => 'father',
                'name_en' => 'Guardian Name',
                'mobile' => '01712345699',
                'is_primary' => true,
            ]);

        $response->assertCreated()
            ->assertJson(['status' => 'success']);
    }

    public function test_can_set_primary_guardian(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $guardian = StudentGuardian::factory()->create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'guardian_type' => 'father',
            'name_en' => 'Father',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/guardians/{$guardian->id}/primary");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue($guardian->fresh()->is_primary);
    }

    public function test_can_delete_guardian(): void
    {
        $student = Student::factory()->withTenant($this->tenant)->create();

        $guardian = StudentGuardian::factory()->create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $student->id,
            'guardian_type' => 'father',
            'name_en' => 'Father',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$student->student_id}/guardians/{$guardian->id}/delete");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertNotNull($guardian->fresh()->deleted_at);
    }

    public function test_unauthenticated_access_fails(): void
    {
        $response = $this->getJson('/api/students');

        $response->assertUnauthorized();
    }
}
