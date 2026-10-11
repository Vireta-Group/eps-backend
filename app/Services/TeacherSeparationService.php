<?php

namespace App\Services;

use App\Models\Teacher;
use App\Models\TeacherLeaveBalance;
use App\Models\TeacherSalary;
use App\Models\TeacherSeparation;
use Carbon\Carbon;
use Illuminate\Support\Str;

class TeacherSeparationService
{
    /**
     * Generate a human-readable, tenant-scoped reference such as `RES-2026-4F9A2C`.
     */
    public function generateReference(string $prefix): string
    {
        return strtoupper($prefix).'-'.now()->format('Y').'-'.strtoupper(Str::random(6));
    }

    /**
     * Calculate a final settlement breakdown for a teacher.
     *
     * Amounts that the platform does not track (loans, advances, provident
     * fund, gratuity, festival bonus) can be supplied through `$options`.
     *
     * @param  array<string, float|int|string|null>  $options
     * @return array<string, mixed>
     */
    public function calculateSettlement(Teacher $teacher, Carbon|string $lastWorkingDay, array $options = []): array
    {
        $last = Carbon::parse($lastWorkingDay);
        $monthly = $this->monthlySalary($teacher, $last);
        $perDay = round($monthly / 30, 2);

        $workedDays = (int) $last->format('d');
        $lastMonthSalary = round($perDay * $workedDays, 2);
        $leaveEncashment = round($perDay * $this->paidLeaveRemainingDays($teacher), 2);
        $noticePay = array_key_exists('notice_pay', $options)
            ? round((float) $options['notice_pay'], 2)
            : 0.0;

        $components = [
            'last_month_salary_prorata' => $lastMonthSalary,
            'unpaid_salary' => round((float) ($options['unpaid_salary'] ?? 0), 2),
            'earned_leave_encashment' => $leaveEncashment,
            'festival_bonus' => round((float) ($options['festival_bonus'] ?? 0), 2),
            'provident_fund' => round((float) ($options['provident_fund'] ?? 0), 2),
            'gratuity' => round((float) ($options['gratuity'] ?? 0), 2),
            'notice_pay' => $noticePay,
        ];

        $deductions = [
            'loan' => round((float) ($options['loan'] ?? 0), 2),
            'advance' => round((float) ($options['advance'] ?? 0), 2),
            'notice_penalty' => round((float) ($options['notice_penalty'] ?? 0), 2),
            'other' => round((float) ($options['other_deductions'] ?? 0), 2),
        ];

        $gross = round(array_sum($components), 2);
        $totalDeductions = round(array_sum($deductions), 2);

        return [
            'components' => $components,
            'gross_amount' => $gross,
            'deductions' => $deductions,
            'total_deductions' => $totalDeductions,
            'net_amount' => round($gross - $totalDeductions, 2),
            'monthly_salary' => $monthly,
            'per_day_salary' => $perDay,
        ];
    }

    /**
     * Derive the notice amount when a teacher did not serve (and did not waive) notice.
     */
    public function noticeAmount(TeacherSeparation $separation, float $perDaySalary): float
    {
        if ($separation->notice_served || $separation->notice_waived) {
            return 0.0;
        }

        if ((float) $separation->notice_penalty_amount > 0) {
            return (float) $separation->notice_penalty_amount;
        }

        return round($perDaySalary * (int) $separation->notice_period_days, 2);
    }

    private function monthlySalary(Teacher $teacher, Carbon $asOf): float
    {
        $salary = TeacherSalary::where('teacher_id', $teacher->id)
            ->where('is_active', true)
            ->where('effective_from', '<=', $asOf->toDateString())
            ->orderByDesc('effective_from')
            ->first();

        return $salary ? (float) $salary->total : 0.0;
    }

    private function paidLeaveRemainingDays(Teacher $teacher): float
    {
        return (float) TeacherLeaveBalance::where('teacher_id', $teacher->id)
            ->whereHas('leaveType', fn ($query) => $query->where('is_paid', true))
            ->sum('remaining_days');
    }
}
