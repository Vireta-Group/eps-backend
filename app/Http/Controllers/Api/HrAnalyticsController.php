<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\PayrollProcess;
use App\Models\PayrollEntry;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('HR Management', description: 'Payroll, staff loans, performance appraisal, and HR analytics.', weight: 90)]
class HrAnalyticsController extends Controller
{
    /**
     * General HR Dashboard stats.
     * 
     * @operationId hr.analytics.dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant->id;

        $teacherCount = Teacher::where('tenant_id', $tenantId)->where('is_active', true)->count();
        $employeeCount = Employee::where('tenant_id', $tenantId)->where('is_active', true)->count();
        
        // Last month total payroll cost
        $lastPayroll = PayrollProcess::where('tenant_id', $tenantId)
            ->orderBy('processed_at', 'desc')
            ->first();
        
        $totalPayrollCost = 0;
        if ($lastPayroll) {
            $totalPayrollCost = PayrollEntry::where('payroll_process_id', $lastPayroll->id)->sum('net_salary');
        }

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_staff' => $teacherCount + $employeeCount,
                'teachers' => $teacherCount,
                'employees' => $employeeCount,
                'last_month_payroll_cost' => $totalPayrollCost,
                'last_payroll_period' => $lastPayroll?->month . ' ' . $lastPayroll?->year,
            ],
        ]);
    }

    /**
     * Payroll cost analytics by staff type.
     * 
     * @operationId hr.analytics.payroll_cost
     */
    public function payrollCostAnalysis(Request $request): JsonResponse
    {
        $tenantId = $request->user()->tenant->id;
        $month = $request->query('month'); // YYYY-MM
        $year = $request->query('year');

        if (!$month || !$year) {
            return response()->json(['status' => 'error', 'message' => 'Month and Year are required.'], 400);
        }

        $process = PayrollProcess::where('tenant_id', $tenantId)
            ->where('month', $month)
            ->where('year', $year)
            ->first();

        if (!$process) {
            return response()->json(['status' => 'error', 'message' => 'Payroll process not found for this period.'], 404);
        }

        $teacherCost = PayrollEntry::where('payroll_process_id', $process->id)
            ->where('staff_type', 'teacher')
            ->sum('net_salary');

        $employeeCost = PayrollEntry::where('payroll_process_id', $process->id)
            ->where('staff_type', 'employee')
            ->sum('net_salary');

        return response()->json([
            'status' => 'success',
            'data' => [
                'period' => "$month $year",
                'teacher_total_cost' => $teacherCost,
                'employee_total_cost' => $employeeCost,
                'overall_cost' => $teacherCost + $employeeCost,
            ],
        ]);
    }
}
