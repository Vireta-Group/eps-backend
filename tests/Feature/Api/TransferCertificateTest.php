<?php

namespace Tests\Feature\Api;

use App\Models\Role;
use App\Models\Student;
use App\Models\Tenant;
use App\Models\TransferCertificate;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferCertificateTest extends TestCase
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

    public function test_list_tc(): void
    {
        TransferCertificate::factory()->withTenant($this->tenant)->count(3)->create();

        $response = $this->withToken($this->token)
            ->getJson('/api/tc');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_issue_tc(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/tc/issue', [
                'student_id' => $this->student->id,
                'leaving_reason_category' => 'financial',
                'leaving_reason' => 'Unable to pay fees',
                'conduct' => 'good',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertFalse($this->student->fresh()->is_active);

        $this->assertDatabaseHas('transfer_certificates', [
            'student_id' => $this->student->id,
            'leaving_reason_category' => 'financial',
        ]);
    }

    public function test_show_tc(): void
    {
        $tc = TransferCertificate::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->getJson("/api/tc/{$tc->id}");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $tc->id,
                ],
            ]);
    }

    public function test_issue_duplicate_tc(): void
    {
        $tc = TransferCertificate::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/tc/{$tc->id}/duplicate");

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('transfer_certificates', [
            'original_tc_id' => $tc->id,
            'is_duplicate' => true,
        ]);
    }

    public function test_receive_tc(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/tc/receive', [
                'student_id' => $this->student->id,
                'tc_number' => 'TC-2026-00001',
                'previous_school_name' => 'Previous School',
                'previous_school_address' => '123 Old Street',
                'issue_date' => '2026-01-15',
                'scan_url' => 'https://example.com/scan.pdf',
            ]);

        $response->assertCreated()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertDatabaseHas('transfer_certificates', [
            'student_id' => $this->student->id,
            'is_received' => true,
            'previous_school_name' => 'Previous School',
        ]);
    }

    public function test_verify_tc(): void
    {
        $tc = TransferCertificate::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/tc/{$tc->id}/verify");

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'is_valid' => true,
                    'tc_number' => $tc->tc_number,
                ],
            ]);

        $this->assertDatabaseHas('tc_verify_logs', [
            'transfer_certificate_id' => $tc->id,
            'is_valid' => true,
        ]);
    }

    public function test_verify_by_number(): void
    {
        $tc = TransferCertificate::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson('/api/tc/verify-by-number', [
                'tc_number' => $tc->tc_number,
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'is_valid' => true,
                    'tc_number' => $tc->tc_number,
                ],
            ]);
    }

    public function test_verify_by_number_not_found(): void
    {
        $response = $this->withToken($this->token)
            ->postJson('/api/tc/verify-by-number', [
                'tc_number' => 'TC-NONEXISTENT',
            ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'is_valid' => false,
                ],
            ]);
    }

    public function test_cancel_tc(): void
    {
        $tc = TransferCertificate::factory()->withTenant($this->tenant)->create();

        $response = $this->withToken($this->token)
            ->postJson("/api/tc/{$tc->id}/cancel", [
                'cancel_reason' => 'Cancelled by parent request',
            ]);

        $response->assertOk()
            ->assertJson([
                'status' => 'success',
            ]);

        $this->assertEquals('cancelled', $tc->fresh()->status);
        $this->assertNotNull($tc->fresh()->cancelled_at);

        $this->assertTrue($tc->student->fresh()->is_active);
    }

    public function test_auth_required(): void
    {
        $response = $this->getJson('/api/tc');

        $response->assertUnauthorized();
    }
}
