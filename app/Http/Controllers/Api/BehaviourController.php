<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BehaviourIncident;
use App\Models\DisciplinaryAction;
use App\Models\PositiveBehaviour;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Student Management', description: 'Student profiles, guardians, attendance, health, behaviour, dropout, transfer certificates, and reports.', weight: 50)]
class BehaviourController extends Controller
{
    private function ensureTenantOwnership(Request $request, mixed $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }

    public function incidents(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = BehaviourIncident::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->where('incident_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->where('incident_date', '<=', $request->date_to);
        }

        $incidents = $query->with('student', 'reportedBy')
            ->orderBy('incident_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $incidents,
        ]);
    }

    public function storeIncident(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'incident_date' => ['required', 'date'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:200'],
            'incident_type' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'severity' => ['nullable', Rule::in(['minor', 'moderate', 'serious', 'critical'])],
            'involved_students' => ['nullable', 'array'],
            'involved_students.*' => ['uuid', 'exists:students,id'],
            'witnesses' => ['nullable', 'array'],
            'witnesses.*' => ['string', 'max:200'],
            'evidence_urls' => ['nullable', 'array'],
            'evidence_urls.*' => ['url'],
            'reported_by' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $incident = BehaviourIncident::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'incident_date' => $validated['incident_date'],
            'incident_time' => $validated['incident_time'] ?? null,
            'location' => $validated['location'] ?? null,
            'incident_type' => $validated['incident_type'],
            'description' => $validated['description'],
            'severity' => $validated['severity'] ?? 'moderate',
            'involved_students' => $validated['involved_students'] ?? null,
            'witnesses' => $validated['witnesses'] ?? null,
            'evidence_urls' => $validated['evidence_urls'] ?? null,
            'reported_by' => $validated['reported_by'] ?? $request->user()->id,
            'status' => $validated['status'] ?? 'open',
            'created_by' => $request->user()->id,
        ]);

        $incident->load('student', 'reportedBy');

        return response()->json([
            'status' => 'success',
            'message' => 'Incident reported successfully.',
            'data' => $incident,
        ], 201);
    }

    public function showIncident(Request $request, BehaviourIncident $incident): JsonResponse
    {
        $this->ensureTenantOwnership($request, $incident);

        $incident->load('student', 'reportedBy', 'disciplinaryActions');

        return response()->json([
            'status' => 'success',
            'data' => $incident,
        ]);
    }

    public function updateIncident(Request $request, BehaviourIncident $incident): JsonResponse
    {
        $this->ensureTenantOwnership($request, $incident);

        $validated = $request->validate([
            'incident_date' => ['sometimes', 'required', 'date'],
            'incident_time' => ['nullable', 'date_format:H:i'],
            'location' => ['nullable', 'string', 'max:200'],
            'incident_type' => ['sometimes', 'required', 'string', 'max:50'],
            'description' => ['sometimes', 'required', 'string'],
            'severity' => ['nullable', Rule::in(['minor', 'moderate', 'serious', 'critical'])],
            'involved_students' => ['nullable', 'array'],
            'involved_students.*' => ['uuid', 'exists:students,id'],
            'witnesses' => ['nullable', 'array'],
            'witnesses.*' => ['string', 'max:200'],
            'evidence_urls' => ['nullable', 'array'],
            'evidence_urls.*' => ['url'],
            'reported_by' => ['nullable', 'uuid', 'exists:users,id'],
            'status' => ['nullable', 'string', 'max:20'],
        ]);

        $incident->update($validated);

        $incident->load('student', 'reportedBy');

        return response()->json([
            'status' => 'success',
            'message' => 'Incident updated successfully.',
            'data' => $incident->fresh(),
        ]);
    }

    public function deleteIncident(Request $request, BehaviourIncident $incident): JsonResponse
    {
        $this->ensureTenantOwnership($request, $incident);

        $incident->update([
            'status' => 'archived',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Incident archived successfully.',
        ]);
    }

    public function actions(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = DisciplinaryAction::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('action_type')) {
            $query->where('action_type', $request->action_type);
        }

        if ($request->filled('is_completed')) {
            $query->where('is_completed', filter_var($request->is_completed, FILTER_VALIDATE_BOOLEAN));
        }

        $actions = $query->with('student', 'incident', 'takenBy')
            ->orderBy('action_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $actions,
        ]);
    }

    public function storeAction(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'incident_id' => ['nullable', 'uuid', 'exists:behaviour_incidents,id'],
            'action_date' => ['required', 'date'],
            'action_type' => ['required', Rule::in([
                'verbal_warning', 'written_warning', 'parent_meeting', 'detention',
                'suspension', 'community_service', 'expulsion', 'custom_action',
            ])],
            'description' => ['required', 'string'],
            'duration_days' => ['nullable', 'integer', 'min:1'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:action_date'],
            'taken_by' => ['nullable', 'uuid', 'exists:users,id'],
        ]);

        $action = DisciplinaryAction::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'incident_id' => $validated['incident_id'] ?? null,
            'action_date' => $validated['action_date'],
            'action_type' => $validated['action_type'],
            'description' => $validated['description'],
            'duration_days' => $validated['duration_days'] ?? null,
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'taken_by' => $validated['taken_by'] ?? $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        $action->load('student', 'incident', 'takenBy');

        return response()->json([
            'status' => 'success',
            'message' => 'Disciplinary action recorded successfully.',
            'data' => $action,
        ], 201);
    }

    public function completeAction(Request $request, DisciplinaryAction $action): JsonResponse
    {
        $this->ensureTenantOwnership($request, $action);

        $action->update([
            'is_completed' => true,
            'completed_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Action marked as completed.',
            'data' => $action->fresh()->load('student', 'incident'),
        ]);
    }

    public function positiveBehaviours(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = PositiveBehaviour::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $behaviours = $query->with('student', 'recognizedBy')
            ->orderBy('recognition_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $behaviours,
        ]);
    }

    public function storePositiveBehaviour(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'behaviour_type' => ['required', 'string', 'max:50'],
            'description' => ['required', 'string'],
            'reward_given' => ['nullable', 'boolean'],
            'reward_description' => ['nullable', 'string'],
            'recognized_by' => ['nullable', 'uuid', 'exists:users,id'],
            'recognition_date' => ['required', 'date'],
        ]);

        $behaviour = PositiveBehaviour::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'behaviour_type' => $validated['behaviour_type'],
            'description' => $validated['description'],
            'reward_given' => $validated['reward_given'] ?? false,
            'reward_description' => $validated['reward_description'] ?? null,
            'recognized_by' => $validated['recognized_by'] ?? $request->user()->id,
            'recognition_date' => $validated['recognition_date'],
            'created_by' => $request->user()->id,
        ]);

        $behaviour->load('student', 'recognizedBy');

        return response()->json([
            'status' => 'success',
            'message' => 'Positive behaviour recorded successfully.',
            'data' => $behaviour,
        ], 201);
    }
}
