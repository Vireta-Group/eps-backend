<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Subject;
use App\Models\Teacher;
use App\Models\TeacherDocument;
use App\Models\TeacherExperience;
use App\Models\TeacherQualification;
use App\Models\TeacherTraining;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherTest extends TestCase
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

    public function test_list_teachers(): void
    {
        $this->createTeacher();

        $response = $this->getJson('/api/teachers', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonCount(1, 'data');
    }

    public function test_create_teacher(): void
    {
        $response = $this->postJson('/api/teachers', [
            'name_en' => 'John Doe',
            'name_bn' => 'জন ডো',
            'email' => 'teacher@example.com',
            'phone' => '01712345678',
            'designation' => 'Assistant Teacher',
            'department' => 'Science',
            'employment_type' => 'full_time',
            'mpo_status' => true,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Teacher created successfully.',
            ]);

        $this->assertDatabaseHas('users', [
            'email' => 'teacher@example.com',
            'user_type' => 'teacher',
        ]);

        $this->assertDatabaseHas('teachers', [
            'designation' => 'Assistant Teacher',
        ]);
    }

    public function test_create_teacher_validates_required_fields(): void
    {
        $response = $this->postJson('/api/teachers', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);
    }

    public function test_create_teacher_validates_employment_type(): void
    {
        $response = $this->postJson('/api/teachers', [
            'name_en' => 'John Doe',
            'email' => 'test@example.com',
            'phone' => '01711111111',
            'designation' => 'Teacher',
            'employment_type' => 'invalid_type',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422);
    }

    public function test_show_teacher(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->getJson('/api/teachers/'.$teacher->id, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_update_teacher(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/update', [
            'designation' => 'Senior Teacher',
            'department' => 'Mathematics',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'message' => 'Teacher updated successfully.',
            ]);

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'designation' => 'Senior Teacher',
        ]);
    }

    public function test_activate_teacher(): void
    {
        $teacher = $this->createTeacher();
        $teacher->update(['is_active' => false]);

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/activate', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'is_active' => true,
        ]);
    }

    public function test_inactivate_teacher(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/inactivate', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('teachers', [
            'id' => $teacher->id,
            'is_active' => false,
        ]);
    }

    public function test_list_qualifications(): void
    {
        $teacher = $this->createTeacher();

        TeacherQualification::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'degree' => 'Bachelor of Science',
            'subject' => 'Physics',
            'institution' => 'Dhaka University',
            'passing_year' => 2020,
            'status' => 'verified',
        ]);

        $response = $this->getJson('/api/teachers/'.$teacher->id.'/qualifications', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonCount(1, 'data');
    }

    public function test_add_qualification(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/qualifications', [
            'degree' => 'Masters in Physics',
            'subject' => 'Physics',
            'institution' => 'Dhaka University',
            'passing_year' => 2022,
            'is_highest' => true,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('teacher_qualifications', [
            'teacher_id' => $teacher->id,
            'degree' => 'Masters in Physics',
        ]);
    }

    public function test_delete_qualification(): void
    {
        $teacher = $this->createTeacher();

        $qualification = TeacherQualification::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'degree' => 'Bachelor of Science',
            'status' => 'verified',
        ]);

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/qualifications/'.$qualification->id.'/delete', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('teacher_qualifications', [
            'id' => $qualification->id,
        ]);
    }

    public function test_list_trainings(): void
    {
        $teacher = $this->createTeacher();

        TeacherTraining::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'training_name' => 'ICT in Education',
            'provider' => 'a2i',
            'completion_date' => now(),
        ]);

        $response = $this->getJson('/api/teachers/'.$teacher->id.'/trainings', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_add_training(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/trainings', [
            'training_name' => 'Pedagogy Training',
            'provider' => 'BRAC',
            'duration' => '5 days',
            'completion_date' => now()->toDateString(),
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated();

        $this->assertDatabaseHas('teacher_trainings', [
            'teacher_id' => $teacher->id,
            'training_name' => 'Pedagogy Training',
        ]);
    }

    public function test_delete_training(): void
    {
        $teacher = $this->createTeacher();

        $training = TeacherTraining::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'training_name' => 'Test Training',
        ]);

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/trainings/'.$training->id.'/delete', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('teacher_trainings', ['id' => $training->id]);
    }

    public function test_list_experiences(): void
    {
        $teacher = $this->createTeacher();

        TeacherExperience::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'organization' => 'ABC School',
            'designation' => 'Teacher',
            'from_date' => '2020-01-01',
            'to_date' => '2023-12-31',
            'is_current' => false,
        ]);

        $response = $this->getJson('/api/teachers/'.$teacher->id.'/experiences', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_add_experience(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/experiences', [
            'organization' => 'XYZ International School',
            'designation' => 'Senior Teacher',
            'from_date' => '2024-01-01',
            'is_current' => true,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated();

        $this->assertDatabaseHas('teacher_experiences', [
            'teacher_id' => $teacher->id,
            'organization' => 'XYZ International School',
            'is_current' => true,
        ]);
    }

    public function test_delete_experience(): void
    {
        $teacher = $this->createTeacher();

        $experience = TeacherExperience::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'organization' => 'Test School',
        ]);

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/experiences/'.$experience->id.'/delete', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertDatabaseMissing('teacher_experiences', ['id' => $experience->id]);
    }

    public function test_list_documents(): void
    {
        $teacher = $this->createTeacher();

        TeacherDocument::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'document_type' => 'nid',
            'file_url' => 'http://example.com/nid.pdf',
            'verification_status' => 'pending',
        ]);

        $response = $this->getJson('/api/teachers/'.$teacher->id.'/documents', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_add_document(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/documents', [
            'document_type' => 'nid',
            'file_url' => 'http://example.com/nid.pdf',
            'expiry_date' => '2030-12-31',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated();

        $this->assertDatabaseHas('teacher_documents', [
            'teacher_id' => $teacher->id,
            'document_type' => 'nid',
        ]);
    }

    public function test_verify_document(): void
    {
        $teacher = $this->createTeacher();

        $document = TeacherDocument::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'document_type' => 'nid',
            'file_url' => 'http://example.com/nid.pdf',
            'verification_status' => 'pending',
        ]);

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/documents/'.$document->id.'/verify', [
            'verification_status' => 'verified',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertOk();

        $this->assertDatabaseHas('teacher_documents', [
            'id' => $document->id,
            'verification_status' => 'verified',
        ]);
    }

    public function test_delete_document(): void
    {
        $teacher = $this->createTeacher();

        $document = TeacherDocument::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'document_type' => 'nid',
            'file_url' => 'http://example.com/nid.pdf',
        ]);

        $response = $this->postJson('/api/teachers/'.$teacher->id.'/documents/'.$document->id.'/delete', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertSoftDeleted('teacher_documents', ['id' => $document->id]);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/teachers');

        $response->assertUnauthorized();
    }

    public function test_tenant_scoped(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id]);
        $otherTeacher = Teacher::factory()->withTenant($otherTenant)->withUser($otherUser)->create();

        $response = $this->getJson('/api/teachers/'.$otherTeacher->id, [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(404);
    }

    // ── Phase 2: Subject & Section Assignment ────────────────────────────

    public function test_assign_subject_teacher(): void
    {
        $teacher = $this->createTeacher();
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $subject = Subject::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/teachers/assign-subject', [
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'school_section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Subject teacher assigned successfully.',
            ]);

        $this->assertDatabaseHas('subject_teacher_assignments', [
            'teacher_id' => $teacher->id,
            'subject_id' => $subject->id,
            'is_active' => true,
        ]);
    }

    public function test_assign_subject_teacher_validates_required(): void
    {
        $response = $this->postJson('/api/teachers/assign-subject', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(422);
    }

    public function test_list_subject_assignments(): void
    {
        $teacher = $this->createTeacher();
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $subject = Subject::factory()->withTenant($this->tenant)->create();

        $this->postJson('/api/teachers/assign-subject', [
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'school_section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response = $this->getJson('/api/teachers/'.$teacher->id.'/subject-assignments', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonCount(1, 'data');
    }

    public function test_remove_subject_assignment(): void
    {
        $teacher = $this->createTeacher();
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();
        $subject = Subject::factory()->withTenant($this->tenant)->create();

        $assignResponse = $this->postJson('/api/teachers/assign-subject', [
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'school_section_id' => $section->id,
            'subject_id' => $subject->id,
            'teacher_id' => $teacher->id,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $assignmentId = $assignResponse->json('data.id');

        $response = $this->postJson('/api/teachers/subject-assignments/'.$assignmentId.'/remove', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('subject_teacher_assignments', [
            'id' => $assignmentId,
            'is_active' => false,
        ]);
    }

    public function test_assign_class_teacher(): void
    {
        $teacher = $this->createTeacher();
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/teachers/assign-class-teacher', [
            'academic_year_id' => $year->id,
            'school_section_id' => $section->id,
            'teacher_id' => $teacher->id,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'message' => 'Class teacher assigned successfully.',
            ]);

        $this->assertDatabaseHas('class_teacher_assignments', [
            'teacher_id' => $teacher->id,
            'school_section_id' => $section->id,
            'is_active' => true,
        ]);
    }

    public function test_list_class_teacher_assignments(): void
    {
        $teacher = $this->createTeacher();
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $this->postJson('/api/teachers/assign-class-teacher', [
            'academic_year_id' => $year->id,
            'school_section_id' => $section->id,
            'teacher_id' => $teacher->id,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response = $this->getJson('/api/teachers/class-teacher-assignments', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonCount(1, 'data');
    }

    public function test_remove_class_teacher_assignment(): void
    {
        $teacher = $this->createTeacher();
        $class = SchoolClass::factory()->withTenant($this->tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        $assignResponse = $this->postJson('/api/teachers/assign-class-teacher', [
            'academic_year_id' => $year->id,
            'school_section_id' => $section->id,
            'teacher_id' => $teacher->id,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $assignmentId = $assignResponse->json('data.id');

        $response = $this->postJson('/api/teachers/class-teacher-assignments/'.$assignmentId.'/remove', [], [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk();

        $this->assertDatabaseHas('class_teacher_assignments', [
            'id' => $assignmentId,
            'is_active' => false,
        ]);
    }

    public function test_workload(): void
    {
        $this->createTeacher();

        $response = $this->getJson('/api/teachers/workload', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonCount(1, 'data');
    }
}
