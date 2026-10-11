<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceSetting;
use App\Models\TeacherAttendanceRegularization;
use App\Models\TeacherAttendanceFinalization;
use App\Models\TeacherFingerprint;
use App\Models\TeacherFingerprintLog;
use App\Models\TeacherSalary;
use App\Services\TeacherAttendanceService;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Teacher Management > Attendance', weight: 62)]
class TeacherAttendanceController extends Controller
{
    public function __construct(private TeacherAttendanceService $attendance)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherAttendance::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'markedBy']);

        if ($request->filled('date')) {
            $query->where('attendance_date', $request->date);
        }

        if ($request->filled('from')) {
            $query->where('attendance_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('attendance_date', '<=', $request->to);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->orderBy('attendance_date', 'desc')->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $attendances,
        ]);
    }

    public function mark(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'attendance_date' => ['required', 'date'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i', 'after_or_equal:check_in_time'],
            'status' => ['nullable', Rule::in(TeacherAttendance::STATUSES)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(['manual', 'fingerprint', 'mobile'])],
            'remarks' => ['nullable', 'string'],
        ]);

        if ($error = $this->finalizedMonthError($request, $validated['attendance_date'])) {
            return $error;
        }

        $record = $this->attendance->mark($tenant, $request->user(), $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher attendance marked successfully.',
            'data' => $record->fresh()->load(['teacher.user', 'markedBy']),
        ], 201);
    }

    private function finalizedMonthError(Request $request, string $date): ?JsonResponse
    {
        $moment = Carbon::parse($date);

        if ($this->attendance->isMonthFinalized($request->user()->tenant, (int) $moment->month, (int) $moment->year)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance for this month has been finalized and can no longer be changed.',
            ], 422);
        }

        return null;
    }
    /**
     * Bulk mark attendance for several teachers on one date.
     *
     * Default status is Present for records with no status supplied.
     *
     * @operationId teacherAttendance.bulk
     */
    public function bulk(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'records' => ['required', 'array', 'min:1'],
            'records.*.teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'records.*.status' => ['nullable', Rule::in(TeacherAttendance::STATUSES)],
            'records.*.check_in_time' => ['nullable', 'date_format:H:i'],
            'records.*.check_out_time' => ['nullable', 'date_format:H:i'],
            'records.*.remarks' => ['nullable', 'string'],
            'records.*.source' => ['nullable', Rule::in(['manual', 'fingerprint', 'mobile'])],
        ]);

        if ($error = $this->finalizedMonthError($request, $validated['date'])) {
            return $error;
        }

        $records = $this->attendance->bulkMark($tenant, $request->user(), $validated['date'], $validated['records']);

        return response()->json([
            'status' => 'success',
            'message' => count($records).' attendance record(s) marked successfully.',
            'data' => $records,
        ], 201);
    }

    /**
     * Attendance sheet: active teachers with their current status pre-filled.
     *
     * Used to render the daily marking grid (defaults to Present).
     *
     * @operationId teacherAttendance.sheet
     */
    public function sheet(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $date = $request->input('date', today()->toDateString());

        $teachers = Teacher::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('user')
            ->orderBy('department')
            ->orderBy('employee_id')
            ->get();

        $existing = TeacherAttendance::where('tenant_id', $tenant->id)
            ->where('attendance_date', $date)
            ->get()
            ->keyBy('teacher_id');

        $settings = $this->attendance->settingsFor($tenant);

        $rows = $teachers->map(fn (Teacher $teacher) => [
            'teacher_id' => $teacher->id,
            'employee_id' => $teacher->employee_id,
            'name' => $teacher->user?->name_en ?? $teacher->user?->name_bn,
            'designation' => $teacher->designation,
            'department' => $teacher->department,
            'attendance_id' => $existing->get($teacher->id)?->id,
            'status' => $existing->get($teacher->id)?->status ?? $settings->default_status,
            'check_in_time' => $existing->get($teacher->id)?->check_in_time,
            'check_out_time' => $existing->get($teacher->id)?->check_out_time,
        ])->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'date' => $date,
                'default_status' => $settings->default_status,
                'teachers' => $rows,
            ],
        ]);
    }

    /**
     * List active teachers whose attendance has not been recorded on a date.
     *
     * @operationId teacherAttendance.pending
     */
    public function pending(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $date = $request->input('date', today()->toDateString());

        $markedIds = TeacherAttendance::where('tenant_id', $tenant->id)
            ->where('attendance_date', $date)
            ->pluck('teacher_id');

        $pending = Teacher::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->whereNotIn('id', $markedIds)
            ->with('user')
            ->orderBy('employee_id')
            ->get()
            ->map(fn (Teacher $teacher) => [
                'teacher_id' => $teacher->id,
                'employee_id' => $teacher->employee_id,
                'name' => $teacher->user?->name_en ?? $teacher->user?->name_bn,
                'department' => $teacher->department,
            ])->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'date' => $date,
                'pending_count' => $pending->count(),
                'teachers' => $pending,
            ],
        ]);
    }

    /**
     * Correct an attendance record.
     *
     * Allowed within the tenant edit window. Once the window closes, only
     * administrators/HR may edit and a reason is required (audit-logged).
     *
     * @operationId teacherAttendance.update
     */
    public function update(Request $request, TeacherAttendance $attendance): JsonResponse
    {
        if ($attendance->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        if ($attendance->isLocked()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance has been finalized and can no longer be edited.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['sometimes', Rule::in(TeacherAttendance::STATUSES)],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $withinWindow = $attendance->isEditWindowOpen();

        if (! $withinWindow) {
            if (! $this->canManageAttendance($request->user())) {
                abort(403, 'Attendance edit window has closed. Only administrators may edit.');
            }

            if (blank($validated['reason'] ?? null)) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A reason is required when editing attendance after the edit window.',
                ], 422);
            }
        }

        $attendance = $this->attendance->edit(
            $attendance,
            Arr::except($validated, ['reason']),
            $request->user(),
            $validated['reason'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher attendance updated successfully.',
            'data' => $attendance->load(['teacher.user', 'markedBy']),
        ]);
    }

    /**
     * View the edit history/audit trail for an attendance record.
     *
     * @operationId teacherAttendance.audit
     */
    public function audit(Request $request, TeacherAttendance $attendance): JsonResponse
    {
        if ($attendance->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $attendance->edits()->with('editedBy')->latest('created_at')->get(),
        ]);
    }

    public function show(Request $request, TeacherAttendance $attendance): JsonResponse
    {
        if ($attendance->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $attendance->load(['teacher.user', 'markedBy']);

        return response()->json([
            'status' => 'success',
            'data' => $attendance,
        ]);
    }

    public function regularizations(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherAttendanceRegularization::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'attendance', 'reviewedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $regularizations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $regularizations,
        ]);
    }

    public function requestRegularization(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'attendance_id' => ['nullable', 'uuid', 'exists:teacher_attendances,id'],
            'attendance_date' => ['required_without:attendance_id', 'nullable', 'date'],
            'teacher_id' => ['required_without:attendance_id', 'nullable', 'uuid', 'exists:teachers,id'],
            'check_in_time' => ['nullable', 'date_format:H:i'],
            'check_out_time' => ['nullable', 'date_format:H:i'],
            'new_status' => ['nullable', Rule::in(['present', 'absent', 'late', 'half_day', 'excused'])],
            'reason' => ['required', 'string', 'max:1000'],
            'supporting_doc_url' => ['nullable', 'url', 'max:500'],
        ]);

        $attendance = null;
        $oldStatus = null;

        if ($validated['attendance_id']) {
            $attendance = TeacherAttendance::where('tenant_id', $tenant->id)
                ->where('id', $validated['attendance_id'])
                ->firstOrFail();

            $oldStatus = $attendance->status;
            $teacherId = $attendance->teacher_id;
            $attendanceDate = $attendance->attendance_date;
        } else {
            $teacherId = $validated['teacher_id'];

            Teacher::where('tenant_id', $tenant->id)
                ->where('id', $teacherId)
                ->firstOrFail();

            $attendanceDate = $validated['attendance_date'];

            $attendance = TeacherAttendance::where('tenant_id', $tenant->id)
                ->where('teacher_id', $teacherId)
                ->where('attendance_date', $attendanceDate)
                ->first();

            $oldStatus = $attendance?->status;
        }

        $regularization = TeacherAttendanceRegularization::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => $teacherId,
            'attendance_id' => $attendance?->id,
            'attendance_date' => $attendanceDate,
            'check_in_time' => $validated['check_in_time'] ?? null,
            'check_out_time' => $validated['check_out_time'] ?? null,
            'old_status' => $oldStatus,
            'new_status' => $validated['new_status'] ?? 'present',
            'reason' => $validated['reason'],
            'supporting_doc_url' => $validated['supporting_doc_url'] ?? null,
            'status' => TeacherAttendanceRegularization::STATUS_PENDING,
            'requested_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Regularization request submitted successfully.',
            'data' => $regularization->fresh()->load(['teacher.user', 'attendance']),
        ], 201);
    }

    public function reviewRegularization(Request $request, TeacherAttendanceRegularization $regularization): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($regularization->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([TeacherAttendanceRegularization::STATUS_APPROVED, TeacherAttendanceRegularization::STATUS_REJECTED])],
            'review_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($regularization, $validated, $request) {
            $regularization->update([
                'status' => $validated['status'],
                'review_remarks' => $validated['review_remarks'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if ($validated['status'] === TeacherAttendanceRegularization::STATUS_APPROVED && $regularization->attendance_id) {
                $regularization->attendance()->update([
                    'status' => $regularization->new_status,
                    'check_in_time' => $regularization->check_in_time,
                    'check_out_time' => $regularization->check_out_time,
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Regularization request reviewed successfully.',
            'data' => $regularization->fresh()->load(['teacher.user', 'attendance', 'reviewedBy']),
        ]);
    }

    public function dailyReport(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $date = $request->input('date', today()->toDateString());

        $query = TeacherAttendance::where('tenant_id', $tenant->id)
            ->where('attendance_date', $date)
            ->with(['teacher.user']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $attendances = $query->get();

        $total = $attendances->count();
        $present = $attendances->where('status', 'present')->count();
        $absent = $attendances->where('status', 'absent')->count();
        $late = $attendances->where('status', 'late')->count();
        $halfDay = $attendances->where('status', 'half_day')->count();
        $onLeave = $attendances->where('status', 'on_leave')->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'date' => $date,
                'summary' => [
                    'total' => $total,
                    'present' => $present,
                    'absent' => $absent,
                    'late' => $late,
                    'half_day' => $halfDay,
                    'on_leave' => $onLeave,
                ],
                'teachers' => $attendances,
            ],
        ]);
    }

    public function monthlyReport(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $yearMonth = $request->input('year_month', now()->format('Y-m'));
        [$year, $month] = explode('-', $yearMonth);

        $daysInMonth = Carbon::createFromDate((int) $year, (int) $month, 1)->daysInMonth;

        $query = TeacherAttendance::where('teacher_attendances.tenant_id', $tenant->id)
            ->whereYear('attendance_date', (int) $year)
            ->whereMonth('attendance_date', (int) $month);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $attendances = $query->get(['teacher_id', 'status', 'attendance_date']);

        $grouped = $attendances->groupBy('teacher_id');
        $teacherIds = $grouped->keys();

        $teachers = Teacher::whereIn('id', $teacherIds)
            ->where('tenant_id', $tenant->id)
            ->with('user')
            ->get()
            ->keyBy('id');

        $result = $grouped->map(function ($records, $teacherId) use ($daysInMonth, $teachers) {
            $teacher = $teachers->get($teacherId);

            return [
                'teacher_id' => $teacherId,
                'teacher_name' => $teacher?->user?->name,
                'employee_id' => $teacher?->employee_id,
                'working_days' => $records->count(),
                'present' => $records->where('status', 'present')->count(),
                'absent' => $records->where('status', 'absent')->count(),
                'late' => $records->where('status', 'late')->count(),
                'leave' => $records->where('status', 'on_leave')->count(),
                'percentage' => $records->count() > 0
                    ? round(($records->whereIn('status', ['present', 'late', 'half_day'])->count() / $daysInMonth) * 100, 2)
                    : 0,
            ];
        })->values();

        return response()->json([
            'status' => 'success',
            'data' => [
                'year_month' => $yearMonth,
                'working_days' => $daysInMonth,
                'teachers' => $result,
            ],
        ]);
    }

    /**
     * Leave-period report: attendance rows driven by leave applications.
     *
     * @operationId teacherAttendance.leavePeriodReport
     */
    public function leavePeriodReport(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $from = $request->input('from', now()->startOfMonth()->toDateString());
        $to = $request->input('to', now()->endOfMonth()->toDateString());

        $query = TeacherAttendance::where('teacher_attendances.tenant_id', $tenant->id)
            ->whereBetween('attendance_date', [$from, $to])
            ->whereNotNull('leave_application_id')
            ->with(['teacher.user', 'leaveApplication.leaveType']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $records = $query->orderBy('attendance_date')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'from' => $from,
                'to' => $to,
                'total' => $records->count(),
                'records' => $records,
            ],
        ]);
    }

    /**
     * Finalize (lock) a month of attendance for salary processing.
     *
     * @operationId teacherAttendance.finalize
     */
    public function finalize(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($this->attendance->isMonthFinalized($tenant, $validated['month'], $validated['year'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance for this month is already finalized.',
            ], 422);
        }

        $finalization = $this->attendance->finalizeMonth(
            $tenant,
            $request->user(),
            $validated['month'],
            $validated['year'],
            $validated['notes'] ?? null,
            $validated['academic_year_id'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance finalized successfully.',
            'data' => $finalization,
        ], 201);
    }

    /**
     * Get the finalization status for a month.
     *
     * @operationId teacherAttendance.finalizationStatus
     */
    public function finalizationStatus(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $finalization = TeacherAttendanceFinalization::where('tenant_id', $tenant->id)
            ->where('month', $validated['month'])
            ->where('year', $validated['year'])
            ->first();

        return response()->json([
            'status' => 'success',
            'data' => [
                'month' => $validated['month'],
                'year' => $validated['year'],
                'is_finalized' => $finalization !== null,
                'finalization' => $finalization,
            ],
        ]);
    }

    /**
     * Preview attendance-based salary adjustments for a teacher.
     *
     * @operationId teacherAttendance.salaryPreview
     */
    public function salaryPreview(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ]);

        $salary = TeacherSalary::where('tenant_id', $tenant->id)
            ->where('teacher_id', $validated['teacher_id'])
            ->where('is_active', true)
            ->latest()
            ->firstOrFail();

        $adjustments = $this->attendance->calculateAttendanceAdjustments(
            $tenant,
            $salary,
            $validated['month'],
            $validated['year'],
        );

        return response()->json([
            'status' => 'success',
            'data' => [
                'teacher_id' => $validated['teacher_id'],
                'month' => $validated['month'],
                'year' => $validated['year'],
                'is_finalized' => $this->attendance->isMonthFinalized($tenant, $validated['month'], $validated['year']),
                ...$adjustments,
            ],
        ]);
    }

    /**
     * Get the tenant's attendance settings.
     *
     * @operationId teacherAttendance.showSettings
     */
    public function showSettings(Request $request): JsonResponse
    {
        $settings = $this->attendance->settingsFor($request->user()->tenant);

        return response()->json([
            'status' => 'success',
            'data' => $settings,
        ]);
    }

    /**
     * Update the tenant's attendance settings.
     *
     * @operationId teacherAttendance.updateSettings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        $settings = $this->attendance->settingsFor($request->user()->tenant);

        $validated = $request->validate([
            'edit_window_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
            'work_start_time' => ['nullable', 'date_format:H:i'],
            'work_end_time' => ['nullable', 'date_format:H:i'],
            'late_after_time' => ['nullable', 'date_format:H:i'],
            'half_day_min_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'overtime_after_hours' => ['nullable', 'numeric', 'min:0', 'max:24'],
            'selfie_required' => ['nullable', 'boolean'],
            'mock_location_detection' => ['nullable', 'boolean'],
            'allow_geofence_override' => ['nullable', 'boolean'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:0'],
            'default_status' => ['nullable', Rule::in(TeacherAttendance::STATUSES)],
            'late_deduction_mode' => ['nullable', Rule::in(TeacherAttendanceSetting::LATE_DEDUCTION_MODES)],
            'late_deduction_fixed_amount' => ['nullable', 'numeric', 'min:0'],
            'late_to_half_day_threshold' => ['nullable', 'integer', 'min:1'],
            'overtime_enabled' => ['nullable', 'boolean'],
            'overtime_hourly_rate' => ['nullable', 'numeric', 'min:0'],
        ]);

        $settings->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance settings updated successfully.',
            'data' => $settings->fresh(),
        ]);
    }

    /**
     * List enrolled teacher fingerprints.
     *
     * @operationId teacherAttendance.listFingerprints
     */
    public function fingerprints(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherFingerprint::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'device']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Enroll a teacher fingerprint (max two per teacher).
     *
     * @operationId teacherAttendance.enrollFingerprint
     */
    public function enrollFingerprint(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'device_id' => ['nullable', 'uuid', 'exists:fingerprint_devices,id'],
            'finger_index' => ['required', 'integer', Rule::in(TeacherFingerprint::FINGER_INDEXES)],
            'template_data' => ['nullable', 'string'],
            'label' => ['nullable', 'string', 'max:100'],
        ]);

        $fingerprint = $this->attendance->enrollFingerprint($request->user()->tenant, $request->user(), $validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher fingerprint enrolled successfully.',
            'data' => $fingerprint->fresh()->load(['teacher.user', 'device']),
        ], 201);
    }

    /**
     * Remove an enrolled teacher fingerprint.
     *
     * @operationId teacherAttendance.deleteFingerprint
     */
    public function deleteFingerprint(Request $request, TeacherFingerprint $fingerprint): JsonResponse
    {
        if ($fingerprint->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $fingerprint->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher fingerprint removed successfully.',
        ]);
    }

    /**
     * List raw fingerprint punches.
     *
     * @operationId teacherAttendance.fingerprintPunches
     */
    public function fingerprintPunches(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherFingerprintLog::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'device']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('from')) {
            $query->where('punch_time', '>=', Carbon::parse($request->from));
        }

        if ($request->filled('to')) {
            $query->where('punch_time', '<=', Carbon::parse($request->to));
        }

        if ($request->filled('processed')) {
            $query->where('processed', filter_var($request->processed, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('punch_time', 'desc')->get(),
        ]);
    }

    /**
     * Store fingerprint punches and process them into attendance.
     *
     * @operationId teacherAttendance.storeFingerprintPunches
     */
    public function storeFingerprintPunches(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'punches' => ['required', 'array', 'min:1'],
            'punches.*.teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'punches.*.device_id' => ['nullable', 'uuid', 'exists:fingerprint_devices,id'],
            'punches.*.punch_time' => ['required', 'date'],
            'punches.*.direction' => ['nullable', Rule::in(TeacherFingerprintLog::DIRECTIONS)],
            'punches.*.raw_data' => ['nullable', 'string'],
            'punches.*.synced' => ['nullable', 'boolean'],
        ]);

        $logs = $this->attendance->recordPunches($tenant, $validated['punches']);
        $result = $this->attendance->processPunches($tenant);

        return response()->json([
            'status' => 'success',
            'message' => $logs->count().' punch(es) recorded and '.count($result['processed']).' attendance record(s) updated.',
            'data' => [
                'recorded' => $logs->count(),
                'processed' => count($result['processed']),
                'skipped' => $result['skipped'],
            ],
        ], 201);
    }

    /**
     * Backfill: process any unprocessed fingerprint punches.
     *
     * @operationId teacherAttendance.processFingerprintPunches
     */
    public function processFingerprintPunches(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'date' => ['nullable', 'date'],
        ]);

        $result = $this->attendance->processPunches(
            $tenant,
            $validated['teacher_id'] ?? null,
            $validated['date'] ?? null,
        );

        return response()->json([
            'status' => 'success',
            'message' => count($result['processed']).' attendance record(s) updated.',
            'data' => [
                'processed' => count($result['processed']),
                'skipped' => $result['skipped'],
                'records' => $result['processed'],
            ],
        ]);
    }
}
