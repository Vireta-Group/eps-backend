<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Academic Management > Academic Years', description: 'Manage academic years, activate, archive, and copy data.', weight: 41)]
class AcademicYearController extends Controller
{
    private const COPY_SCOPES = [
        'class_section_only',
        'class_section_subject',
        'class_section_subject_routine',
        'all_except_students',
    ];

    private const YEAR_STATUSES = [
        'upcoming', 'active', 'transitioning', 'archived',
    ];

    /**
     * List all academic years for the tenant.
     *
     * Optionally filter by `status` or `is_current`.
     *
     * @operationId academicYear.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = AcademicYear::where('tenant_id', $tenant->id);

        if ($request->filled('status')) {
            $query->where('year_status', $request->status);
        }

        if ($request->filled('is_current')) {
            $query->where('is_current', filter_var($request->is_current, FILTER_VALIDATE_BOOLEAN));
        }

        $years = $query->orderBy('start_date', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $years,
        ]);
    }

    /**
     * Create a new academic year.
     *
     * **Required:** year_name, start_date, end_date
     * **Optional:** year_name_bn, is_current, branch_id, copied_from_year_id, copy_scope
     *
     * @operationId academicYear.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'year_name' => [
                'required', 'string', 'max:100',
                Rule::unique('academic_years', 'year_name')
                    ->where('tenant_id', $tenant->id)
                    ->whereNull('deleted_at'),
            ],
            'year_name_bn' => ['nullable', 'string', 'max:200'],
            'start_date' => ['required', 'date', 'before_or_equal:end_date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_current' => ['nullable', 'boolean'],
            'branch_id' => ['nullable', 'string', 'size:36'],
            'copied_from_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'copy_scope' => ['nullable', 'string', Rule::in(self::COPY_SCOPES)],
        ]);

        $this->validateNoOverlap($tenant->id, $validated['start_date'], $validated['end_date'], null);

        $validated['tenant_id'] = $tenant->id;
        $validated['year_status'] = AcademicYear::YEAR_STATUS_UPCOMING;
        $validated['created_by'] = $request->user()->id;

        if (! empty($validated['is_current'])) {
            $this->deactivateOtherYears($tenant->id, null);
            $validated['year_status'] = AcademicYear::YEAR_STATUS_ACTIVE;
        }

        $year = AcademicYear::create($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Academic year created successfully.',
            'data' => $year->fresh(),
        ], 201);
    }

    /**
     * Get a single academic year.
     *
     * @operationId academicYear.show
     */
    public function show(Request $request, AcademicYear $year): JsonResponse
    {
        $this->ensureTenantOwnership($request, $year);

        return response()->json([
            'status' => 'success',
            'data' => $year,
        ]);
    }

    /**
     * Activate an academic year.
     *
     * Sets the year as current, deactivates all other years for the tenant,
     * and auto-archives the previously active year.
     *
     * @operationId academicYear.activate
     */
    public function activate(Request $request, AcademicYear $year): JsonResponse
    {
        $this->ensureTenantOwnership($request, $year);

        $tenant = $request->user()->tenant;

        $previousActive = AcademicYear::where('tenant_id', $tenant->id)
            ->where('is_current', true)
            ->where('id', '!=', $year->id)
            ->first();

        $this->deactivateOtherYears($tenant->id, $year->id);

        if ($previousActive) {
            $previousActive->update([
                'is_current' => false,
                'year_status' => AcademicYear::YEAR_STATUS_ARCHIVED,
                'is_locked' => true,
                'locked_at' => now(),
                'locked_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
            ]);
        }

        $year->update([
            'is_current' => true,
            'year_status' => AcademicYear::YEAR_STATUS_ACTIVE,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Academic year activated successfully. Previous year has been archived.',
            'data' => $year->fresh(),
        ]);
    }

    /**
     * Archive an academic year.
     *
     * Makes the year read-only (is_locked = true, year_status = archived).
     * If it was current, it will be deactivated.
     *
     * @operationId academicYear.archive
     */
    public function archive(Request $request, AcademicYear $year): JsonResponse
    {
        $this->ensureTenantOwnership($request, $year);

        $year->update([
            'is_current' => false,
            'year_status' => AcademicYear::YEAR_STATUS_ARCHIVED,
            'is_locked' => true,
            'locked_at' => now(),
            'locked_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Academic year archived successfully.',
            'data' => $year->fresh(),
        ]);
    }

    /**
     * Copy structure from a previous academic year.
     *
     * Records the copy source and scope for later processing when
     * Class/Section/Subject modules are implemented.
     *
     * **Required:** copy_scope
     * **Optional:** copied_from_year_id (defaults to latest previous year)
     *
     * @operationId academicYear.copy
     */
    public function copy(Request $request, AcademicYear $year): JsonResponse
    {
        $this->ensureTenantOwnership($request, $year);

        $validated = $request->validate([
            'copy_scope' => ['required', 'string', Rule::in(self::COPY_SCOPES)],
            'copied_from_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
        ]);

        $sourceYearId = $validated['copied_from_year_id'] ?? AcademicYear::where('tenant_id', $year->tenant_id)
            ->where('id', '!=', $year->id)
            ->where('start_date', '<', $year->start_date)
            ->orderBy('start_date', 'desc')
            ->value('id');

        $year->update([
            'copied_from_year_id' => $sourceYearId,
            'copy_scope' => $validated['copy_scope'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Copy preferences saved. Structure will be duplicated when Class/Section modules are available.',
            'data' => $year->fresh(),
        ]);
    }

    private function ensureTenantOwnership(Request $request, AcademicYear $year): void
    {
        $tenant = $request->user()->tenant;

        if ($year->tenant_id !== $tenant->id) {
            abort(404);
        }
    }

    private function validateNoOverlap(string $tenantId, string $startDate, string $endDate, ?string $excludeId): void
    {
        $overlap = AcademicYear::where('tenant_id', $tenantId)
            ->whereDate('start_date', '<=', $endDate)
            ->whereDate('end_date', '>=', $startDate)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists();

        if ($overlap) {
            abort(422, 'The date range overlaps with an existing academic year.');
        }
    }

    private function deactivateOtherYears(string $tenantId, ?string $excludeId): void
    {
        AcademicYear::where('tenant_id', $tenantId)
            ->where('is_current', true)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->update(['is_current' => false]);
    }
}
