<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Student;
use App\Models\StudentParent;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ParentPortalTest extends TestCase
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

        $this->token = $this->user->createToken('test')->plainTextToken;

        $this->student = Student::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_link_parent_to_student(): void
    {
        $response = $this->postJson('/api/parent/link', [
            'student_id' => $this->student->id,
            'relationship' => 'father',
            'is_primary' => true,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(201)
            ->assertJson([
                'status' => 'success',
                'message' => 'Parent linked successfully.',
            ]);

        $this->assertDatabaseHas('parent_students', [
            'user_id' => $this->user->id,
            'student_id' => $this->student->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);
    }

    public function test_link_requires_student_id(): void
    {
        $response = $this->postJson('/api/parent/link', [
            'relationship' => 'father',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422);
    }

    public function test_link_requires_valid_relationship(): void
    {
        $response = $this->postJson('/api/parent/link', [
            'student_id' => $this->student->id,
            'relationship' => 'invalid_relation',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422);
    }

    public function test_link_prevents_duplicate(): void
    {
        StudentParent::create([
            'user_id' => $this->user->id,
            'student_id' => $this->student->id,
            'relationship' => 'mother',
        ]);

        $response = $this->postJson('/api/parent/link', [
            'student_id' => $this->student->id,
            'relationship' => 'father',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422);
    }

    public function test_link_validates_student_belongs_to_tenant(): void
    {
        $otherTenant = Tenant::factory()->create();
        $otherStudent = Student::factory()->create([
            'tenant_id' => $otherTenant->id,
        ]);

        $response = $this->postJson('/api/parent/link', [
            'student_id' => $otherStudent->id,
            'relationship' => 'father',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(404);
    }

    public function test_list_children(): void
    {
        StudentParent::create([
            'user_id' => $this->user->id,
            'student_id' => $this->student->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/parent/children', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonFragment(['id' => $this->student->id]);
    }

    public function test_dashboard(): void
    {
        StudentParent::create([
            'user_id' => $this->user->id,
            'student_id' => $this->student->id,
            'relationship' => 'father',
            'is_primary' => true,
        ]);

        $response = $this->getJson('/api/parent/dashboard', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);

        $response->assertJsonFragment(['id' => $this->student->id]);
    }

    public function test_get_notification_prefs(): void
    {
        $response = $this->getJson('/api/parent/notifications', [
            'Authorization' => 'Bearer '.$this->token,
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
            ]);
    }

    public function test_update_notification_prefs(): void
    {
        $response = $this->postJson('/api/parent/notifications', [
            'event_type' => 'attendance_alert',
            'sms' => true,
            'email' => false,
            'push' => true,
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(200)
            ->assertJson([
                'status' => 'success',
                'message' => 'Notification preference updated.',
            ]);

        $this->assertDatabaseHas('parent_notification_prefs', [
            'user_id' => $this->user->id,
            'event_type' => 'attendance_alert',
            'sms' => true,
            'email' => false,
            'push' => true,
        ]);
    }

    public function test_update_notification_prefs_validates_event_type(): void
    {
        $response = $this->postJson('/api/parent/notifications', [
            'event_type' => 'invalid_event',
        ], ['Authorization' => 'Bearer '.$this->token]);

        $response->assertStatus(422);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/parent/children');

        $response->assertStatus(401);
    }
}
