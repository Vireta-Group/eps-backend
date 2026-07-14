<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceRegularization;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Teacher Management > Attendance', weight: 56)]
class TeacherAttendanceController extends Controller
{
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
            'status' => ['nullable', Rule::in(['present', 'absent', 'late', 'half_day', 'on_leave', 'excused'])],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'location_name' => ['nullable', 'string', 'max:255'],
            'source' => ['nullable', Rule::in(['manual', 'fingerprint', 'mobile'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $teacher = Teacher::where('tenant_id', $tenant->id)
            ->where('id', $validated['teacher_id'])
            ->firstOrFail();

        $record = TeacherAttendance::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'attendance_date' => $validated['attendance_date'],
            ],
            [
                'tenant_id' => $tenant->id,
                'check_in_time' => $validated['check_in_time'] ?? null,
                'check_out_time' => $validated['check_out_time'] ?? null,
                'status' => $validated['status'] ?? 'present',
                'latitude' => $validated['latitude'] ?? null,
                'longitude' => $validated['longitude'] ?? null,
                'location_name' => $validated['location_name'] ?? null,
                'source' => $validated['source'] ?? 'manual',
                'remarks' => $validated['remarks'] ?? null,
                'marked_by' => $request->user()->id,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher attendance marked successfully.',
            'data' => $record->fresh()->load(['teacher.user', 'markedBy']),
        ], 201);
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
}
