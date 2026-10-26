<?php

namespace Tests\Feature\Api;

use App\Models\ExamCalendar;
use App\Models\ExamSchedule;
use App\Models\ExamType;
use App\Models\GradingRule;
use App\Models\GradingSystem;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ExamManagementTest extends TestCase
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

    private function headers(): array
    {
        return ['Authorization' => 'Bearer '.$this->token];
    }

    private function createExamType(): ExamType
    {
        return ExamType::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Half Yearly',
            'short_code' => 'HY',
            'category' => 'summative',
            'count_towards_gpa' => true,
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);
    }

    private function createGradingSystem(): GradingSystem
    {
        $system = GradingSystem::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Standard GPA',
            'type' => 'gpa',
            'is_default' => true,
            'created_by' => $this->user->id,
        ]);

        GradingRule::create([
            'grading_system_id' => $system->id,
            'grade_name' => 'A+',
            'min_mark' => 80,
            'max_mark' => 100,
            'gpa_point' => 5,
            'grade_color' => '#00AA00',
        ]);

        GradingRule::create([
            'grading_system_id' => $system->id,
            'grade_name' => 'F',
            'min_mark' => 0,
            'max_mark' => 79.99,
            'gpa_point' => 0,
            'grade_color' => '#FF0000',
        ]);

        return $system;
    }

    private function createSchedule(ExamType $examType, Subject $subject): ExamSchedule
    {
        $calendar = ExamCalendar::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Half Yearly 2026',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        return ExamSchedule::create([
            'tenant_id' => $this->tenant->id,
            'exam_calendar_id' => $calendar->id,
            'exam_type_id' => $examType->id,
            'subject_id' => $subject->id,
            'exam_date' => now()->addDays(6)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '13:00',
            'total_marks' => 100,
        ]);
    }

    public function test_can_create_exam_type(): void
    {
        $response = $this->postJson('/api/exam/types', [
            'name' => 'Annual Exam',
            'short_code' => 'AN',
            'category' => 'summative',
            'count_towards_gpa' => true,
        ], $this->headers());

        $response->assertCreated()
            ->assertJson(['status' => 'success']);
    }

    public function test_can_create_exam_calendar_schedule_and_room(): void
    {
        $examType = $this->createExamType();
        $subject = Subject::factory()->withTenant($this->tenant)->create();

        $calendarResponse = $this->postJson('/api/exam/calendars', [
            'name' => 'Half Yearly 2026',
            'start_date' => now()->addDays(5)->toDateString(),
            'end_date' => now()->addDays(10)->toDateString(),
        ], $this->headers());

        $calendarResponse->assertCreated();

        $scheduleResponse = $this->postJson('/api/exam/schedules', [
            'exam_calendar_id' => $calendarResponse->json('data.id'),
            'exam_type_id' => $examType->id,
            'subject_id' => $subject->id,
            'exam_date' => now()->addDays(6)->toDateString(),
            'start_time' => '10:00',
            'end_time' => '13:00',
            'total_marks' => 100,
        ], $this->headers());

        $scheduleResponse->assertCreated();

        $roomResponse = $this->postJson('/api/exam/rooms', [
            'name' => 'Room 101',
            'capacity' => 40,
        ], $this->headers());

        $roomResponse->assertCreated();
    }

    public function test_can_enter_verify_marks_and_publish_results(): void
    {
        $examType = $this->createExamType();
        $subject = Subject::factory()->withTenant($this->tenant)->create();
        $schedule = $this->createSchedule($examType, $subject);
        $gradingSystem = $this->createGradingSystem();

        $student = \App\Models\Student::factory()->withTenant($this->tenant)->create();

        $enterResponse = $this->postJson('/api/exam/marks/bulk', [
            'exam_schedule_id' => $schedule->id,
            'marks' => [
                [
                    'student_id' => $student->id,
                    'theory_marks' => 70,
                    'practical_marks' => 15,
                ],
            ],
        ], $this->headers());

        $enterResponse->assertOk()->assertJson(['status' => 'success']);

        $markId = $enterResponse->json('data.0.id');

        $this->postJson("/api/exam/marks/{$markId}/verify", [], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.status', 'verified');

        $publishResponse = $this->postJson('/api/exam/results/publish', [
            'exam_period' => '2026-Half-Yearly',
            'student_ids' => [$student->id],
            'grading_system_id' => $gradingSystem->id,
        ], $this->headers());

        $publishResponse->assertOk()
            ->assertJson(['status' => 'success'])
            ->assertJsonPath('data.0.result_status', 'passed');
    }

    public function test_can_issue_and_verify_admit_card(): void
    {
        $examType = $this->createExamType();
        $subject = Subject::factory()->withTenant($this->tenant)->create();
        $schedule = $this->createSchedule($examType, $subject);

        $student = \App\Models\Student::factory()->withTenant($this->tenant)->create();

        $response = $this->postJson('/api/exam/admit-cards', [
            'student_id' => $student->id,
            'exam_schedule_id' => $schedule->id,
        ], $this->headers());

        $response->assertCreated()
            ->assertJson(['status' => 'success']);

        $qrCode = $response->json('data.qr_code');

        $this->postJson('/api/exam/admit-cards/verify', [
            'qr_code' => $qrCode,
        ], $this->headers())
            ->assertOk()
            ->assertJsonPath('data.qr_code', $qrCode);
    }
}

