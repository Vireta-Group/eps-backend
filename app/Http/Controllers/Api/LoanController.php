<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StaffLoan;
use App\Models\LoanEmi;
use App\Services\Payroll\Loan\LoanService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('HR Management', description: 'Payroll, staff loans, performance appraisal, and HR analytics.', weight: 90)]
class LoanController extends Controller
{
    public function __construct(
        protected LoanService $loanService
    ) {}

    /**
     * Request a new loan.
     * 
     * @operationId loan.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'staff_id' => ['required', 'uuid'],
            'staff_type' => ['required', Rule::in(['teacher', 'employee'])],
            'loan_amount' => ['required', 'numeric', 'min:0'],
            'interest_rate' => ['nullable', 'numeric', 'min:0'],
            'term_months' => ['required', 'integer', 'min:1'],
            'disbursement_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            $loan = $this->loanService->createLoan(array_merge($validated, [
                'tenant_id' => $tenant->id,
                'status' => 'pending',
                'created_by' => $request->user()->id,
                'end_date' => \Carbon\Carbon::parse($validated['disbursement_date'])->addMonths($validated['term_months']),
            ]));

            return response()->json([
                'status' => 'success',
                'message' => 'Loan application submitted successfully.',
                'data' => $loan,
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Loan creation failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a loan.
     * 
     * @operationId loan.approve
     */
    public function approve(Request $request, StaffLoan $loan): JsonResponse
    {
        if ($loan->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $loan->update([
            'status' => 'active',
            'approved_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Loan approved and activated successfully.',
            'data' => $loan,
        ]);
    }

    /**
     * List all loans for the tenant.
     * 
     * @operationId loan.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $loans = StaffLoan::where('tenant_id', $tenant->id)
            ->with('emis')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $loans,
        ]);
    }

    /**
     * Show a specific loan and its EMI schedule.
     * 
     * @operationId loan.show
     */
    public function show(Request $request, StaffLoan $loan): JsonResponse
    {
        if ($loan->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $loan->load('emis'),
        ]);
    }
}
