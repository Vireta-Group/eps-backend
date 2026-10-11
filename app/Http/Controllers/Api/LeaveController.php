<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LeaveType;
use App\Models\Teacher;
use App\Models\TeacherLeaveApplication;
use App\Models\TeacherLeaveBalance;
use App\Services\TeacherAttendanceService;
use Carbon\Carbon;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Teacher Management > Leave Management', weight: 63)]
class LeaveController extends Controller
{
    public function __construct(private TeacherAttendanceService $attendance)
    {
    }

    // ── Leave Types ─────────────────────────────────────────────────────

    public function leaveTypes(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $types = LeaveType::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('leave_type_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $types,
        ]);
    }

    public function storeLeaveType(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'leave_type_name' => ['required', 'string', 'max:100'],
            'leave_type_name_bn' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'default_days' => ['required', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $type = LeaveType::create([
            'tenant_id' => $tenant->id,
            'leave_type_name' => $validated['leave_type_name'],
            'leave_type_name_bn' => $validated['leave_type_name_bn'] ?? null,
            'description' => $validated['description'] ?? null,
            'default_days' => $validated['default_days'],
            'is_paid' => $validated['is_paid'] ?? true,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave type created successfully.',
            'data' => $type->fresh(),
        ], 201);
    }

    public function updateLeaveType(Request $request, LeaveType $leaveType): JsonResponse
    {
        if ($leaveType->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'leave_type_name' => ['sometimes', 'required', 'string', 'max:100'],
            'leave_type_name_bn' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'default_days' => ['sometimes', 'required', 'integer', 'min:1', 'max:365'],
            'is_paid' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $leaveType->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave type updated successfully.',
            'data' => $leaveType->fresh(),
        ]);
    }

    public function deleteLeaveType(Request $request, LeaveType $leaveType): JsonResponse
    {
        if ($leaveType->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $leaveType->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Leave type deleted successfully.',
        ]);
    }

    // ── Leave Balances ──────────────────────────────────────────────────

    public function balances(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherLeaveBalance::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'leaveType', 'academicYear']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $balances = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $balances,
        ]);
    }

    public function createBalance(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
            'total_days' => ['required', 'integer', 'min:1', 'max:365'],
            'used_days' => ['nullable', 'integer', 'min:0'],
        ]);

        Teacher::where('tenant_id', $tenant->id)
            ->where('id', $validated['teacher_id'])
            ->firstOrFail();

        $total = $validated['total_days'];
        $used = $validated['used_days'] ?? 0;

        $balance = TeacherLeaveBalance::updateOrCreate(
            [
                'teacher_id' => $validated['teacher_id'],
                'academic_year_id' => $validated['academic_year_id'],
                'leave_type_id' => $validated['leave_type_id'],
            ],
            [
                'tenant_id' => $tenant->id,
                'total_days' => $total,
                'used_days' => $used,
                'remaining_days' => $total - $used,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Leave balance created successfully.',
            'data' => $balance->fresh()->load(['teacher.user', 'leaveType', 'academicYear']),
        ], 201);
    }

    // ── Leave Applications ──────────────────────────────────────────────

    public function applications(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherLeaveApplication::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'leaveType', 'reviewedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('from')) {
            $query->where('start_date', '>=', $request->from);
        }

        if ($request->filled('to')) {
            $query->where('end_date', '<=', $request->to);
        }

        $applications = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $applications,
        ]);
    }

    public function apply(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'leave_type_id' => ['required', 'uuid', 'exists:leave_types,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_half_day' => ['nullable', 'boolean'],
            'half_day_session' => ['nullable', Rule::in([TeacherLeaveApplication::HALF_DAY_MORNING, TeacherLeaveApplication::HALF_DAY_AFTERNOON])],
            'is_emergency' => ['nullable', 'boolean'],
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        Teacher::where('tenant_id', $tenant->id)
            ->where('id', $validated['teacher_id'])
            ->firstOrFail();

        $isHalfDay = (bool) ($validated['is_half_day'] ?? false);

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);
        $totalDays = $isHalfDay ? 0.5 : ($start->diffInDays($end) + 1);

        $application = DB::transaction(function () use ($tenant, $validated, $totalDays, $isHalfDay, $request) {
            $application = TeacherLeaveApplication::create([
                'tenant_id' => $tenant->id,
                'teacher_id' => $validated['teacher_id'],
                'leave_type_id' => $validated['leave_type_id'],
                'academic_year_id' => $validated['academic_year_id'] ?? null,
                'start_date' => $validated['start_date'],
                'end_date' => $validated['end_date'],
                'total_days' => $totalDays,
                'is_half_day' => $isHalfDay,
                'half_day_session' => $validated['half_day_session'] ?? null,
                'is_emergency' => $validated['is_emergency'] ?? false,
                'reason' => $validated['reason'],
                'status' => TeacherLeaveApplication::STATUS_PENDING,
                'created_by' => $request->user()->id,
            ]);

            return $application;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Leave application submitted successfully.',
            'data' => $application->fresh()->load(['teacher.user', 'leaveType']),
        ], 201);
    }

    public function review(Request $request, TeacherLeaveApplication $application): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($application->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($application->status !== TeacherLeaveApplication::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending applications can be reviewed.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([TeacherLeaveApplication::STATUS_APPROVED, TeacherLeaveApplication::STATUS_REJECTED])],
            'review_remarks' => ['nullable', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($application, $validated, $request) {
            $application->update([
                'status' => $validated['status'],
                'review_remarks' => $validated['review_remarks'] ?? null,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => now(),
            ]);

            if ($validated['status'] === TeacherLeaveApplication::STATUS_APPROVED) {
                $balance = TeacherLeaveBalance::where('teacher_id', $application->teacher_id)
                    ->where('leave_type_id', $application->leave_type_id)
                    ->where('academic_year_id', $application->academic_year_id)
                    ->first();

                if ($balance) {
                    $updatedUsed = $balance->used_days + $application->total_days;
                    $balance->update([
                        'used_days' => $updatedUsed,
                        'remaining_days' => $balance->total_days - $updatedUsed,
                    ]);
                }

                $this->attendance->applyLeaveToAttendance($application->fresh());
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Leave application reviewed successfully.',
            'data' => $application->fresh()->load(['teacher.user', 'leaveType', 'reviewedBy']),
        ]);
    }

    public function cancel(Request $request, TeacherLeaveApplication $application): JsonResponse
    {
        if ($application->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        if (! in_array($application->status, [TeacherLeaveApplication::STATUS_PENDING, TeacherLeaveApplication::STATUS_APPROVED])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending or approved applications can be cancelled.',
            ], 422);
        }

        $wasApproved = $application->status === TeacherLeaveApplication::STATUS_APPROVED;

        DB::transaction(function () use ($application, $wasApproved) {
            $application->update(['status' => TeacherLeaveApplication::STATUS_CANCELLED]);

            if ($wasApproved) {
                $balance = TeacherLeaveBalance::where('teacher_id', $application->teacher_id)
                    ->where('leave_type_id', $application->leave_type_id)
                    ->where('academic_year_id', $application->academic_year_id)
                    ->first();

                if ($balance) {
                    $restoredUsed = max(0, $balance->used_days - $application->total_days);
                    $balance->update([
                        'used_days' => $restoredUsed,
                        'remaining_days' => $balance->total_days - $restoredUsed,
                    ]);
                }

                $this->attendance->removeLeaveFromAttendance($application);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Leave application cancelled successfully.',
            'data' => $application->fresh(),
        ]);
    }
}
