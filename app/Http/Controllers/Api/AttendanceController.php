<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AttendancePeriod;
use App\Models\AttendanceRegularization;
use App\Models\FingerprintDevice;
use App\Models\FingerprintLog;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\StudentAttendance;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Student Management', description: 'Student profiles, guardians, attendance, health, behaviour, dropout, transfer certificates, and reports.', weight: 50)]
class AttendanceController extends Controller
{
    // ── Periods ─────────────────────────────────────────────────────────

    /**
     * List all active attendance periods for the tenant.
     *
     * @operationId attendance.periods
     */
    public function periods(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $periods = AttendancePeriod::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('display_order')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $periods,
        ]);
    }

    /**
     * Create a new attendance period.
     *
     * **Required:** period_name, start_time, end_time
     *
     * @operationId attendance.storePeriod
     */
    public function storePeriod(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'period_name' => ['required', 'string', 'max:100'],
            'period_name_bn' => ['nullable', 'string', 'max:200'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $period = AttendancePeriod::create([
            'tenant_id' => $tenant->id,
            'period_name' => $validated['period_name'],
            'period_name_bn' => $validated['period_name_bn'] ?? null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'display_order' => $validated['display_order'] ?? 0,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance period created successfully.',
            'data' => $period->fresh(),
        ], 201);
    }

    /**
     * Update an attendance period.
     *
     * @operationId attendance.updatePeriod
     */
    public function updatePeriod(Request $request, AttendancePeriod $period): JsonResponse
    {
        $this->ensurePeriodOwnership($request, $period);

        $validated = $request->validate([
            'period_name' => ['sometimes', 'required', 'string', 'max:100'],
            'period_name_bn' => ['nullable', 'string', 'max:200'],
            'start_time' => ['sometimes', 'required', 'date_format:H:i'],
            'end_time' => ['sometimes', 'required', 'date_format:H:i', 'after:start_time'],
            'display_order' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $period->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance period updated successfully.',
            'data' => $period->fresh(),
        ]);
    }

    /**
     * Soft-delete (deactivate) an attendance period.
     *
     * @operationId attendance.deletePeriod
     */
    public function deletePeriod(Request $request, AttendancePeriod $period): JsonResponse
    {
        $this->ensurePeriodOwnership($request, $period);

        $period->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance period deleted successfully.',
        ]);
    }

    // ── Daily Attendance ─────────────────────────────────────────────────

    /**
     * List attendance records with optional filters.
     *
     * Defaults to today's date. Loads student relationships.
     *
     * @operationId attendance.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = StudentAttendance::where('student_attendances.tenant_id', $tenant->id)
            ->with([
                'student.currentPhoto',
                'student.currentAcademic.schoolClass',
                'student.currentAcademic.schoolSection',
                'period',
            ]);

        if ($request->filled('date')) {
            $query->where('attendance_date', $request->date);
        } else {
            $query->where('attendance_date', today()->toDateString());
        }

        if ($request->filled('section_id')) {
            $query->where('school_section_id', $request->section_id);
        }

        if ($request->filled('class_id')) {
            $query->where('school_class_id', $request->class_id);
        }

        if ($request->filled('period_id')) {
            $query->where('period_id', $request->period_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $attendances = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $attendances,
        ]);
    }

    /**
     * Mark attendance for one or multiple students.
     *
     * Accepts single: `{student_id, status, date}` or bulk: `{attendances: [{student_id, status, date, arrival_time, departure_time, remarks}]}`.
     * Uses `updateOrCreate` on [student_id, attendance_date, period_id].
     *
     * @operationId attendance.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($request->has('attendances')) {
            $validated = $request->validate([
                'attendances' => ['required', 'array', 'min:1'],
                'attendances.*.student_id' => ['required', 'uuid', 'exists:students,id'],
                'attendances.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave', 'excused', 'suspended'])],
                'attendances.*.date' => ['required', 'date'],
                'attendances.*.period_id' => ['nullable', 'uuid', 'exists:attendance_periods,id'],
                'attendances.*.arrival_time' => ['nullable', 'date_format:H:i'],
                'attendances.*.departure_time' => ['nullable', 'date_format:H:i'],
                'attendances.*.remarks' => ['nullable', 'string'],
            ]);

            $records = [];

            DB::transaction(function () use ($tenant, $request, $validated, &$records) {
                foreach ($validated['attendances'] as $item) {
                    $records[] = $this->upsertAttendance($tenant, $request->user(), $item);
                }
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Attendance marked successfully.',
                'data' => $records,
            ], 201);
        }

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave', 'excused', 'suspended'])],
            'date' => ['required', 'date'],
            'period_id' => ['nullable', 'uuid', 'exists:attendance_periods,id'],
            'arrival_time' => ['nullable', 'date_format:H:i'],
            'departure_time' => ['nullable', 'date_format:H:i'],
            'remarks' => ['nullable', 'string'],
        ]);

        $record = DB::transaction(function () use ($tenant, $request, $validated) {
            return $this->upsertAttendance($tenant, $request->user(), $validated);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance marked successfully.',
            'data' => $record,
        ], 201);
    }

    /**
     * Mark attendance for an entire section/class in bulk.
     *
     * **Required:** school_section_id, attendance_date
     * **Optional:** default_status (default: present), overrides
     *
     * @operationId attendance.markBulk
     */
    public function markBulk(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'school_section_id' => ['required', 'uuid', 'exists:school_sections,id'],
            'attendance_date' => ['required', 'date'],
            'default_status' => ['nullable', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave', 'excused', 'suspended'])],
            'overrides' => ['nullable', 'array'],
            'overrides.*.student_id' => ['required', 'uuid', 'exists:students,id'],
            'overrides.*.status' => ['required', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave', 'excused', 'suspended'])],
            'overrides.*.arrival_time' => ['nullable', 'date_format:H:i'],
        ]);

        $section = SchoolSection::where('tenant_id', $tenant->id)
            ->where('id', $validated['school_section_id'])
            ->firstOrFail();

        $defaultStatus = $validated['default_status'] ?? 'present';

        $students = Student::where('students.tenant_id', $tenant->id)
            ->join('student_academic_histories', function ($join) {
                $join->on('students.id', '=', 'student_academic_histories.student_id')
                    ->where('student_academic_histories.is_current', true);
            })
            ->where('student_academic_histories.school_section_id', $section->id)
            ->select('students.*',
                'student_academic_histories.academic_year_id',
                'student_academic_histories.school_class_id',
                'student_academic_histories.school_section_id')
            ->get();

        $overrideMap = collect($validated['overrides'] ?? [])->keyBy('student_id');

        $academicYear = $tenant->academicYears()->where('is_current', true)->first();

        $records = [];

        DB::transaction(function () use ($tenant, $request, $students, $validated, $defaultStatus, $overrideMap, $academicYear, &$records) {
            foreach ($students as $student) {
                $override = $overrideMap->get($student->id);

                $record = StudentAttendance::updateOrCreate(
                    [
                        'student_id' => $student->id,
                        'attendance_date' => $validated['attendance_date'],
                        'period_id' => null,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'academic_year_id' => $academicYear?->id ?? $student->academic_year_id,
                        'school_class_id' => $student->school_class_id,
                        'school_section_id' => $student->school_section_id,
                        'status' => $override ? $override['status'] : $defaultStatus,
                        'arrival_time' => $override['arrival_time'] ?? null,
                        'source' => 'manual',
                        'marked_by' => $request->user()->id,
                        'created_by' => $request->user()->id,
                    ]
                );

                $records[] = $record;
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Bulk attendance marked successfully.',
            'data' => $records,
        ], 201);
    }

    /**
     * Get a single attendance record.
     *
     * @operationId attendance.show
     */
    public function show(Request $request, StudentAttendance $attendance): JsonResponse
    {
        $this->ensureAttendanceOwnership($request, $attendance);

        $attendance->load(['student.currentPhoto', 'student.currentAcademic.schoolClass', 'student.currentAcademic.schoolSection', 'period']);

        return response()->json([
            'status' => 'success',
            'data' => $attendance,
        ]);
    }

    // ── Regularization ──────────────────────────────────────────────────

    /**
     * List regularization requests.
     *
     * Filter by status, student_id.
     *
     * @operationId attendance.regularizations
     */
    public function regularizationRequests(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = AttendanceRegularization::where('tenant_id', $tenant->id)
            ->with(['student', 'attendance', 'reviewedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $regularizations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $regularizations,
        ]);
    }

    /**
     * Request regularization for an attendance record.
     *
     * Provide either `attendance_id` or `attendance_date + student_id`.
     * **Required:** reason
     *
     * @operationId attendance.requestRegularization
     */
    public function requestRegularization(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'attendance_id' => ['nullable', 'uuid', 'exists:student_attendances,id'],
            'attendance_date' => ['required_without:attendance_id', 'nullable', 'date'],
            'student_id' => ['required_without:attendance_id', 'nullable', 'uuid', 'exists:students,id'],
            'reason' => ['required', 'string', 'max:1000'],
            'supporting_doc_url' => ['nullable', 'url', 'max:500'],
        ]);

        $attendance = null;
        $oldStatus = null;

        if ($validated['attendance_id']) {
            $attendance = StudentAttendance::where('tenant_id', $tenant->id)
                ->where('id', $validated['attendance_id'])
                ->firstOrFail();

            $oldStatus = $attendance->status;
            $studentId = $attendance->student_id;
            $attendanceDate = $attendance->attendance_date;
        } else {
            $studentId = $validated['student_id'];

            $student = Student::where('tenant_id', $tenant->id)
                ->where('id', $studentId)
                ->firstOrFail();

            $attendanceDate = $validated['attendance_date'];

            $attendance = StudentAttendance::where('tenant_id', $tenant->id)
                ->where('student_id', $studentId)
                ->where('attendance_date', $attendanceDate)
                ->first();

            $oldStatus = $attendance?->status;
        }

        $regularization = AttendanceRegularization::create([
            'tenant_id' => $tenant->id,
            'student_id' => $studentId,
            'attendance_id' => $attendance?->id,
            'attendance_date' => $attendanceDate,
            'old_status' => $oldStatus,
            'new_status' => 'excused',
            'reason' => $validated['reason'],
            'supporting_doc_url' => $validated['supporting_doc_url'] ?? null,
            'status' => AttendanceRegularization::STATUS_PENDING,
            'requested_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Regularization request submitted successfully.',
            'data' => $regularization->fresh(),
        ], 201);
    }

    /**
     * Review a regularization request.
     *
     * **Required:** status (approved/rejected)
     * If approved, updates the attendance record status.
     *
     * @operationId attendance.reviewRegularization
     */
    public function reviewRegularization(Request $request, AttendanceRegularization $regularization): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($regularization->tenant_id !== $tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([AttendanceRegularization::STATUS_APPROVED, AttendanceRegularization::STATUS_REJECTED])],
            'review_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($regularization, $validated, $request) {
            $regularization->update([
                'status' => $validated['status'],
                'review_remarks' => $validated['review_remarks'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if ($validated['status'] === AttendanceRegularization::STATUS_APPROVED && $regularization->attendance_id) {
                $regularization->attendance()->update([
                    'status' => $regularization->new_status,
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Regularization request reviewed successfully.',
            'data' => $regularization->fresh()->load(['student', 'attendance', 'reviewedBy']),
        ]);
    }

    // ── Fingerprint Devices ──────────────────────────────────────────────

    /**
     * List fingerprint devices for the tenant.
     *
     * @operationId attendance.fingerprintDevices
     */
    public function devices(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $devices = FingerprintDevice::where('tenant_id', $tenant->id)
            ->orderBy('device_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $devices,
        ]);
    }

    /**
     * Create a new fingerprint device.
     *
     * @operationId attendance.storeFingerprintDevice
     */
    public function storeDevice(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'device_name' => ['required', 'string', 'max:200'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:200'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'shift_type' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'is_online' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
        ]);

        $device = FingerprintDevice::create([
            'tenant_id' => $tenant->id,
            'device_name' => $validated['device_name'],
            'device_model' => $validated['device_model'] ?? null,
            'serial_number' => $validated['serial_number'] ?? null,
            'location' => $validated['location'] ?? null,
            'ip_address' => $validated['ip_address'] ?? null,
            'port' => $validated['port'] ?? null,
            'shift_type' => $validated['shift_type'] ?? null,
            'status' => $validated['status'] ?? 'active',
            'is_online' => $validated['is_online'] ?? false,
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Fingerprint device created successfully.',
            'data' => $device->fresh(),
        ], 201);
    }

    /**
     * Update a fingerprint device.
     *
     * @operationId attendance.updateFingerprintDevice
     */
    public function updateDevice(Request $request, FingerprintDevice $device): JsonResponse
    {
        $this->ensureDeviceOwnership($request, $device);

        $validated = $request->validate([
            'device_name' => ['sometimes', 'required', 'string', 'max:200'],
            'device_model' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:200'],
            'ip_address' => ['nullable', 'string', 'max:45'],
            'port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'shift_type' => ['nullable', 'string', 'max:20'],
            'status' => ['nullable', 'string', 'max:20'],
            'is_online' => ['nullable', 'boolean'],
            'last_sync_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $device->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Fingerprint device updated successfully.',
            'data' => $device->fresh(),
        ]);
    }

    // ── Fingerprint Logs ─────────────────────────────────────────────────

    /**
     * List fingerprint logs with optional filters.
     *
     * Filter by device_id, student_id, date range.
     *
     * @operationId attendance.fingerprintLogs
     */
    public function fingerprintLogs(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = FingerprintLog::where('fingerprint_logs.tenant_id', $tenant->id)
            ->with(['device', 'student']);

        if ($request->filled('device_id')) {
            $query->where('device_id', $request->device_id);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('from')) {
            $query->where('scan_time', '>=', Carbon::parse($request->from));
        }

        if ($request->filled('to')) {
            $query->where('scan_time', '<=', Carbon::parse($request->to));
        }

        $logs = $query->orderBy('scan_time', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }

    /**
     * Create a fingerprint log entry.
     *
     * @operationId attendance.storeFingerprintLog
     */
    public function storeFingerprintLog(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'device_id' => ['nullable', 'uuid', 'exists:fingerprint_devices,id'],
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'scan_time' => ['required', 'date'],
            'direction' => ['nullable', 'string', 'max:10'],
            'raw_data' => ['nullable', 'string'],
            'synced' => ['nullable', 'boolean'],
            'synced_at' => ['nullable', 'date'],
        ]);

        $log = FingerprintLog::create([
            'tenant_id' => $tenant->id,
            'device_id' => $validated['device_id'] ?? null,
            'student_id' => $validated['student_id'],
            'scan_time' => $validated['scan_time'],
            'direction' => $validated['direction'] ?? 'entry',
            'raw_data' => $validated['raw_data'] ?? null,
            'synced' => $validated['synced'] ?? false,
            'synced_at' => $validated['synced_at'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Fingerprint log created successfully.',
            'data' => $log->fresh(),
        ], 201);
    }

    // ── Reports ─────────────────────────────────────────────────────────

    /**
     * Daily attendance summary per section.
     *
     * Query params: date (default today), section_id.
     *
     * @operationId attendance.dailyReport
     */
    public function dailyReport(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $date = $request->input('date', today()->toDateString());

        $query = StudentAttendance::where('student_attendances.tenant_id', $tenant->id)
            ->where('attendance_date', $date)
            ->with(['student:id,name_en,student_id', 'student.currentAcademic.schoolClass', 'student.currentAcademic.schoolSection']);

        if ($request->filled('section_id')) {
            $query->where('school_section_id', $request->section_id);
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
                'students' => $attendances,
            ],
        ]);
    }

    /**
     * Monthly attendance report per student.
     *
     * Query params: year_month (Y-m), student_id (optional), class_id, section_id.
     *
     * @operationId attendance.monthlyReport
     */
    public function monthlyReport(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $yearMonth = $request->input('year_month', now()->format('Y-m'));

        [$year, $month] = explode('-', $yearMonth);

        $daysInMonth = Carbon::createFromDate((int) $year, (int) $month, 1)->daysInMonth;

        $query = StudentAttendance::where('student_attendances.tenant_id', $tenant->id)
            ->whereYear('attendance_date', (int) $year)
            ->whereMonth('attendance_date', (int) $month);

        if ($request->filled('class_id')) {
            $query->where('school_class_id', $request->class_id);
        }

        if ($request->filled('section_id')) {
            $query->where('school_section_id', $request->section_id);
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $attendances = $query->get(['student_id', 'status', 'attendance_date']);

        $grouped = $attendances->groupBy('student_id');

        $studentIds = $grouped->keys();

        $students = Student::whereIn('id', $studentIds)
            ->where('tenant_id', $tenant->id)
            ->select('id', 'student_id', 'name_en')
            ->get()
            ->keyBy('id');

        $result = $grouped->map(function ($records, $studentId) use ($daysInMonth, $students) {
            $student = $students->get($studentId);

            return [
                'student_id' => $studentId,
                'student_identifier' => $student?->student_id,
                'student_name' => $student?->name_en,
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
                'students' => $result,
            ],
        ]);
    }

    // ── Helpers ─────────────────────────────────────────────────────────

    private function ensurePeriodOwnership(Request $request, AttendancePeriod $period): void
    {
        if ($period->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }

    private function ensureAttendanceOwnership(Request $request, StudentAttendance $attendance): void
    {
        if ($attendance->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }

    private function ensureDeviceOwnership(Request $request, FingerprintDevice $device): void
    {
        if ($device->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }

    private function upsertAttendance($tenant, $user, array $data): StudentAttendance
    {
        $student = Student::where('tenant_id', $tenant->id)
            ->where('id', $data['student_id'])
            ->firstOrFail();

        $currentHistory = StudentAcademicHistory::where('student_id', $student->id)
            ->where('is_current', true)
            ->first();

        return StudentAttendance::updateOrCreate(
            [
                'student_id' => $student->id,
                'attendance_date' => $data['date'],
                'period_id' => $data['period_id'] ?? null,
            ],
            [
                'tenant_id' => $tenant->id,
                'academic_year_id' => $currentHistory?->academic_year_id,
                'school_class_id' => $currentHistory?->school_class_id,
                'school_section_id' => $currentHistory?->school_section_id,
                'status' => $data['status'],
                'arrival_time' => $data['arrival_time'] ?? null,
                'departure_time' => $data['departure_time'] ?? null,
                'remarks' => $data['remarks'] ?? null,
                'source' => 'manual',
                'marked_by' => $user->id,
                'created_by' => $user->id,
            ]
        );
    }
}
