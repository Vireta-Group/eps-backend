<?php

namespace App\Services\Payroll;

use App\Models\SalaryComponent;
use App\Models\TaxSlab;
use App\Models\Employee;
use App\Models\Teacher;
use App\Models\PayrollProcess;
use App\Models\PayrollEntry;
use App\Services\Payroll\Loan\LoanService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PayrollService
{
    public function __construct(
        protected LoanService $loanService
    ) {}

    /**
     * Run bulk payroll processing for all staff members.
     * 
     * @param string $tenantId
     * @param string $month format YYYY-MM
     * @param string $year
     * @param string $userId processed by
     * @return PayrollProcess
     */
    public function processBulkPayroll(string $tenantId, string $month, string $year, string $userId)
    {
        return DB::transaction(function () use ($tenantId, $month, $year, $userId) {
            $process = PayrollProcess::create([
                'tenant_id' => $tenantId,
                'month' => $month,
                'year' => $year,
                'status' => 'draft',
                'processed_by' => $userId,
                'processed_at' => now(),
            ]);

            $teachers = Teacher::where('tenant_id', $tenantId)->where('is_active', true)->get();
            $employees = Employee::where('tenant_id', $tenantId)->where('is_active', true)->get();

            foreach ($teachers as $teacher) {
                $calc = $this->calculateSalary($teacher);
                $this->createPayrollEntry($process->id, $teacher, 'teacher', $calc);
            }

            foreach ($employees as $employee) {
                $calc = $this->calculateSalary($employee);
                $this->createPayrollEntry($process->id, $employee, 'employee', $calc);
            }

            return $process;
        });
    }

    /**
     * Create a payroll entry for a staff member.
     */
    protected function createPayrollEntry($processId, $staff, string $type, array $calc): void
    {
        PayrollEntry::create([
            'payroll_process_id' => $processId,
            'staff_id' => $staff->id,
            'staff_type' => $type,
            'basic_salary' => $calc['basic_salary'],
            'earnings' => $calc['earnings'],
            'deductions' => $calc['deductions'],
            'gross_salary' => $calc['gross_salary'],
            'taxable_income' => $calc['taxable_income'],
            'tax_amount' => $calc['tax_amount'],
            'total_deductions' => $calc['total_deductions'],
            'net_salary' => $calc['net_salary'],
            'payment_status' => 'pending',
        ]);
    }

    /**
     * Calculate the gross and net salary for a staff member.
     * 
     * @param mixed $staff The Employee or Teacher model
     * @param array $customComponentValues Optional overrides for component values
     * @return array
     */
    public function calculateSalary($staff, array $customComponentValues = []): array
    {
        $tenantId = $staff->tenant_id;
        $staffType = ($staff instanceof Teacher) ? 'teacher' : 'employee';

        $components = SalaryComponent::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->get();

        $basicSalary = $this->resolveBasicSalary($staff);
        $earnings = [];
        $deductions = [];
        $grossSalary = 0;
        $taxableIncome = 0;

        foreach ($components as $component) {
            $value = $this->calculateComponentValue($component, $basicSalary, $customComponentValues);

            if ($component->type === 'earning') {
                $earnings[$component->short_code] = $value;
                $grossSalary += $value;
                if ($component->is_taxable) {
                    $taxableIncome += $value;
                }
            } else {
                $deductions[$component->short_code] = $value;
            }
        }

        $taxAmount = $this->calculateTax($tenantId, $taxableIncome);
        $deductions['TDS'] = $taxAmount;
        $totalDeductions = array_sum($deductions);

        $pendingEmi = $this->loanService->getPendingEmi($staff->id, $staffType);
        if ($pendingEmi) {
            $deductions['LOAN_EMI'] = $pendingEmi->total_emi;
            $totalDeductions += $pendingEmi->total_emi;
        }

        $netSalary = $grossSalary - $totalDeductions;

        return [
            'staff_id' => $staff->id,
            'basic_salary' => $basicSalary,
            'earnings' => $earnings,
            'deductions' => $deductions,
            'gross_salary' => $grossSalary,
            'taxable_income' => $taxableIncome,
            'tax_amount' => $taxAmount,
            'total_deductions' => $totalDeductions,
            'net_salary' => $netSalary,
        ];
    }

    /**
     * Resolve the basic salary for the staff.
     */
    protected function resolveBasicSalary($staff): float
    {
        return (float) ($staff->basic_salary ?? 0);
    }

    /**
     * Calculate the value of a specific salary component.
     */
    protected function calculateComponentValue(SalaryComponent $component, float $basicSalary, array $customValues): float
    {
        if (isset($customValues[$component->short_code])) {
            return (float) $customValues[$component->short_code];
        }

        switch ($component->calculation_type) {
            case 'percentage_of_basic':
                return ($basicSalary * $component->default_value) / 100;
            case 'percentage_of_gross':
                return ($basicSalary * $component->default_value) / 100;
            case 'fixed':
            default:
                return (float) $component->default_value;
        }
    }

    /**
     * Calculate Income Tax based on active slabs.
     */
    public function calculateTax(string $tenantId, float $taxableIncome): float
    {
        $slabs = TaxSlab::where('tenant_id', $tenantId)
            ->where('is_active', true)
            ->orderBy('min_income', 'asc')
            ->get();

        if ($slabs->isEmpty()) {
            return 0;
        }

        $tax = 0;
        $remainingIncome = $taxableIncome;

        foreach ($slabs as $slab) {
            if ($remainingIncome <= 0) break;

            $slabMax = $slab->max_income ?? PHP_FLOAT_MAX;
            $slabRange = $slabMax - $slab->min_income;
            $taxableInThisSlab = min($remainingIncome, $slabRange);

            $tax += ($taxableInThisSlab * $slab->tax_rate) / 100;
            $remainingIncome -= $taxableInThisSlab;
        }

        return $tax;
    }
}
