<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SalaryStructure;
use App\Models\Teacher;
use App\Models\TeacherPayslip;
use App\Models\TeacherSalary;
use App\Services\TeacherAttendanceService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Teacher Management > Salary Management', weight: 58)]
class SalaryController extends Controller
{
    public function __construct(private TeacherAttendanceService $attendance)
    {
    }

    // ── Salary Structures ────────────────────────────────────────────────

    public function structures(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $structures = SalaryStructure::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->orderBy('structure_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $structures,
        ]);
    }

    public function storeStructure(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'structure_name' => ['required', 'string', 'max:100'],
            'structure_name_bn' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'basic_pay' => ['required', 'numeric', 'min:0'],
            'house_rent' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'array'],
            'other_allowances.*' => ['numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $basic = (float) $validated['basic_pay'];
        $houseRent = (float) ($validated['house_rent'] ?? 0);
        $medical = (float) ($validated['medical_allowance'] ?? 0);
        $transport = (float) ($validated['transport_allowance'] ?? 0);
        $other = $validated['other_allowances'] ?? [];
        $otherTotal = array_sum($other);
        $total = $basic + $houseRent + $medical + $transport + $otherTotal;

        $structure = SalaryStructure::create([
            'tenant_id' => $tenant->id,
            'structure_name' => $validated['structure_name'],
            'structure_name_bn' => $validated['structure_name_bn'] ?? null,
            'description' => $validated['description'] ?? null,
            'basic_pay' => $basic,
            'house_rent' => $houseRent,
            'medical_allowance' => $medical,
            'transport_allowance' => $transport,
            'other_allowances' => $other,
            'total' => $total,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Salary structure created successfully.',
            'data' => $structure->fresh(),
        ], 201);
    }

    public function updateStructure(Request $request, SalaryStructure $structure): JsonResponse
    {
        if ($structure->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'structure_name' => ['sometimes', 'required', 'string', 'max:100'],
            'structure_name_bn' => ['nullable', 'string', 'max:200'],
            'description' => ['nullable', 'string'],
            'basic_pay' => ['sometimes', 'required', 'numeric', 'min:0'],
            'house_rent' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'array'],
            'other_allowances.*' => ['numeric', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data = $validated;
        $basic = (float) ($data['basic_pay'] ?? $structure->basic_pay);
        $houseRent = (float) ($data['house_rent'] ?? $structure->house_rent);
        $medical = (float) ($data['medical_allowance'] ?? $structure->medical_allowance);
        $transport = (float) ($data['transport_allowance'] ?? $structure->transport_allowance);
        $other = $data['other_allowances'] ?? $structure->other_allowances;
        $otherTotal = is_array($other) ? array_sum($other) : 0;
        $data['total'] = $basic + $houseRent + $medical + $transport + $otherTotal;

        $structure->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Salary structure updated successfully.',
            'data' => $structure->fresh(),
        ]);
    }

    public function deleteStructure(Request $request, SalaryStructure $structure): JsonResponse
    {
        if ($structure->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $structure->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Salary structure deleted successfully.',
        ]);
    }

    // ── Teacher Salaries ─────────────────────────────────────────────────

    public function salaries(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherSalary::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'salaryStructure', 'academicYear']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('academic_year_id')) {
            $query->where('academic_year_id', $request->academic_year_id);
        }

        $salaries = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $salaries,
        ]);
    }

    public function storeSalary(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'salary_structure_id' => ['nullable', 'uuid', 'exists:salary_structures,id'],
            'academic_year_id' => ['nullable', 'uuid', 'exists:academic_years,id'],
            'basic_pay' => ['required', 'numeric', 'min:0'],
            'house_rent' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'array'],
            'other_allowances.*' => ['numeric', 'min:0'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ]);

        Teacher::where('tenant_id', $tenant->id)
            ->where('id', $validated['teacher_id'])
            ->firstOrFail();

        $basic = (float) $validated['basic_pay'];
        $houseRent = (float) ($validated['house_rent'] ?? 0);
        $medical = (float) ($validated['medical_allowance'] ?? 0);
        $transport = (float) ($validated['transport_allowance'] ?? 0);
        $other = $validated['other_allowances'] ?? [];
        $total = $basic + $houseRent + $medical + $transport + array_sum($other);

        $salary = TeacherSalary::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => $validated['teacher_id'],
            'salary_structure_id' => $validated['salary_structure_id'] ?? null,
            'academic_year_id' => $validated['academic_year_id'] ?? null,
            'basic_pay' => $basic,
            'house_rent' => $houseRent,
            'medical_allowance' => $medical,
            'transport_allowance' => $transport,
            'other_allowances' => $other,
            'total' => $total,
            'bank_account_no' => $validated['bank_account_no'] ?? null,
            'effective_from' => $validated['effective_from'] ?? null,
            'effective_to' => $validated['effective_to'] ?? null,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher salary created successfully.',
            'data' => $salary->fresh()->load(['teacher.user', 'salaryStructure']),
        ], 201);
    }

    public function updateSalary(Request $request, TeacherSalary $salary): JsonResponse
    {
        if ($salary->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'basic_pay' => ['sometimes', 'required', 'numeric', 'min:0'],
            'house_rent' => ['nullable', 'numeric', 'min:0'],
            'medical_allowance' => ['nullable', 'numeric', 'min:0'],
            'transport_allowance' => ['nullable', 'numeric', 'min:0'],
            'other_allowances' => ['nullable', 'array'],
            'other_allowances.*' => ['numeric', 'min:0'],
            'bank_account_no' => ['nullable', 'string', 'max:50'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $data = $validated;
        $basic = (float) ($data['basic_pay'] ?? $salary->basic_pay);
        $houseRent = (float) ($data['house_rent'] ?? $salary->house_rent);
        $medical = (float) ($data['medical_allowance'] ?? $salary->medical_allowance);
        $transport = (float) ($data['transport_allowance'] ?? $salary->transport_allowance);
        $other = $data['other_allowances'] ?? $salary->other_allowances;
        $otherTotal = is_array($other) ? array_sum($other) : 0;
        $data['total'] = $basic + $houseRent + $medical + $transport + $otherTotal;

        $salary->update($data);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher salary updated successfully.',
            'data' => $salary->fresh()->load(['teacher.user', 'salaryStructure']),
        ]);
    }

    // ── Payslips ─────────────────────────────────────────────────────────

    public function payslips(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherPayslip::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'teacherSalary', 'academicYear']);

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        if ($request->filled('month')) {
            $query->where('month', $request->month);
        }

        if ($request->filled('year')) {
            $query->where('year', $request->year);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $payslips = $query->orderBy('year', 'desc')->orderBy('month', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $payslips,
        ]);
    }

    public function generatePayslips(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'month' => ['required', 'integer', 'between:1,12'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'teacher_ids' => ['nullable', 'array'],
            'teacher_ids.*' => ['uuid', 'exists:teachers,id'],
        ]);

        $month = $validated['month'];
        $year = $validated['year'];

        if (! $this->attendance->isMonthFinalized($tenant, $month, $year)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Attendance for the selected month has not been finalized.',
            ], 422);
        }

        $query = TeacherSalary::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->with('teacher');

        if (! empty($validated['teacher_ids'])) {
            $query->whereIn('teacher_id', $validated['teacher_ids']);
        }

        $salaries = $query->get();

        if ($salaries->isEmpty()) {
            return response()->json([
                'status' => 'error',
                'message' => 'No active teacher salaries found for the given criteria.',
            ], 422);
        }

        $payslips = [];

        DB::transaction(function () use ($tenant, $salaries, $month, $year, $request, &$payslips) {
            foreach ($salaries as $salary) {
                $otherAllowances = $salary->other_allowances ?? [];

                $adjustments = $this->attendance->calculateAttendanceAdjustments($tenant, $salary, $month, $year);
                $attendanceAddition = $adjustments['addition'];
                $grossPay = $salary->total + $attendanceAddition;

                $deductions = [
                    'tax' => round($grossPay * 0.05, 2),
                    'provident_fund' => round($salary->basic_pay * 0.1, 2),
                ];
                $deductions = array_merge($deductions, $adjustments['deductions']);
                $totalDeductions = round(array_sum($deductions), 2);
                $netPay = round($grossPay - $totalDeductions, 2);

                $payslip = TeacherPayslip::updateOrCreate(
                    [
                        'teacher_id' => $salary->teacher_id,
                        'month' => $month,
                        'year' => $year,
                    ],
                    [
                        'tenant_id' => $tenant->id,
                        'teacher_salary_id' => $salary->id,
                        'academic_year_id' => $salary->academic_year_id,
                        'basic_pay' => $salary->basic_pay,
                        'house_rent' => $salary->house_rent,
                        'medical_allowance' => $salary->medical_allowance,
                        'transport_allowance' => $salary->transport_allowance,
                        'other_allowances' => $otherAllowances,
                        'gross_pay' => $grossPay,
                        'deductions' => $deductions,
                        'total_deductions' => $totalDeductions,
                        'net_pay' => $netPay,
                        'attendance_summary' => [
                            ...$adjustments['summary'],
                            'per_day_salary' => $adjustments['per_day_salary'],
                            'attendance_deduction' => $adjustments['total_deduction'],
                            'attendance_addition' => $attendanceAddition,
                        ],
                        'attendance_deduction' => $adjustments['total_deduction'],
                        'attendance_addition' => $attendanceAddition,
                        'status' => TeacherPayslip::STATUS_PENDING,
                        'created_by' => $request->user()->id,
                    ]
                );

                $payslips[] = $payslip->fresh()->load(['teacher.user', 'teacherSalary']);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => count($payslips).' payslip(s) generated successfully.',
            'data' => $payslips,
        ], 201);
    }

    public function markPaid(Request $request, TeacherPayslip $payslip): JsonResponse
    {
        if ($payslip->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        if ($payslip->status !== TeacherPayslip::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending payslips can be marked as paid.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_date' => ['required', 'date'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'transaction_id' => ['nullable', 'string', 'max:100'],
            'remarks' => ['nullable', 'string'],
        ]);

        $payslip->update([
            'status' => TeacherPayslip::STATUS_PAID,
            'payment_date' => $validated['payment_date'],
            'payment_method' => $validated['payment_method'] ?? null,
            'transaction_id' => $validated['transaction_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payslip marked as paid successfully.',
            'data' => $payslip->fresh()->load(['teacher.user', 'teacherSalary']),
        ]);
    }
}
