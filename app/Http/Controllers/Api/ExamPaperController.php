<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamPaper;
use App\Services\Exam\QuestionBankService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Exam Management', description: 'Exam configuration, schedules, seating, question bank, papers, admit cards, marks, and results.', weight: 100)]
class ExamPaperController extends Controller
{
    public function __construct(
        protected QuestionBankService $questionBankService
    ) {}

    /**
     * List all exam papers.
     * 
     * @operationId exam.papers.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $papers = ExamPaper::where('tenant_id', $tenant->id)
            ->with('schedule')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $papers,
        ]);
    }

    /**
     * Generate an exam paper from the question bank.
     * 
     * @operationId exam.papers.generate
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'exam_schedule_id' => ['required', 'uuid', 'exists:exam_schedules,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'total_marks' => ['required', 'numeric', 'min:0'],
            'question_count' => ['nullable', 'integer', 'min:1'],
        ]);

        $paper = $this->questionBankService->generatePaper($request->user()->tenant->id, $request->all());

        return response()->json([
            'status' => 'success',
            'message' => 'Exam paper generated successfully.',
            'data' => $paper->load('questions.question.options'),
        ], 201);
    }
}