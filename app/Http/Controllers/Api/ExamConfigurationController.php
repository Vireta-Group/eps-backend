<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ExamType;
use App\Models\GradingSystem;
use App\Models\GradingRule;
use App\Models\SubjectExamConfig;
use App\Services\Exam\GradingService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Exam Management > Configuration', description: 'Manage exam types, grading systems, and subject-specific configs.', weight: 51)]
class ExamConfigurationController extends Controller
{
    public function __construct(
        protected GradingService $gradingService
    ) {}

    /**
     * List all exam types.
     * 
     * @operationId exam.types.index
     */
    public function indexTypes(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $types = ExamType::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $types,
        ]);
    }

    /**
     * Create a new exam type.
     * 
     * @operationId exam.types.store
     */
    public function storeType(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_code' => ['required', 'string', 'max:20', 'unique:exam_types,short_code'],
            'description' => ['nullable', 'string'],
            'category' => ['required', Rule::in(['formative', 'summative'])],
            'count_towards_gpa' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = ExamType::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Exam type created successfully.',
            'data' => $type,
        ], 201);
    }

    /**
     * List all grading systems.
     * 
     * @operationId exam.grading.index
     */
    public function indexGradingSystems(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $systems = GradingSystem::where('tenant_id', $tenant->id)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $systems,
        ]);
    }

    /**
     * Create a grading system.
     * 
     * @operationId exam.grading.store
     */
    public function storeGradingSystem(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(['gpa', 'percentage', 'letter', 'custom'])],
            'is_default' => ['nullable', 'boolean'],
        ]);

        $system = GradingSystem::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Grading system created successfully.',
            'data' => $system,
        ], 201);
    }

    /**
     * Add a rule to a grading system.
     * 
     * @operationId exam.grading.rules.store
     */
    public function storeGradingRule(Request $request, GradingSystem $system): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($system->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'grade_name' => ['required', 'string', 'max:20'],
            'min_mark' => ['required', 'numeric', 'min:0'],
            'max_mark' => ['required', 'numeric', 'min:0'],
            'gpa_point' => ['required', 'numeric', 'min:0', 'max:5'],
            'grade_color' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $rule = GradingRule::create(array_merge($validated, [
            'grading_system_id' => $system->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Grading rule added successfully.',
            'data' => $rule,
        ], 201);
    }

    /**
     * Configure subject-specific exam settings.
     * 
     * @operationId exam.subject.config
     */
    public function storeSubjectConfig(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'subject_id' => ['required', 'uuid'],
            'exam_type_id' => ['required', 'uuid', 'exists:exam_types,id'],
            'grading_system_id' => ['nullable', 'uuid', 'exists:grading_systems,id'],
            'pass_marks' => ['required', 'numeric', 'min:0'],
            'is_mandatory' => ['nullable', 'boolean'],
        ]);

        $config = SubjectExamConfig::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Subject exam configuration saved successfully.',
            'data' => $config,
        ], 201);
    }

    /**
     * Calculate grade for a specific mark.
     * 
     * @operationId exam.grading.calculate
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate([
            'mark' => ['required', 'numeric'],
            'grading_system_id' => ['required', 'uuid', 'exists:grading_systems,id'],
        ]);

        $system = GradingSystem::findOrFail($request->grading_system_id);
        $result = $this->gradingService->calculateGrade((float)$request->mark, $system);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
}
