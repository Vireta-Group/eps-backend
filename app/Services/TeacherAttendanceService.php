<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\TeacherAttendanceEdit;
use App\Models\TeacherAttendanceFinalization;
use App\Models\TeacherAttendanceSetting;
use App\Models\TeacherFingerprint;
use App\Models\TeacherFingerprintLog;
use App\Models\TeacherLeaveApplication;
use App\Models\TeacherSalary;
use App\Models\Tenant;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TeacherAttendanceService
{
    /**
     * Resolve (or lazily create) the tenant's attendance settings.
     */
    public function settingsFor(Tenant $tenant): TeacherAttendanceSetting
    {
        return TeacherAttendanceSetting::firstOrCreate(
            ['tenant_id' => $tenant->id],
            ['default_status' => TeacherAttendance::STATUS_PRESENT]
        );
    }

    /**
     * Create or update a single day's attendance record.
     *
     * @param  array<string, mixed>  $data
     */
    public function mark(Tenant $tenant, User $actor, array $data): TeacherAttendance
    {
        $settings = $this->settingsFor($tenant);
        $editableUntil = now()->addMinutes($settings->edit_window_minutes);

        $teacher = Teacher::where('tenant_id', $tenant->id)
            ->where('id', $data['teacher_id'])
            ->firstOrFail();

        return TeacherAttendance::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'attendance_date' => $data['attendance_date'],
            ],
            [
                'tenant_id' => $tenant->id,
                'academic_year_id' => $data['academic_year_id'] ?? null,
                'check_in_time' => $data['check_in_time'] ?? null,
                'check_out_time' => $data['check_out_time'] ?? null,
                'status' => $data['status'] ?? $settings->default_status,
                'latitude' => $data['latitude'] ?? null,
                'longitude' => $data['longitude'] ?? null,
                'location_name' => $data['location_name'] ?? null,
                'source' => $data['source'] ?? TeacherAttendance::SOURCE_MANUAL,
                'remarks' => $data['remarks'] ?? null,
                'marked_by' => $actor->id,
                'editable_until' => $editableUntil,
                'created_by' => $actor->id,
            ]
        );
    }

    /**
     * Mark attendance for many teachers on a shared date.
     *
     * @param  array<int, array<string, mixed>>  $records
     * @return array<int, TeacherAttendance>
     */
    public function bulkMark(Tenant $tenant, User $actor, string $date, array $records): array
    {
        return DB::transaction(function () use ($tenant, $actor, $date, $records) {
            $marked = [];

            foreach ($records as $record) {
                $marked[] = $this->mark($tenant, $actor, [
                    ...$record,
                    'attendance_date' => $date,
                ]);
            }

            return $marked;
        });
    }

    /**
     * Apply a correction to an attendance record and log the change.
     *
     * @param  array<string, mixed>  $newValues
     */
    public function edit(TeacherAttendance $attendance, array $newValues, User $actor, ?string $reason = null): TeacherAttendance
    {
        $tracked = [
            'status',
            'check_in_time',
            'check_out_time',
            'remarks',
            'leave_application_id',
        ];

        $oldValues = $attendance->only($tracked);

        return DB::transaction(function () use ($attendance, $newValues, $actor, $reason, $oldValues) {
            $attendance->fill($newValues);
            $attendance->edit_count = ($attendance->edit_count ?? 0) + 1;
            $attendance->save();

            TeacherAttendanceEdit::create([
                'tenant_id' => $attendance->tenant_id,
                'attendance_id' => $attendance->id,
                'edited_by' => $actor->id,
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'reason' => $reason,
            ]);

            return $attendance->fresh();
        });
    }

    /**
     * Reflect an approved leave application onto the attendance register.
     *
     * Every day in the leave span is marked as leave (or half day for a
     * half-day application). Existing records are overridden — leave wins.
     */
    public function applyLeaveToAttendance(TeacherLeaveApplication $application): void
    {
        if ($application->status !== TeacherLeaveApplication::STATUS_APPROVED) {
            return;
        }

        $status = $application->is_half_day
            ? TeacherAttendance::STATUS_HALF_DAY
            : TeacherAttendance::STATUS_ON_LEAVE;

        $start = Carbon::parse($application->start_date);
        $end = Carbon::parse($application->end_date);

        for ($date = $start->copy(); $date->lessThanOrEqualTo($end); $date->addDay()) {
            TeacherAttendance::updateOrCreate(
                [
                    'teacher_id' => $application->teacher_id,
                    'attendance_date' => $date->toDateString(),
                ],
                [
                    'tenant_id' => $application->tenant_id,
                    'academic_year_id' => $application->academic_year_id,
                    'status' => $status,
                    'source' => TeacherAttendance::SOURCE_LEAVE,
                    'leave_application_id' => $application->id,
                    'marked_by' => $application->reviewed_by,
                ]
            );
        }
    }

    /**
     * Remove the attendance rows created from a leave application.
     */
    public function removeLeaveFromAttendance(TeacherLeaveApplication $application): void
    {
        TeacherAttendance::where('tenant_id', $application->tenant_id)
            ->where('leave_application_id', $application->id)
            ->where('source', TeacherAttendance::SOURCE_LEAVE)
            ->delete();
    }

    /**
     * Whether the given month's attendance has been finalized.
     */
    public function isMonthFinalized(Tenant $tenant, int $month, int $year): bool
    {
        return TeacherAttendanceFinalization::where('tenant_id', $tenant->id)
            ->where('month', $month)
            ->where('year', $year)
            ->exists();
    }

    /**
     * Finalize a month of attendance: lock every row and store the summary.
     */
    public function finalizeMonth(
        Tenant $tenant,
        User $actor,
        int $month,
        int $year,
        ?string $notes = null,
        ?string $academicYearId = null,
    ): TeacherAttendanceFinalization {
        if ($this->isMonthFinalized($tenant, $month, $year)) {
            throw new RuntimeException('Attendance for this month is already finalized.');
        }

        return DB::transaction(function () use ($tenant, $actor, $month, $year, $notes, $academicYearId) {
            $rows = $this->monthQuery($tenant, $month, $year)->get();
            $summary = $this->summarize($rows);

            $this->monthQuery($tenant, $month, $year)
                ->whereNull('locked_at')
                ->update(['locked_at' => now(), 'updated_at' => now()]);

            return TeacherAttendanceFinalization::create([
                'tenant_id' => $tenant->id,
                'academic_year_id' => $academicYearId,
                'month' => $month,
                'year' => $year,
                'total_days' => $summary['total_records'],
                'working_days' => $summary['working_days'],
                'present_days' => $summary['present_days'],
                'absent_days' => $summary['absent_days'],
                'late_days' => $summary['late_days'],
                'half_day_days' => $summary['half_day_days'],
                'leave_days' => $summary['leave_days'],
                'notes' => $notes,
                'finalized_by' => $actor->id,
                'finalized_at' => now(),
                'created_by' => $actor->id,
            ]);
        });
    }

    /**
     * Attendance summary for a single teacher within a month.
     *
     * @return array<string, mixed>
     */
    public function monthlySummary(Tenant $tenant, string $teacherId, int $month, int $year): array
    {
        $rows = $this->monthQuery($tenant, $month, $year)
            ->where('teacher_id', $teacherId)
            ->get();

        return $this->summarize($rows);
    }

    /**
     * Compute attendance-based deductions/addition for a salary in a month.
     *
     * @return array{summary: array<string, mixed>, deductions: array<string, float>, total_deduction: float, addition: float, per_day_salary: float}
     */
    public function calculateAttendanceAdjustments(
        Tenant $tenant,
        TeacherSalary $salary,
        int $month,
        int $year,
    ): array {
        $settings = $this->settingsFor($tenant);
        $summary = $this->monthlySummary($tenant, $salary->teacher_id, $month, $year);

        $workingDays = max(1, (int) $summary['working_days']);
        $perDaySalary = round(((float) $salary->total) / $workingDays, 2);

        $absentDeduction = round($summary['absent_days'] * $perDaySalary, 2);
        $halfDayDeduction = round($summary['half_day_days'] * 0.5 * $perDaySalary, 2);

        $lateDeduction = 0.0;
        if ($settings->late_deduction_mode === TeacherAttendanceSetting::LATE_DEDUCTION_FIXED) {
            $lateDeduction = round($summary['late_days'] * (float) $settings->late_deduction_fixed_amount, 2);
        } elseif ($settings->late_deduction_mode === TeacherAttendanceSetting::LATE_DEDUCTION_HALF_DAY) {
            $threshold = max(1, (int) $settings->late_to_half_day_threshold);
            $lateDeduction = round(intdiv($summary['late_days'], $threshold) * 0.5 * $perDaySalary, 2);
        }

        $addition = 0.0;
        if ($settings->overtime_enabled) {
            $addition = round($summary['overtime_hours'] * (float) $settings->overtime_hourly_rate, 2);
        }

        $deductions = array_filter([
            'absent' => $absentDeduction,
            'late' => $lateDeduction,
            'half_day' => $halfDayDeduction,
        ], fn (float $amount) => $amount > 0);

        return [
            'summary' => $summary,
            'deductions' => $deductions,
            'total_deduction' => round(array_sum($deductions), 2),
            'addition' => $addition,
            'per_day_salary' => $perDaySalary,
        ];
    }

    /**
     * Enroll (or re-enroll) a teacher's fingerprint template.
     *
     * @param  array<string, mixed>  $data
     */
    public function enrollFingerprint(Tenant $tenant, User $actor, array $data): TeacherFingerprint
    {
        $teacher = Teacher::where('tenant_id', $tenant->id)
            ->where('id', $data['teacher_id'])
            ->firstOrFail();

        if (! in_array((int) $data['finger_index'], TeacherFingerprint::FINGER_INDEXES, true)) {
            throw new RuntimeException('Fingerprint index must be 1 or 2.');
        }

        return TeacherFingerprint::updateOrCreate(
            [
                'teacher_id' => $teacher->id,
                'finger_index' => (int) $data['finger_index'],
            ],
            [
                'tenant_id' => $tenant->id,
                'device_id' => $data['device_id'] ?? null,
                'template_data' => $data['template_data'] ?? null,
                'label' => $data['label'] ?? null,
                'is_active' => true,
                'enrolled_at' => now(),
                'enrolled_by' => $actor->id,
                'created_by' => $actor->id,
            ]
        );
    }

    /**
     * Store raw fingerprint punches (used for online sync and offline upload).
     *
     * @param  array<int, array<string, mixed>>  $punches
     * @return Collection<int, TeacherFingerprintLog>
     */
    public function recordPunches(Tenant $tenant, array $punches): Collection
    {
        return DB::transaction(function () use ($tenant, $punches) {
            $logs = new Collection;

            foreach ($punches as $punch) {
                $teacher = Teacher::where('tenant_id', $tenant->id)
                    ->where('id', $punch['teacher_id'])
                    ->firstOrFail();

                $synced = (bool) ($punch['synced'] ?? true);

                $logs->push(TeacherFingerprintLog::create([
                    'tenant_id' => $tenant->id,
                    'device_id' => $punch['device_id'] ?? null,
                    'teacher_id' => $teacher->id,
                    'punch_time' => $punch['punch_time'],
                    'direction' => $punch['direction'] ?? TeacherFingerprintLog::DIRECTION_ENTRY,
                    'raw_data' => $punch['raw_data'] ?? null,
                    'synced' => $synced,
                    'synced_at' => $synced ? now() : null,
                ]));
            }

            return $logs;
        });
    }

    /**
     * Convert unprocessed punches into attendance records with auto status.
     *
     * @return array{processed: array<int, TeacherAttendance>, skipped: array<int, array<string, string>>}
     */
    public function processPunches(Tenant $tenant, ?string $teacherId = null, ?string $date = null): array
    {
        $settings = $this->settingsFor($tenant);

        $query = TeacherFingerprintLog::where('tenant_id', $tenant->id)
            ->where('processed', false);

        if ($teacherId !== null) {
            $query->where('teacher_id', $teacherId);
        }

        if ($date !== null) {
            $query->whereDate('punch_time', $date);
        }

        $logs = $query->orderBy('punch_time')->get();

        $processed = [];
        $skipped = [];

        foreach ($logs->groupBy(fn (TeacherFingerprintLog $log) => $log->teacher_id.'|'.$log->punch_time->toDateString()) as $group) {
            /** @var Collection<int, TeacherFingerprintLog> $sorted */
            $sorted = $group->sortBy('punch_time')->values();
            $first = $sorted->first();
            $attendanceDate = $first->punch_time->toDateString();

            if ($this->isMonthFinalized($tenant, (int) $first->punch_time->month, (int) $first->punch_time->year)) {
                $skipped[] = ['teacher_id' => $first->teacher_id, 'attendance_date' => $attendanceDate, 'reason' => 'month_finalized'];

                continue;
            }

            $existing = TeacherAttendance::where('teacher_id', $first->teacher_id)
                ->where('attendance_date', $attendanceDate)
                ->first();

            if ($existing?->isLocked()) {
                $skipped[] = ['teacher_id' => $first->teacher_id, 'attendance_date' => $attendanceDate, 'reason' => 'locked'];

                continue;
            }

            $checkIn = Carbon::parse($sorted->first()->punch_time);
            $checkOut = $sorted->count() > 1 ? Carbon::parse($sorted->last()->punch_time) : null;

            $status = $this->fingerprintStatus($checkIn, $checkOut, $settings);

            $workHours = $checkOut !== null
                ? round($checkIn->diffInMinutes($checkOut) / 60, 2)
                : null;

            $overtime = 0.0;
            if ($workHours !== null && (float) $settings->overtime_after_hours > 0) {
                $overtime = max(0, round($workHours - (float) $settings->overtime_after_hours, 2));
            }

            $deviceName = $first->device?->device_name;

            $record = TeacherAttendance::updateOrCreate(
                [
                    'teacher_id' => $first->teacher_id,
                    'attendance_date' => $attendanceDate,
                ],
                [
                    'tenant_id' => $tenant->id,
                    'academic_year_id' => $existing?->academic_year_id,
                    'check_in_time' => $checkIn->format('H:i:s'),
                    'check_out_time' => $checkOut?->format('H:i:s'),
                    'status' => $status,
                    'source' => TeacherAttendance::SOURCE_FINGERPRINT,
                    'device_info' => $deviceName ?? $first->device_id,
                    'work_hours' => $workHours,
                    'overtime_hours' => $overtime,
                ]
            );

            TeacherFingerprintLog::whereIn('id', $group->pluck('id'))->update([
                'processed' => true,
                'processed_at' => now(),
            ]);

            $processed[] = $record->fresh();
        }

        return ['processed' => $processed, 'skipped' => $skipped];
    }

    /**
     * Derive attendance status from a day's first/last punch.
     */
    private function fingerprintStatus(Carbon $checkIn, ?Carbon $checkOut, TeacherAttendanceSetting $settings): string
    {
        if ($checkOut === null) {
            return TeacherAttendance::STATUS_HALF_DAY;
        }

        if ($settings->late_after_time !== null) {
            $lateAfter = Carbon::parse($checkIn->toDateString().' '.$settings->late_after_time);

            if ($checkIn->greaterThan($lateAfter)) {
                return TeacherAttendance::STATUS_LATE;
            }
        }

        return TeacherAttendance::STATUS_PRESENT;
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<TeacherAttendance>
     */
    private function monthQuery(Tenant $tenant, int $month, int $year)
    {
        return TeacherAttendance::where('tenant_id', $tenant->id)
            ->whereMonth('attendance_date', $month)
            ->whereYear('attendance_date', $year);
    }

    /**
     * @param  Collection<int, TeacherAttendance>  $rows
     * @return array<string, mixed>
     */
    private function summarize(Collection $rows): array
    {
        return [
            'total_records' => $rows->count(),
            'working_days' => $rows->pluck('attendance_date')->unique()->count(),
            'present_days' => $rows->where('status', TeacherAttendance::STATUS_PRESENT)->count(),
            'absent_days' => $rows->where('status', TeacherAttendance::STATUS_ABSENT)->count(),
            'late_days' => $rows->where('status', TeacherAttendance::STATUS_LATE)->count(),
            'half_day_days' => $rows->where('status', TeacherAttendance::STATUS_HALF_DAY)->count(),
            'leave_days' => $rows->where('status', TeacherAttendance::STATUS_ON_LEAVE)->count(),
            'overtime_hours' => round((float) $rows->sum('overtime_hours'), 2),
        ];
    }
}
