<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Academic Management', description: 'Academic years, classes, sections, and subjects.', weight: 40)]
class ClassController extends Controller
{
    /**
     * List all classes for the tenant.
     *
     * Sorted by `numeric_order` ASC.
     * Optionally filter by `is_active`.
     *
     * @operationId class.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = SchoolClass::where('tenant_id', $tenant->id);

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $classes = $query->orderBy('numeric_order')->get();

        return response()->json([
            'status' => 'success',
            'data' => $classes,
        ]);
    }

    /**
     * Create a new class.
     *
     * `numeric_order` is auto-calculated (max + 1 for the tenant).
     *
     * **Required:** class_name, class_type
     * **Optional:** class_name_bn, is_active
     *
     * @operationId class.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'class_name' => [
                'required', 'string', 'max:100',
                Rule::unique('school_classes', 'class_name')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at'),
            ],
            'class_name_bn' => ['nullable', 'string', 'max:200'],
            'class_type' => ['required', Rule::in(['regular', 'special', 'vocational'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['tenant_id'] = $tenant->id;
        $validated['created_by'] = $request->user()->id;

        $maxOrder = SchoolClass::where('tenant_id', $tenant->id)->max('numeric_order');
        $validated['numeric_order'] = ($maxOrder ?? 0) + 1;

        $class = SchoolClass::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Class created successfully.',
            'data' => $class->fresh(),
        ], 201);
    }

    /**
     * Get a single class.
     *
     * @operationId class.show
     */
    public function show(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->ensureTenantOwnership($request, $schoolClass);

        return response()->json([
            'status' => 'success',
            'data' => $schoolClass,
        ]);
    }

    /**
     * Update a class.
     *
     * All fields are optional — only send what needs to change.
     * `numeric_order` is NOT updated here (use reorder endpoint).
     *
     * @operationId class.update
     */
    public function update(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->ensureTenantOwnership($request, $schoolClass);

        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'class_name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('school_classes', 'class_name')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at')
                    ->ignore($schoolClass->id),
            ],
            'class_name_bn' => ['nullable', 'string', 'max:200'],
            'class_type' => ['sometimes', 'required', Rule::in(['regular', 'special', 'vocational'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $validated['updated_by'] = $request->user()->id;
        $schoolClass->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Class updated successfully.',
            'data' => $schoolClass->fresh(),
        ]);
    }

    /**
     * Activate a class.
     *
     * Sets `is_active` to `true`.
     *
     * @operationId class.activate
     */
    public function activate(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->ensureTenantOwnership($request, $schoolClass);

        $schoolClass->update([
            'is_active' => true,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Class activated successfully.',
            'data' => $schoolClass->fresh(),
        ]);
    }

    /**
     * Inactivate a class.
     *
     * Sets `is_active` to `false`.
     *
     * @operationId class.inactivate
     */
    public function inactivate(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->ensureTenantOwnership($request, $schoolClass);

        $schoolClass->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Class inactivated successfully.',
            'data' => $schoolClass->fresh(),
        ]);
    }

    /**
     * Soft-delete a class.
     *
     * Sets `deleted_at`, `deleted_by`, and optional `delete_reason`.
     * The class will no longer appear in any list or show requests.
     *
     * @operationId class.delete
     */
    public function delete(Request $request, SchoolClass $schoolClass): JsonResponse
    {
        $this->ensureTenantOwnership($request, $schoolClass);

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $schoolClass->update([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $validated['delete_reason'] ?? null,
        ]);

        $schoolClass->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Class deleted successfully.',
        ]);
    }

    private function ensureTenantOwnership(Request $request, SchoolClass $schoolClass): void
    {
        $tenant = $request->user()->tenant;

        if ($schoolClass->tenant_id !== $tenant->id) {
            abort(404);
        }
    }
}
