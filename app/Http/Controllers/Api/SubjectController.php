<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Academic Management > Subjects', description: 'Manage subjects with type (Theory/Practical/Both).', weight: 44)]
class SubjectController extends Controller
{
    /**
     * List all subjects for the tenant.
     *
     * Optionally filter by `is_active` or `subject_type`.
     *
     * @operationId subject.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = Subject::where('tenant_id', $tenant->id);

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->subject_type);
        }

        $subjects = $query->orderBy('subject_name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $subjects,
        ]);
    }

    /**
     * Create a new subject.
     *
     * **Required:** subject_name
     * **Optional:** subject_name_bn, subject_code, subject_type, credit_hour, is_optional, is_active
     *
     * @operationId subject.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'subject_name' => [
                'required', 'string', 'max:100',
                Rule::unique('subjects', 'subject_name')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at'),
            ],
            'subject_name_bn' => ['nullable', 'string', 'max:200'],
            'subject_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('subjects', 'subject_code')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at'),
            ],
            'subject_type' => ['nullable', Rule::in(['theory', 'practical', 'both'])],
            'credit_hour' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'is_optional' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['tenant_id'] = $tenant->id;
        $validated['created_by'] = $request->user()->id;

        $subject = Subject::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Subject created successfully.',
            'data' => $subject->fresh(),
        ], 201);
    }

    /**
     * Get a single subject.
     *
     * @operationId subject.show
     */
    public function show(Request $request, Subject $subject): JsonResponse
    {
        $this->ensureTenantOwnership($request, $subject);

        return response()->json([
            'status' => 'success',
            'data' => $subject,
        ]);
    }

    /**
     * Update a subject.
     *
     * All fields are optional — only send what needs to change.
     *
     * @operationId subject.update
     */
    public function update(Request $request, Subject $subject): JsonResponse
    {
        $this->ensureTenantOwnership($request, $subject);

        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'subject_name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('subjects', 'subject_name')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at')
                    ->ignore($subject->id),
            ],
            'subject_name_bn' => ['nullable', 'string', 'max:200'],
            'subject_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('subjects', 'subject_code')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at')
                    ->ignore($subject->id),
            ],
            'subject_type' => ['nullable', Rule::in(['theory', 'practical', 'both'])],
            'credit_hour' => ['nullable', 'numeric', 'min:0', 'max:99.99'],
            'is_optional' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['updated_by'] = $request->user()->id;
        $subject->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Subject updated successfully.',
            'data' => $subject->fresh(),
        ]);
    }

    /**
     * Activate a subject.
     *
     * Sets `is_active` to `true`.
     *
     * @operationId subject.activate
     */
    public function activate(Request $request, Subject $subject): JsonResponse
    {
        $this->ensureTenantOwnership($request, $subject);

        $subject->update([
            'is_active' => true,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subject activated successfully.',
            'data' => $subject->fresh(),
        ]);
    }

    /**
     * Inactivate a subject.
     *
     * Sets `is_active` to `false`.
     *
     * @operationId subject.inactivate
     */
    public function inactivate(Request $request, Subject $subject): JsonResponse
    {
        $this->ensureTenantOwnership($request, $subject);

        $subject->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subject inactivated successfully.',
            'data' => $subject->fresh(),
        ]);
    }

    /**
     * Soft-delete a subject.
     *
     * Sets `deleted_at`, `deleted_by`, and optional `delete_reason`.
     *
     * @operationId subject.delete
     */
    public function delete(Request $request, Subject $subject): JsonResponse
    {
        $this->ensureTenantOwnership($request, $subject);

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $subject->update([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $validated['delete_reason'] ?? null,
        ]);

        $subject->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Subject deleted successfully.',
        ]);
    }

    private function ensureTenantOwnership(Request $request, Subject $subject): void
    {
        $tenant = $request->user()->tenant;

        if ($subject->tenant_id !== $tenant->id) {
            abort(404);
        }
    }
}
