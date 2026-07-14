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

class StudentDocumentTest extends TestCase
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

    public function test_can_upload_document(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$this->student->student_id}/documents", [
                'document_type' => 'birth_certificate',
                'document_name' => 'Birth Certificate',
                'file_url' => 'http://example.com/doc.pdf',
                'issue_date' => '2020-01-01',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'document_type' => 'birth_certificate',
                    'document_name' => 'Birth Certificate',
                ],
            ]);

        $this->assertDatabaseHas('student_documents', [
            'student_id' => $this->student->id,
            'document_type' => 'birth_certificate',
        ]);
    }

    public function test_can_list_documents(): void
    {
        $this->student->documents()->create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'birth_certificate',
            'document_name' => 'Birth Cert',
            'file_url' => 'http://example.com/doc.pdf',
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/students/{$this->student->student_id}/documents");

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_can_delete_document(): void
    {
        $document = $this->student->documents()->create([
            'tenant_id' => $this->tenant->id,
            'document_type' => 'birth_certificate',
            'document_name' => 'Birth Cert',
            'file_url' => 'http://example.com/doc.pdf',
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$this->student->student_id}/documents/{$document->id}/delete");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertNotNull($document->fresh()->deleted_at);
    }
}
