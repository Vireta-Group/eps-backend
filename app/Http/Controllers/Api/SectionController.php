<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Academic Management', description: 'Academic years, classes, sections, and subjects.', weight: 40)]
class SectionController extends Controller
{
    /**
     * List all sections for the tenant.
     *
     * Optionally filter by `school_class_id`, `shift`, or `is_active`.
     *
     * @operationId section.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = SchoolSection::where('tenant_id', $tenant->id);

        if ($request->filled('school_class_id')) {
            $query->where('school_class_id', $request->school_class_id);
        }

        if ($request->filled('shift')) {
            $query->where('shift', $request->shift);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $sections = $query->orderBy('section_name')->get();

        return response()->json([
            'status' => 'success',
            'data' => $sections,
        ]);
    }

    /**
     * Create a new section.
     *
     * **Required:** school_class_id, section_name
     * **Optional:** section_name_bn, max_capacity, class_teacher_id, room_number, shift, is_active
     *
     * @operationId section.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'school_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'section_name' => [
                'required', 'string', 'max:100',
                Rule::unique('school_sections', 'section_name')
                    ->where('tenant_id', $tenant->id)
                    ->where('school_class_id', $request->school_class_id)
                    ->whereNull('deleted_at'),
            ],
            'section_name_bn' => ['nullable', 'string', 'max:200'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
            'class_teacher_id' => ['nullable', 'uuid'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'shift' => ['nullable', Rule::in(['morning', 'day', 'evening'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $this->ensureClassOwnership($request, $validated['school_class_id']);

        $validated['tenant_id'] = $tenant->id;
        $validated['created_by'] = $request->user()->id;

        $section = SchoolSection::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section created successfully.',
            'data' => $section->fresh(),
        ], 201);
    }

    /**
     * Get a single section.
     *
     * @operationId section.show
     */
    public function show(Request $request, SchoolSection $section): JsonResponse
    {
        $this->ensureTenantOwnership($request, $section);

        return response()->json([
            'status' => 'success',
            'data' => $section,
        ]);
    }

    /**
     * Update a section.
     *
     * All fields are optional — only send what needs to change.
     *
     * @operationId section.update
     */
    public function update(Request $request, SchoolSection $section): JsonResponse
    {
        $this->ensureTenantOwnership($request, $section);

        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'school_class_id' => ['sometimes', 'required', 'uuid', 'exists:school_classes,id'],
            'section_name' => [
                'sometimes', 'required', 'string', 'max:100',
                Rule::unique('school_sections', 'section_name')
                    ->where('tenant_id', $tenant->id)
                    ->where('school_class_id', $request->school_class_id ?? $section->school_class_id)
                    ->whereNull('deleted_at')
                    ->ignore($section->id),
            ],
            'section_name_bn' => ['nullable', 'string', 'max:200'],
            'max_capacity' => ['nullable', 'integer', 'min:1'],
            'class_teacher_id' => ['nullable', 'uuid'],
            'room_number' => ['nullable', 'string', 'max:50'],
            'shift' => ['nullable', Rule::in(['morning', 'day', 'evening'])],
            'is_active' => ['nullable', 'boolean'],
        ]);

        if ($request->filled('school_class_id')) {
            $this->ensureClassOwnership($request, $validated['school_class_id']);
        }

        $validated['updated_by'] = $request->user()->id;
        $section->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Section updated successfully.',
            'data' => $section->fresh(),
        ]);
    }

    /**
     * Activate a section.
     *
     * Sets `is_active` to `true`.
     *
     * @operationId section.activate
     */
    public function activate(Request $request, SchoolSection $section): JsonResponse
    {
        $this->ensureTenantOwnership($request, $section);

        $section->update([
            'is_active' => true,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Section activated successfully.',
            'data' => $section->fresh(),
        ]);
    }

    /**
     * Inactivate a section.
     *
     * Sets `is_active` to `false`.
     *
     * @operationId section.inactivate
     */
    public function inactivate(Request $request, SchoolSection $section): JsonResponse
    {
        $this->ensureTenantOwnership($request, $section);

        $section->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Section inactivated successfully.',
            'data' => $section->fresh(),
        ]);
    }

    /**
     * Soft-delete a section.
     *
     * Sets `deleted_at`, `deleted_by`, and optional `delete_reason`.
     *
     * @operationId section.delete
     */
    public function delete(Request $request, SchoolSection $section): JsonResponse
    {
        $this->ensureTenantOwnership($request, $section);

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $section->update([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $validated['delete_reason'] ?? null,
        ]);

        $section->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Section deleted successfully.',
        ]);
    }

    private function ensureTenantOwnership(Request $request, SchoolSection $section): void
    {
        $tenant = $request->user()->tenant;

        if ($section->tenant_id !== $tenant->id) {
            abort(404);
        }
    }

    private function ensureClassOwnership(Request $request, string $classId): void
    {
        $exists = SchoolClass::where('tenant_id', $request->user()->tenant->id)
            ->where('id', $classId)
            ->exists();

        if (! $exists) {
            abort(422, 'The selected school class is invalid.');
        }
    }
}
