<?php

namespace Tests\Feature\Api;

use App\Models\BehaviourIncident;
use App\Models\DisciplinaryAction;
use App\Models\PositiveBehaviour;
use App\Models\Role;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class BehaviourTest extends TestCase
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
        $this->student = Student::factory()->withTenant($this->tenant)->create();
    }

    public function test_list_incidents(): void
    {
        BehaviourIncident::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'incident_date' => now()->toDateString(),
            'incident_type' => 'bullying',
            'description' => 'Student was involved in a bullying incident.',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/behaviour/incidents');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_incident(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/behaviour/incidents', [
                'student_id' => $this->student->id,
                'incident_date' => now()->toDateString(),
                'incident_type' => 'bullying',
                'description' => 'Student was involved in a bullying incident.',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'incident_type' => 'bullying',
                    'severity' => 'moderate',
                ],
            ]);
    }

    public function test_show_incident(): void
    {
        $incident = BehaviourIncident::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'incident_date' => now()->toDateString(),
            'incident_type' => 'bullying',
            'description' => 'Test incident',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/behaviour/incidents/{$incident->id}");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'id' => $incident->id,
                ],
            ]);
    }

    public function test_update_incident(): void
    {
        $incident = BehaviourIncident::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'incident_date' => now()->toDateString(),
            'incident_type' => 'bullying',
            'description' => 'Original description',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/behaviour/incidents/{$incident->id}/update", [
                'description' => 'Updated description',
                'severity' => 'serious',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'description' => 'Updated description',
                    'severity' => 'serious',
                ],
            ]);
    }

    public function test_delete_incident(): void
    {
        $incident = BehaviourIncident::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'incident_date' => now()->toDateString(),
            'incident_type' => 'bullying',
            'description' => 'Test incident',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/behaviour/incidents/{$incident->id}/delete");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertEquals('archived', $incident->fresh()->status);
    }

    public function test_list_actions(): void
    {
        DisciplinaryAction::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'action_date' => now()->toDateString(),
            'action_type' => 'verbal_warning',
            'description' => 'Verbal warning given.',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/behaviour/actions');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_action(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/behaviour/actions', [
                'student_id' => $this->student->id,
                'action_date' => now()->toDateString(),
                'action_type' => 'verbal_warning',
                'description' => 'Student received a verbal warning.',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'action_type' => 'verbal_warning',
                ],
            ]);
    }

    public function test_complete_action(): void
    {
        $action = DisciplinaryAction::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'action_date' => now()->toDateString(),
            'action_type' => 'detention',
            'description' => '3-day detention.',
            'duration_days' => 3,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/behaviour/actions/{$action->id}/complete");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertTrue($action->fresh()->is_completed);
        $this->assertNotNull($action->fresh()->completed_at);
    }

    public function test_list_positive_behaviours(): void
    {
        PositiveBehaviour::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'behaviour_type' => 'helping_others',
            'description' => 'Helped a classmate.',
            'recognition_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/behaviour/positive');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_positive_behaviour(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/behaviour/positive', [
                'student_id' => $this->student->id,
                'behaviour_type' => 'helping_others',
                'description' => 'Helped a classmate with studies.',
                'recognition_date' => now()->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'behaviour_type' => 'helping_others',
                ],
            ]);
    }
}
