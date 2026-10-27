<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamResult;
use App\Models\ExamSchedule;
use App\Models\GradingSystem;
use App\Models\StudentMark;
use App\Services\Exam\MarksService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Exam Management', description: 'Exam configuration, schedules, seating, question bank, papers, admit cards, marks, and results.', weight: 100)]
class ExamResultController extends Controller
{
    public function __construct(
        protected MarksService $marksService
    ) {}

    /**
     * List entered marks for an exam schedule.
     *
     * @operationId exam.marks.index
     */
    public function indexMarks(Request $request): JsonResponse
    {
        $request->validate([
            'exam_schedule_id' => ['required', 'uuid', 'exists:exam_schedules,id'],
        ]);

        $marks = StudentMark::where('tenant_id', $request->user()->tenant->id)
            ->where('exam_schedule_id', $request->query('exam_schedule_id'))
            ->with(['student', 'schedule'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $marks,
        ]);
    }

    /**
     * Enter marks for multiple students at once.
     *
     * @operationId exam.marks.bulkStore
     */
    public function enterMarks(Request $request): JsonResponse
    {
        $request->validate([
            'exam_schedule_id' => ['required', 'uuid', 'exists:exam_schedules,id'],
            'marks' => ['required', 'array', 'min:1'],
            'marks.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'marks.*.theory_marks' => ['nullable', 'numeric', 'min:0'],
            'marks.*.practical_marks' => ['nullable', 'numeric', 'min:0'],
            'marks.*.pass_marks' => ['nullable', 'numeric', 'min:0'],
        ]);

        $tenant = $request->user()->tenant;
        $schedule = ExamSchedule::where('tenant_id', $tenant->id)->findOrFail($request->exam_schedule_id);

        $marks = $this->marksService->enterMarks(
            $tenant->id,
            $schedule,
            $request->input('marks', []),
            $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => $marks->count() . ' mark entries saved successfully.',
            'data' => $marks,
        ]);
    }

    /**
     * Verify a single mark entry.
     *
     * @operationId exam.marks.verify
     */
    public function verifyMark(Request $request, StudentMark $mark): JsonResponse
    {
        if ($mark->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $mark = $this->marksService->verifyMark($mark, $request->user()->id);

        return response()->json([
            'status' => 'success',
            'message' => 'Mark entry verified successfully.',
            'data' => $mark,
        ]);
    }

    /**
     * List published results.
     *
     * @operationId exam.results.index
     */
    public function indexResults(Request $request): JsonResponse
    {
        $results = ExamResult::where('tenant_id', $request->user()->tenant->id)
            ->when($request->query('exam_period'), fn ($query, $period) => $query->where('exam_period', $period))
            ->with('student')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $results,
        ]);
    }

    /**
     * Publish final results for a set of students.
     *
     * @operationId exam.results.publish
     */
    public function publishResults(Request $request): JsonResponse
    {
        $request->validate([
            'exam_period' => ['required', 'string', 'max:50'],
            'student_ids' => ['required', 'array', 'min:1'],
            'student_ids.*' => ['uuid', 'exists:students,id'],
            'grading_system_id' => ['nullable', 'uuid', 'exists:grading_systems,id'],
        ]);

        $tenant = $request->user()->tenant;

        if ($request->filled('grading_system_id')) {
            $system = GradingSystem::where('tenant_id', $tenant->id)->findOrFail($request->grading_system_id);

            foreach ($request->input('student_ids') as $studentId) {
                StudentMark::where('tenant_id', $tenant->id)
                    ->where('student_id', $studentId)
                    ->where('status', 'verified')
                    ->get()
                    ->each(fn (StudentMark $mark) => $this->marksService->applyGrading($mark, $system));
            }
        }

        $results = $this->marksService->publishResults(
            $tenant->id,
            $request->exam_period,
            $request->input('student_ids'),
            $request->user()->id
        );

        return response()->json([
            'status' => 'success',
            'message' => $results->count() . ' results published successfully.',
            'data' => $results,
        ]);
    }

    /**
     * Show a single published result.
     *
     * @operationId exam.results.show
     */
    public function showResult(Request $request, ExamResult $result): JsonResponse
    {
        if ($result->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result->load('student'),
        ]);
    }
}
