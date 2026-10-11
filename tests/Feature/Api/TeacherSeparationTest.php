<?php

namespace Tests\Feature\Api;

use App\Models\AcademicYear;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Teacher;
use App\Models\TeacherLeaveBalance;
use App\Models\TeacherSalary;
use App\Models\TeacherSeparation;
use App\Models\TeacherSettlement;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class TeacherSeparationTest extends TestCase
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

    private function auth(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    // ── Separations ─────────────────────────────────────────────────────

    public function test_list_separations(): void
    {
        $teacher = $this->createTeacher();
        TeacherSeparation::factory()->withTeacher($teacher)->create();

        $response = $this->getJson('/api/teacher-separations', $this->auth());

        $response->assertOk()->assertJson(['status' => 'success']);

        $response->assertJsonCount(1, 'data');
    }

    public function test_submit_resignation(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teacher-separations', [
            'teacher_id' => $teacher->id,
            'type' => 'resignation',
            'resignation_date' => now()->toDateString(),
            'last_working_day' => now()->addMonth()->toDateString(),
            'reason' => 'Personal reasons',
            'notice_period_days' => 30,
        ], $this->auth());

        $response->assertCreated()->assertJson(['status' => 'success']);

        $this->assertDatabaseHas('teacher_separations', [
            'teacher_id' => $teacher->id,
            'type' => 'resignation',
            'status' => 'pending',
        ]);
    }

    public function test_submit_termination_requires_type(): void
    {
        $teacher = $this->createTeacher();

        $response = $this->postJson('/api/teacher-separations', [
            'teacher_id' => $teacher->id,
            'type' => 'termination',
            'last_working_day' => now()->toDateString(),
        ], $this->auth());

        $response->assertStatus(422)->assertJson(['status' => 'error']);
    }

    public function test_review_approve_generates_reference_and_deactivates_teacher(): void
    {
        $teacher = $this->createTeacher();
        $separation = TeacherSeparation::factory()->withTeacher($teacher)->create([
            'last_working_day' => now()->subDay()->toDateString(),
        ]);

        $response = $this->postJson('/api/teacher-separations/'.$separation->id.'/review', [
            'status' => 'approved',
        ], $this->auth());

        $response->assertOk()->assertJson(['status' => 'success']);

        $separation->refresh();
        $this->assertSame(TeacherSeparation::STATUS_APPROVED, $separation->status);
        $this->assertNotNull($separation->letter_reference);
        $this->assertNotNull($separation->receipt_no);
        $this->assertFalse($teacher->refresh()->is_active);
    }

    public function test_review_reject_requires_reason(): void
    {
        $teacher = $this->createTeacher();
        $separation = TeacherSeparation::factory()->withTeacher($teacher)->create();

        $response = $this->postJson('/api/teacher-separations/'.$separation->id.'/review', [
            'status' => 'rejected',
        ], $this->auth());

        $response->assertStatus(422);
    }

    public function test_review_reject_stores_reason(): void
    {
        $teacher = $this->createTeacher();
        $separation = TeacherSeparation::factory()->withTeacher($teacher)->create();

        $response = $this->postJson('/api/teacher-separations/'.$separation->id.'/review', [
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient notice',
        ], $this->auth());

        $response->assertOk();

        $this->assertDatabaseHas('teacher_separations', [
            'id' => $separation->id,
            'status' => 'rejected',
            'rejection_reason' => 'Insufficient notice',
        ]);
    }

    public function test_withdraw_pending_separation(): void
    {
        $teacher = $this->createTeacher();
        $separation = TeacherSeparation::factory()->withTeacher($teacher)->create();

        $response = $this->postJson('/api/teacher-separations/'.$separation->id.'/withdraw', [], $this->auth());

        $response->assertOk();

        $this->assertDatabaseHas('teacher_separations', [
            'id' => $separation->id,
            'status' => 'withdrawn',
        ]);
    }

    public function test_separation_documents_lifecycle(): void
    {
        $teacher = $this->createTeacher();
        $separation = TeacherSeparation::factory()->withTeacher($teacher)->create();

        $this->postJson('/api/teacher-separations/'.$separation->id.'/documents', [
            'document_type' => 'warning_letter',
            'title' => 'First warning',
        ], $this->auth())->assertCreated();

        $list = $this->getJson('/api/teacher-separations/'.$separation->id.'/documents', $this->auth());
        $list->assertOk()->assertJsonCount(1, 'data');

        $documentId = $list->json('data.0.id');

        $this->postJson('/api/teacher-separations/'.$separation->id.'/documents/'.$documentId.'/delete', [], $this->auth())
            ->assertOk();

        $this->assertDatabaseMissing('teacher_separation_documents', ['id' => $documentId]);
    }

    public function test_letter_data_after_approval(): void
    {
        $teacher = $this->createTeacher();
        $separation = TeacherSeparation::factory()->withTeacher($teacher)->create([
            'last_working_day' => now()->subDay()->toDateString(),
        ]);

        $this->postJson('/api/teacher-separations/'.$separation->id.'/review', [
            'status' => 'approved',
        ], $this->auth())->assertOk();

        $response = $this->getJson('/api/teacher-separations/'.$separation->id.'/letter', $this->auth());

        $response->assertOk()
            ->assertJsonPath('data.employee_id', $teacher->employee_id)
            ->assertJsonPath('data.type', 'resignation');

        $this->assertNotNull($response->json('data.reference'));
    }

    // ── Final Settlement ────────────────────────────────────────────────

    public function test_settlement_preview_calculates_breakdown(): void
    {
        $teacher = $this->createTeacher();

        TeacherSalary::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'basic_pay' => 30000,
            'total' => 30000,
            'effective_from' => now()->subMonths(2)->toDateString(),
            'is_active' => true,
        ]);

        $leaveType = LeaveType::factory()->withTenant($this->tenant)->create(['is_paid' => true]);
        $year = AcademicYear::factory()->withTenant($this->tenant)->create();

        TeacherLeaveBalance::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'academic_year_id' => $year->id,
            'leave_type_id' => $leaveType->id,
            'total_days' => 10,
            'used_days' => 7,
            'remaining_days' => 3,
        ]);

        $lastWorkingDay = now()->startOfMonth()->addDays(14)->toDateString();

        $response = $this->postJson('/api/teacher-settlements/preview', [
            'teacher_id' => $teacher->id,
            'last_working_day' => $lastWorkingDay,
        ], $this->auth());

        $response->assertOk()->assertJson(['status' => 'success']);

        $data = $response->json('data');
        $this->assertEquals(15000.0, (float) $data['components']['last_month_salary_prorata']);
        $this->assertEquals(3000.0, (float) $data['components']['earned_leave_encashment']);
        $this->assertEquals(18000.0, (float) $data['gross_amount']);
        $this->assertEquals(18000.0, (float) $data['net_amount']);
    }

    public function test_create_settlement_applies_notice_penalty(): void
    {
        $teacher = $this->createTeacher();

        TeacherSalary::create([
            'tenant_id' => $this->tenant->id,
            'teacher_id' => $teacher->id,
            'basic_pay' => 30000,
            'total' => 30000,
            'effective_from' => now()->subMonths(2)->toDateString(),
            'is_active' => true,
        ]);

        $separation = TeacherSeparation::factory()->withTeacher($teacher)->create([
            'notice_penalty_amount' => 500,
        ]);

        $response = $this->postJson('/api/teacher-settlements', [
            'teacher_id' => $teacher->id,
            'separation_id' => $separation->id,
            'last_working_day' => now()->startOfMonth()->addDays(14)->toDateString(),
        ], $this->auth());

        $response->assertCreated()->assertJson(['status' => 'success']);

        $settlement = TeacherSettlement::where('teacher_id', $teacher->id)->firstOrFail();
        $this->assertEquals(15000.0, (float) $settlement->gross_amount);
        $this->assertEquals(500.0, (float) $settlement->total_deductions);
        $this->assertEquals(14500.0, (float) $settlement->net_amount);
    }

    public function test_approve_and_pay_settlement(): void
    {
        $teacher = $this->createTeacher();
        $settlement = TeacherSettlement::factory()->withTeacher($teacher)->create([
            'status' => TeacherSettlement::STATUS_DRAFT,
        ]);

        $this->postJson('/api/teacher-settlements/'.$settlement->id.'/approve', [], $this->auth())
            ->assertOk();

        $settlement->refresh();
        $this->assertSame(TeacherSettlement::STATUS_APPROVED, $settlement->status);
        $this->assertNotNull($settlement->receipt_no);

        $this->postJson('/api/teacher-settlements/'.$settlement->id.'/pay', [
            'payment_method' => 'bank_transfer',
        ], $this->auth())->assertOk();

        $settlement->refresh();
        $this->assertSame(TeacherSettlement::STATUS_PAID, $settlement->status);
        $this->assertNotNull($settlement->paid_at);

        $this->getJson('/api/teacher-settlements/'.$settlement->id.'/receipt', $this->auth())
            ->assertOk()
            ->assertJsonPath('data.payment_method', 'bank_transfer')
            ->assertJsonPath('data.status', 'paid');
    }
}
