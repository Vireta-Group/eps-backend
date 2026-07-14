<?php

namespace Tests\Feature\Api;

use App\Models\Allergy;
use App\Models\EmergencyContact;
use App\Models\HealthCheckup;
use App\Models\MedicalCondition;
use App\Models\Role;
use App\Models\Student;
use App\Models\StudentHealthRecord;
use App\Models\Tenant;
use App\Models\User;
use App\Models\VaccinationRecord;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class HealthTest extends TestCase
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

    public function test_list_health_records(): void
    {
        StudentHealthRecord::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'record_date' => now()->toDateString(),
            'height_cm' => 150.0,
            'weight_kg' => 45.0,
            'recorded_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/records');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_health_record(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/health/records', [
                'student_id' => $this->student->id,
                'record_date' => now()->toDateString(),
                'height_cm' => 150.0,
                'weight_kg' => 45.0,
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'height_cm' => '150.0',
                ],
            ]);

        $this->assertDatabaseHas('student_health_records', [
            'student_id' => $this->student->id,
        ]);
    }

    public function test_growth_chart(): void
    {
        StudentHealthRecord::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'record_date' => now()->subMonths(2)->toDateString(),
            'height_cm' => 148.0,
            'weight_kg' => 43.0,
            'recorded_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        StudentHealthRecord::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'record_date' => now()->subMonths(1)->toDateString(),
            'height_cm' => 149.0,
            'weight_kg' => 44.0,
            'recorded_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/records/growth?student_id='.$this->student->id);

        $response->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_list_vaccinations(): void
    {
        VaccinationRecord::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'vaccine_name' => 'BCG',
            'vaccination_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/vaccinations');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_vaccination(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/health/vaccinations', [
                'student_id' => $this->student->id,
                'vaccine_name' => 'BCG',
                'dose_number' => '1',
                'vaccination_date' => now()->toDateString(),
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'vaccine_name' => 'BCG',
                ],
            ]);
    }

    public function test_due_vaccinations(): void
    {
        VaccinationRecord::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'vaccine_name' => 'Hepatitis B',
            'vaccination_date' => now()->subYear()->toDateString(),
            'next_due_date' => now()->addDays(15)->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/vaccinations/due');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_medical_conditions(): void
    {
        MedicalCondition::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'condition_name' => 'Asthma',
            'description' => 'Mild asthma.',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/medical-conditions');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_medical_condition(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/health/medical-conditions', [
                'student_id' => $this->student->id,
                'condition_name' => 'Asthma',
                'condition_type' => 'chronic',
                'description' => 'Mild persistent asthma.',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'condition_name' => 'Asthma',
                ],
            ]);
    }

    public function test_list_allergies(): void
    {
        Allergy::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'allergy_name' => 'Peanuts',
            'allergy_type' => 'food',
            'severity' => 'critical',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/allergies');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_allergy(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/health/allergies', [
                'student_id' => $this->student->id,
                'allergy_name' => 'Peanuts',
                'allergy_type' => 'food',
                'severity' => 'critical',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'allergy_name' => 'Peanuts',
                ],
            ]);
    }

    public function test_critical_allergies(): void
    {
        Allergy::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'allergy_name' => 'Peanuts',
            'allergy_type' => 'food',
            'severity' => 'critical',
            'created_by' => $this->user->id,
        ]);

        Allergy::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'allergy_name' => 'Dust',
            'allergy_type' => 'environmental',
            'severity' => 'mild',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/allergies/critical');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_list_checkups(): void
    {
        HealthCheckup::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'checkup_date' => now()->toDateString(),
            'checkup_type' => 'annual',
            'doctor_name' => 'Dr. Smith',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/checkups');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_checkup(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/health/checkups', [
                'student_id' => $this->student->id,
                'checkup_date' => now()->toDateString(),
                'checkup_type' => 'annual',
                'doctor_name' => 'Dr. Smith',
                'findings' => 'Healthy',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'checkup_type' => 'annual',
                ],
            ]);
    }

    public function test_emergency_contacts(): void
    {
        EmergencyContact::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'contact_name' => 'John Doe',
            'relationship' => 'Father',
            'mobile' => '1234567890',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson('/api/health/emergency-contacts');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_store_emergency_contact(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/health/emergency-contacts', [
                'student_id' => $this->student->id,
                'contact_name' => 'John Doe',
                'relationship' => 'Father',
                'mobile' => '1234567890',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'contact_name' => 'John Doe',
                ],
            ]);
    }

    public function test_set_primary_contact(): void
    {
        $contact1 = EmergencyContact::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'contact_name' => 'John Doe',
            'relationship' => 'Father',
            'mobile' => '1234567890',
            'is_primary' => true,
            'created_by' => $this->user->id,
        ]);

        $contact2 = EmergencyContact::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'contact_name' => 'Jane Doe',
            'relationship' => 'Mother',
            'mobile' => '0987654321',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->postJson("/api/health/emergency-contacts/{$contact2->id}/set-primary");

        $response->assertOk()
            ->assertJson(['status' => 'success']);

        $this->assertFalse($contact1->fresh()->is_primary);
        $this->assertTrue($contact2->fresh()->is_primary);
    }

    public function test_student_health_summary(): void
    {
        StudentHealthRecord::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'record_date' => now()->toDateString(),
            'height_cm' => 150.0,
            'weight_kg' => 45.0,
            'recorded_by' => $this->user->id,
            'created_by' => $this->user->id,
        ]);

        Allergy::create([
            'tenant_id' => $this->tenant->id,
            'student_id' => $this->student->id,
            'allergy_name' => 'Peanuts',
            'allergy_type' => 'food',
            'severity' => 'critical',
            'created_by' => $this->user->id,
        ]);

        $response = $this->withToken($this->token)
            ->getJson("/api/health/students/{$this->student->id}/summary");

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
                'data' => [
                    'student' => [
                        'id' => $this->student->id,
                    ],
                ],
            ])
            ->assertJsonStructure([
                'status',
                'data' => [
                    'student', 'health_records', 'vaccinations',
                    'medical_conditions', 'allergies', 'checkups', 'emergency_contacts',
                ],
            ]);
    }
}
