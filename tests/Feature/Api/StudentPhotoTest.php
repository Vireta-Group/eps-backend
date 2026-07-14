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

class StudentPhotoTest extends TestCase
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

    public function test_can_upload_photo(): void
    {
        $response = $this->withToken($this->token)
            ->postJson("/api/students/{$this->student->student_id}/photo", [
                'photo_url' => 'http://example.com/photo.jpg',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'is_current' => true,
                ],
            ]);

        $this->assertDatabaseHas('student_photos', [
            'student_id' => $this->student->id,
            'is_current' => true,
        ]);
    }

    public function test_upload_archives_previous_photo(): void
    {
        $this->student->photos()->create([
            'tenant_id' => $this->tenant->id,
            'photo_url' => 'http://example.com/old.jpg',
            'is_current' => true,
        ]);

        $this->withToken($this->token)
            ->postJson("/api/students/{$this->student->student_id}/photo", [
                'photo_url' => 'http://example.com/new.jpg',
            ]);

        $oldPhoto = $this->student->photos()->where('photo_url', 'http://example.com/old.jpg')->first();
        $this->assertFalse($oldPhoto->is_current);
        $this->assertNotNull($oldPhoto->archived_at);
    }

    public function test_can_get_current_photo(): void
    {
        $this->student->photos()->create([
            'tenant_id' => $this->tenant->id,
            'photo_url' => 'http://example.com/photo.jpg',
            'is_current' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/students/{$this->student->student_id}/photo");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'photo_url' => 'http://example.com/photo.jpg',
                ],
            ]);
    }

    public function test_can_get_photo_history(): void
    {
        $this->student->photos()->create([
            'tenant_id' => $this->tenant->id,
            'photo_url' => 'http://example.com/old.jpg',
            'is_current' => false,
        ]);

        $this->student->photos()->create([
            'tenant_id' => $this->tenant->id,
            'photo_url' => 'http://example.com/new.jpg',
            'is_current' => true,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/students/{$this->student->student_id}/photos/history");

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }
}
