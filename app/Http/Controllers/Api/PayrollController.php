<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryComponent;
use App\Models\SalaryGrade;
use App\Models\TaxSlab;
use App\Models\Employee;
use App\Models\Teacher;
use App\Services\Payroll\PayrollService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('HR Management', description: 'Payroll, staff loans, performance appraisal, and HR analytics.', weight: 90)]
class PayrollController extends Controller
{
    public function __construct(
        protected PayrollService $payrollService
    ) {}

    /**
     * List all payroll processes.
     * 
     * @operationId payroll.processes.index
     */
    public function indexProcesses(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $processes = \App\Models\PayrollProcess::where('tenant_id', $tenant->id)
            ->orderBy('month', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $processes,
        ]);
    }

    /**
     * Show details of a specific payroll process.
     * 
     * @operationId payroll.processes.show
     */
    public function showProcess(Request $request, $id): JsonResponse
    {
        $process = \App\Models\PayrollProcess::where('tenant_id', $request->user()->tenant->id)
            ->with('entries')
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $process,
        ]);
    }

    /**
     * Create a new bulk payroll process for the month.
     * 
     * @operationId payroll.bulk.process
     */
    public function processBulk(Request $request): JsonResponse
    {
        $request->validate([
            'month' => ['required', 'string', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/'],
            'year' => ['required', 'string', 'max:4'],
        ]);

        try {
            $process = $this->payrollService->processBulkPayroll(
                $request->user()->tenant->id,
                $request->month,
                $request->year,
                $request->user()->id
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Bulk payroll processed successfully in draft mode.',
                'data' => $process->load('entries'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payroll processing failed: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lock a payroll process to prevent further changes.
     * 
     * @operationId payroll.bulk.lock
     */
    public function lockProcess(Request $request, $id): JsonResponse
    {
        $process = \App\Models\PayrollProcess::where('tenant_id', $request->user()->tenant->id)
            ->findOrFail($id);

        $process->update([
            'status' => 'locked',
            'locked_at' => now(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payroll process locked successfully.',
            'data' => $process,
        ]);
    }

    /**
     * List all salary components.
     * 
     * @operationId payroll.components.index
     */
    public function indexComponents(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $components = SalaryComponent::where('tenant_id', $tenant->id)
            ->orderBy('name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $components,
        ]);
    }

    /**
     * Create a salary component.
     * 
     * @operationId payroll.components.store
     */
    public function storeComponent(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'short_code' => ['required', 'string', 'max:20', 'unique:salary_components,short_code'],
            'type' => ['required', Rule::in(['earning', 'deduction'])],
            'calculation_type' => ['required', Rule::in(['fixed', 'percentage_of_basic', 'percentage_of_gross'])],
            'default_value' => ['required', 'numeric', 'min:0'],
            'is_taxable' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $component = SalaryComponent::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Salary component created successfully.',
            'data' => $component,
        ], 201);
    }

    /**
     * List all salary grades.
     * 
     * @operationId payroll.grades.index
     */
    public function indexGrades(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $grades = SalaryGrade::where('tenant_id', $tenant->id)
            ->orderBy('grade_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $grades,
        ]);
    }

    /**
     * Create a salary grade.
     * 
     * @operationId payroll.grades.store
     */
    public function storeGrade(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'grade_name' => ['required', 'string', 'max:100'],
            'short_code' => ['required', 'string', 'max:20', 'unique:salary_grades,short_code'],
            'min_basic' => ['required', 'numeric', 'min:0'],
            'max_basic' => ['required', 'numeric', 'min:0'],
            'increment_step' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $grade = SalaryGrade::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Salary grade created successfully.',
            'data' => $grade,
        ], 201);
    }

    /**
     * List all tax slabs.
     * 
     * @operationId payroll.tax.index
     */
    public function indexTaxSlabs(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $slabs = \App\Models\TaxSlab::where('tenant_id', $tenant->id)
            ->orderBy('min_income', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $slabs,
        ]);
    }

    /**
     * Create a tax slab.
     * 
     * @operationId payroll.tax.store
     */
    public function storeTaxSlab(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'min_income' => ['required', 'numeric', 'min:0'],
            'max_income' => ['nullable', 'numeric', 'min:0'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'slab_name' => ['nullable', 'string', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $slab = \App\Models\TaxSlab::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Tax slab created successfully.',
            'data' => $slab,
        ], 201);
    }

    /**
     * Calculate a preview salary for a specific staff member.
     * 
     * @operationId payroll.calculate.preview
     */
    public function calculatePreview(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => ['required', 'uuid'],
            'staff_type' => ['required', Rule::in(['employee', 'teacher'])],
            'custom_values' => ['nullable', 'array'],
        ]);

        $staff = match ($request->staff_type) {
            'employee' => Employee::where('tenant_id', $request->user()->tenant->id)->findOrFail($request->staff_id),
            'teacher' => Teacher::where('tenant_id', $request->user()->tenant->id)->findOrFail($request->staff_id),
        };

        $result = $this->payrollService->calculateSalary($staff, $request->custom_values ?? []);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }
}