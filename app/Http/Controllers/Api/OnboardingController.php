<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CandidateApplication;
use App\Models\OnboardingChecklist;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Recruitment & Onboarding > Onboarding', description: 'Manage the onboarding process for newly hired staff.', weight: 67)]
class OnboardingController extends Controller
{
    /**
     * List onboarding tasks for a candidate.
     *
     * @operationId onboarding.tasksIndex
     */
    public function index(Request $request, CandidateApplication $application): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($application->tenant_id !== $tenant->id) abort(404);

        return response()->json([
            'status' => 'success',
            'data' => $application->onboardingChecklist()->orderBy('created_at', 'asc')->get(),
        ]);
    }

    /**
     * Add a task to the onboarding checklist.
     *
     * @operationId onboarding.addTask
     */
    public function store(Request $request, CandidateApplication $application): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($application->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'task_name' => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:50'],
        ]);

        $task = OnboardingChecklist::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'candidate_application_id' => $application->id,
            'is_completed' => false,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Onboarding task added.',
            'data' => $task,
        ], 201);
    }

    /**
     * Mark a task as completed.
     *
     * @operationId onboarding.completeTask
     */
    public function complete(Request $request, OnboardingChecklist $task): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($task->tenant_id !== $tenant->id) abort(404);

        $task->update([
            'is_completed' => true,
            'completed_at' => now(),
            'completed_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Task marked as completed.',
            'data' => $task->fresh(),
        ]);
    }

    /**
     * Delete a task from the checklist.
     *
     * @operationId onboarding.deleteTask
     */
    public function destroy(Request $request, OnboardingChecklist $task): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($task->tenant_id !== $tenant->id) abort(404);

        $task->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Task deleted successfully.',
        ]);
    }
}
