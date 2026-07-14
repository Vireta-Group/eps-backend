<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\DropoutRecord;
use App\Models\ReadmissionRecord;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Student Management > Dropout', description: 'Record dropouts, readmissions, and analytics.', weight: 58)]
class DropoutController extends Controller
{
    /**
     * List dropout records for the tenant.
     *
     * Filter by student_id, dropout_category, status, date range.
     *
     * @operationId dropout.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = DropoutRecord::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('dropout_category')) {
            $query->where('dropout_category', $request->dropout_category);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('from_date')) {
            $query->whereDate('dropout_date', '>=', $request->from_date);
        }

        if ($request->filled('to_date')) {
            $query->whereDate('dropout_date', '<=', $request->to_date);
        }

        $records = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $records,
        ]);
    }

    /**
     * Record a dropout.
     *
     * Sets the student to inactive.
     *
     * @operationId dropout.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'dropout_date' => ['required', 'date'],
            'dropout_category' => ['required', 'string', 'max:50', 'in:financial,family,distance,employment,marriage,transfer_school,illness,disinterest,unknown,other'],
            'reason' => ['nullable', 'string'],
            'last_attendance_date' => ['nullable', 'date'],
            'supporting_note' => ['nullable', 'string'],
            'fee_due_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $student = Student::where('tenant_id', $tenant->id)
            ->where('id', $validated['student_id'])
            ->firstOrFail();

        if (! $student->is_active) {
            abort(422, 'Student is already inactive.');
        }

        $record = DB::transaction(function () use ($tenant, $validated, $student, $request) {
            $record = DropoutRecord::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'dropout_date' => $validated['dropout_date'],
                'dropout_category' => $validated['dropout_category'],
                'reason' => $validated['reason'] ?? null,
                'last_attendance_date' => $validated['last_attendance_date'] ?? null,
                'supporting_note' => $validated['supporting_note'] ?? null,
                'fee_due_amount' => $validated['fee_due_amount'] ?? 0,
                'created_by' => $request->user()->id,
            ]);

            $student->update([
                'is_active' => false,
                'updated_by' => $request->user()->id,
            ]);

            return $record;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Dropout recorded successfully.',
            'data' => $record,
        ], 201);
    }

    /**
     * Show a single dropout record.
     *
     * @operationId dropout.show
     */
    public function show(Request $request, DropoutRecord $dropoutRecord): JsonResponse
    {
        if ($dropoutRecord->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $dropoutRecord->load(['student', 'readmissions']);

        return response()->json([
            'status' => 'success',
            'data' => $dropoutRecord,
        ]);
    }

    /**
     * Readmit a student from dropout.
     *
     * Creates ReadmissionRecord, creates new StudentAcademicHistory, reactivates student.
     *
     * @operationId dropout.readmit
     */
    public function readmit(Request $request, DropoutRecord $dropoutRecord): JsonResponse
    {
        if ($dropoutRecord->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'readmission_date' => ['required', 'date'],
            'new_academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'new_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'new_section_id' => ['required', 'uuid', 'exists:school_sections,id'],
            'fee_settled' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
        ]);

        $student = $dropoutRecord->student;

        AcademicYear::where('tenant_id', $tenant->id)->where('id', $validated['new_academic_year_id'])->firstOrFail();
        SchoolClass::where('tenant_id', $tenant->id)->where('id', $validated['new_class_id'])->firstOrFail();
        SchoolSection::where('tenant_id', $tenant->id)->where('id', $validated['new_section_id'])->firstOrFail();

        $readmission = DB::transaction(function () use ($tenant, $dropoutRecord, $student, $validated, $request) {
            $readmission = ReadmissionRecord::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'dropout_record_id' => $dropoutRecord->id,
                'readmission_date' => $validated['readmission_date'],
                'reason' => $validated['reason'] ?? null,
                'new_academic_year_id' => $validated['new_academic_year_id'],
                'new_class_id' => $validated['new_class_id'],
                'new_section_id' => $validated['new_section_id'],
                'fee_settled' => $validated['fee_settled'] ?? false,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $student->update([
                'is_active' => true,
                'updated_by' => $request->user()->id,
            ]);

            StudentAcademicHistory::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'academic_year_id' => $validated['new_academic_year_id'],
                'school_class_id' => $validated['new_class_id'],
                'school_section_id' => $validated['new_section_id'],
                'is_current' => true,
                'created_by' => $request->user()->id,
            ]);

            return $readmission;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Student readmitted successfully.',
            'data' => $readmission,
        ], 201);
    }

    /**
     * List readmission attempts for a dropout.
     *
     * @operationId dropout.readmissionHistory
     */
    public function readmissionHistory(Request $request, DropoutRecord $dropoutRecord): JsonResponse
    {
        if ($dropoutRecord->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $readmissions = $dropoutRecord->readmissions()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $readmissions,
        ]);
    }

    /**
     * Dropout analytics.
     *
     * Accepts year filter.
     *
     * @operationId dropout.analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $year = $request->input('year', now()->year);

        $query = DropoutRecord::where('dropout_records.tenant_id', $tenant->id)
            ->whereYear('dropout_date', $year);

        $totalDropouts = (clone $query)->count();

        $byCategory = (clone $query)
            ->select('dropout_category', DB::raw('count(*) as total'))
            ->groupBy('dropout_category')
            ->pluck('total', 'dropout_category');

        $byGender = (clone $query)
            ->join('students', 'dropout_records.student_id', '=', 'students.id')
            ->select('students.gender', DB::raw('count(*) as total'))
            ->groupBy('students.gender')
            ->pluck('total', 'gender');

        $totalReadmissions = ReadmissionRecord::where('tenant_id', $tenant->id)
            ->whereHas('dropoutRecord', function ($q) use ($year) {
                $q->whereYear('dropout_date', $year);
            })
            ->count();

        $readmissionRate = $totalDropouts > 0
            ? round(($totalReadmissions / $totalDropouts) * 100, 2)
            : 0;

        return response()->json([
            'status' => 'success',
            'data' => [
                'year' => (int) $year,
                'total_dropouts' => $totalDropouts,
                'by_category' => $byCategory,
                'by_gender' => $byGender,
                'total_readmissions' => $totalReadmissions,
                'readmission_rate' => $readmissionRate,
            ],
        ]);
    }
}
