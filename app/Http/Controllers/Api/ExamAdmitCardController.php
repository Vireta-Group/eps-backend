<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdmitCard;
use App\Models\ExamSchedule;
use App\Models\Student;
use App\Services\Exam\AdmitCardService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Exam Management > Admit Cards', description: 'Issue and verify student admit cards.', weight: 64)]
class ExamAdmitCardController extends Controller
{
    public function __construct(
        protected AdmitCardService $admitCardService
    ) {}

    /**
     * List admit cards for an exam schedule.
     *
     * @operationId exam.admitCards.index
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'exam_schedule_id' => ['required', 'uuid', 'exists:exam_schedules,id'],
        ]);

        $cards = AdmitCard::where('tenant_id', $request->user()->tenant->id)
            ->where('exam_schedule_id', $request->query('exam_schedule_id'))
            ->with(['student', 'schedule'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $cards,
        ]);
    }

    /**
     * Issue an admit card for a single student.
     *
     * @operationId exam.admitCards.generate
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'exam_schedule_id' => ['required', 'uuid', 'exists:exam_schedules,id'],
        ]);

        $tenant = $request->user()->tenant;

        $student = Student::where('tenant_id', $tenant->id)->findOrFail($request->student_id);
        $schedule = ExamSchedule::where('tenant_id', $tenant->id)->findOrFail($request->exam_schedule_id);

        $eligibility = $this->admitCardService->checkEligibility($student, $schedule);

        if (! $eligibility['eligible']) {
            return response()->json([
                'status' => 'error',
                'message' => 'Student is not eligible for an admit card.',
                'data' => $eligibility,
            ], 422);
        }

        $card = $this->admitCardService->generateForStudent($tenant->id, $student, $schedule);

        return response()->json([
            'status' => 'success',
            'message' => 'Admit card issued successfully.',
            'data' => $card,
        ], 201);
    }

    /**
     * Issue admit cards for all eligible students of an exam schedule.
     *
     * @operationId exam.admitCards.bulkGenerate
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        $request->validate([
            'exam_schedule_id' => ['required', 'uuid', 'exists:exam_schedules,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['uuid', 'exists:students,id'],
        ]);

        $tenant = $request->user()->tenant;
        $schedule = ExamSchedule::where('tenant_id', $tenant->id)->findOrFail($request->exam_schedule_id);

        $cards = $this->admitCardService->bulkGenerate(
            $tenant->id,
            $schedule,
            $request->input('student_ids', [])
        );

        return response()->json([
            'status' => 'success',
            'message' => $cards->count() . ' admit cards issued successfully.',
            'data' => $cards,
        ]);
    }

    /**
     * Verify an admit card by QR code.
     *
     * @operationId exam.admitCards.verify
     */
    public function verify(Request $request): JsonResponse
    {
        $request->validate([
            'qr_code' => ['required', 'string'],
        ]);

        $card = $this->admitCardService->verify($request->qr_code);

        if (! $card) {
            return response()->json([
                'status' => 'error',
                'message' => 'Admit card not found or invalid.',
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $card,
        ]);
    }
}
