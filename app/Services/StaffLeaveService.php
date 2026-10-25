<?php

namespace App\Services;

use App\Models\StaffLeaveApplication;
use App\Models\StaffLeaveBalance;
use App\Models\StaffLeaveType;
use App\Models\Teacher;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class StaffLeaveService
{
    /**
     * Apply for leave for any staff member.
     */
    public function applyLeave(Tenant $tenant, \Illuminate\Database\Eloquent\Model $staff, array $data): StaffLeaveApplication
    {
        $isHalfDay = (bool) ($data['is_half_day'] ?? false);
        $start = Carbon::parse($data['start_date']);
        $end = Carbon::parse($data['end_date']);
        
        // Simple day calculation:’S’-S’+1. Half day is always 0.5.
        $totalDays = $isHalfDay ? 0.5 : ($start->diffInDays($end) + 1);

        return StaffLeaveApplication::create([
            'tenant_id' => $tenant->id,
            'staff_id' => $staff->id,
            'staff_type' => get_class($staff),
            'leave_type_id' => $data['leave_type_id'],
            'academic_year_id' => $data['academic_year_id'] ?? null,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'total_days' => $totalDays,
            'is_half_day' => $isHalfDay,
            'half_day_session' => $data['half_day_session'] ?? null,
            'is_emergency' => $data['is_emergency'] ?? false,
            'reason' => $data['reason'],
            'status' => StaffLeaveApplication::STATUS_PENDING,
        ]);
    }

    /**
     * Review a leave application.
     */
    public function reviewLeave(StaffLeaveApplication $application, string $status, ?string $remarks, User $actor): StaffLeaveApplication
    {
        return DB::transaction(function () use ($application, $status, $remarks, $actor) {
            $application->update([
                'status' => $status,
                'review_remarks' => $remarks,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ]);

            if ($status === StaffLeaveApplication::STATUS_APPROVED) {
                $this->updateBalance($application, 'deduct');
            }

            return $application->fresh();
        });
    }

    /**
     * Cancel a leave application and restore balance.
     */
    public function cancelLeave(StaffLeaveApplication $application): StaffLeaveApplication
    {
        return DB::transaction(function () use ($application) {
            $application->update(['status' => StaffLeaveApplication::STATUS_CANCELLED]);

            if ($application->status === StaffLeaveApplication::STATUS_APPROVED) {
                $this->updateBalance($application, 'restore');
            }

            return $application->fresh();
        });
    }

    private function updateBalance(StaffLeaveApplication $application, string $action): void
    {
        $balance = StaffLeaveBalance::where([
            'tenant_id' => $application->tenant_id,
            'staff_id' => $application->staff_id,
            'staff_type' => $application->staff_type,
            'leave_type_id' => $application->leave_type_id,
            'academic_year_id' => $application->academic_year_id,
        ])->first();

        if (! $balance) return;

        if ($action === 'deduct') {
            $balance->update([
                'used_days' => $balance->used_//L_..._used_days + $application->total_days,
                'remaining_days' => $balance->total_days - ($balance->used_days + $application->total_days),
            ]);
        } else {
            $balance->update([
                'used_days' => max(0, $balance->used_days - $application->total_days),
                'remaining_days' => $balance->total_days - max(0, $balance->used_days - $application->total_days),
            ]);
        }
    }
}
