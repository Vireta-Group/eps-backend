<?php

namespace App\Services\Payroll\Loan;

use App\Models\StaffLoan;
use App\Models\LoanEmi;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class LoanService
{
    /**
     * Create a loan and generate its EMI schedule.
     * 
     * @param array $data
     * @return StaffLoan
     */
    public function createLoan(array $data): StaffLoan
    {
        return DB::transaction(function () use ($data) {
            // 1. Create the loan record
            $loan = StaffLoan::create($data);

            // 2. Generate EMI schedule (Amortization)
            $this->generateEmiSchedule($loan);

            return $loan;
        });
    }

    /**
     * Generate the EMI schedule for a loan.
     */
    protected function generateEmiSchedule(StaffLoan $loan): void
    {
        $principal = $loan->loan_amount;
        $annualRate = $loan->interest_rate;
        $months = $loan->term_months;
        
        // Monthly Interest Rate
        $monthlyRate = ($annualRate / 100) / 12;
        
        // EMI Calculation: P * r * (1+r)^n / ((1+r)^n - 1)
        $emi = 0;
        if ($monthlyRate > 0) {
            $emi = $principal * $monthlyRate * pow(1 + $monthlyRate, $months) / (pow(1 + $monthlyRate, $months) - 1);
        } else {
            $emi = $principal / $months;
        }

        // Update the loan with calculated EMI
        $loan->update(['emi_amount' => $emi]);

        $remainingPrincipal = $principal;
        $startDate = Carbon::parse($loan->disbursement_date);

        for ($i = 1; $i <= $months; $i++) {
            $interestForMonth = $remainingPrincipal * $monthlyRate;
            $principalForMonth = $emi - $interestForMonth;
            
            $dueDate = $startDate->copy()->addMonths($i);

            LoanEmi::create([
                'staff_loan_id' => $loan->id,
                'emi_number' => $i,
                'principal_amount' => $principalForMonth,
                'interest_amount' => $interestForMonth,
                'total_emi' => $emi,
                'due_date' => $dueDate,
                'status' => 'pending',
            ]);

            $remainingPrincipal -= $principalForMonth;
        }
    }

    /**
     * Get current pending EMI for a staff member to be deducted from salary.
     */
    public function getPendingEmi($staffId, string $staffType): ?LoanEmi
    {
        return LoanEmi::whereHas('loan', function ($query) use ($staffId, $staffType) {
            $query->where('staff_id', $staffId)->where('staff_type', $staffType)->where('status', 'active');
        })
        ->where('status', 'pending')
        ->orderBy('due_date', 'asc')
        ->first();
    }
}
