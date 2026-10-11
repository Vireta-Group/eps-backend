<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherSeparation;
use App\Models\TeacherSettlement;
use App\Services\TeacherSeparationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Teacher Management > Transfer & Resignation', description: 'Manage teacher resignations, terminations, documents, and final settlements.', weight: 65)]
class TeacherSettlementController extends Controller
{
    public function __construct(private TeacherSeparationService $separation)
    {
    }

    /**
     * List final settlements for the tenant.
     *
     * @operationId teacherSettlement.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherSettlement::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'separation', 'approvedBy']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Preview a final settlement without saving it.
     *
     * @operationId teacherSettlement.preview
     */
    public function preview(Request $request): JsonResponse
    {
        $teacher = $this->resolveTeacher($request);

        $result = $this->calculate($teacher, $request);

        return response()->json([
            'status' => 'success',
            'data' => $result,
        ]);
    }

    /**
     * Create a final settlement.
     *
     * @operationId teacherSettlement.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $teacher = $this->resolveTeacher($request);
        $result = $this->calculate($teacher, $request);

        $separation = null;

        if ($request->filled('separation_id')) {
            $separation = TeacherSeparation::where('tenant_id', $tenant->id)
                ->where('id', $request->separation_id)
                ->firstOrFail();
        }

        $settlement = TeacherSettlement::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => $teacher->id,
            'separation_id' => $separation?->id,
            'last_working_day' => $request->last_working_day,
            'components' => $result['components'],
            'gross_amount' => $result['gross_amount'],
            'deductions' => $result['deductions'],
            'total_deductions' => $result['total_deductions'],
            'net_amount' => $result['net_amount'],
            'status' => TeacherSettlement::STATUS_DRAFT,
            'remarks' => $request->input('remarks'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Final settlement created successfully.',
            'data' => $settlement->fresh()->load(['teacher.user', 'separation']),
        ], 201);
    }

    /**
     * Show a final settlement.
     *
     * @operationId teacherSettlement.show
     */
    public function show(Request $request, TeacherSettlement $settlement): JsonResponse
    {
        $this->ensureTenant($request, $settlement);

        return response()->json([
            'status' => 'success',
            'data' => $settlement->load(['teacher.user', 'separation', 'approvedBy']),
        ]);
    }

    /**
     * Approve a final settlement.
     *
     * @operationId teacherSettlement.approve
     */
    public function approve(Request $request, TeacherSettlement $settlement): JsonResponse
    {
        $this->ensureTenant($request, $settlement);

        if ($settlement->status !== TeacherSettlement::STATUS_DRAFT) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only draft settlements can be approved.',
            ], 422);
        }

        $settlement->update([
            'status' => TeacherSettlement::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'receipt_no' => $settlement->receipt_no ?: $this->separation->generateReference('SET'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Final settlement approved successfully.',
            'data' => $settlement->fresh()->load(['teacher.user', 'approvedBy']),
        ]);
    }

    /**
     * Record payment of an approved settlement.
     *
     * @operationId teacherSettlement.pay
     */
    public function pay(Request $request, TeacherSettlement $settlement): JsonResponse
    {
        $this->ensureTenant($request, $settlement);

        if ($settlement->status !== TeacherSettlement::STATUS_APPROVED) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only approved settlements can be paid.',
            ], 422);
        }

        $validated = $request->validate([
            'payment_method' => ['required', Rule::in(['cash', 'bank_transfer', 'bkash', 'nagad', 'cheque'])],
        ]);

        $settlement->update([
            'status' => TeacherSettlement::STATUS_PAID,
            'payment_method' => $validated['payment_method'],
            'paid_at' => now(),
            'receipt_no' => $settlement->receipt_no ?: $this->separation->generateReference('SET'),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Final settlement marked as paid.',
            'data' => $settlement->fresh()->load(['teacher.user']),
        ]);
    }

    /**
     * Structured receipt data for a settlement.
     *
     * @operationId teacherSettlement.receipt
     */
    public function receipt(Request $request, TeacherSettlement $settlement): JsonResponse
    {
        $this->ensureTenant($request, $settlement);

        $settlement->load('teacher.user');

        return response()->json([
            'status' => 'success',
            'data' => [
                'receipt_no' => $settlement->receipt_no,
                'teacher_name' => $settlement->teacher?->user?->name_en,
                'employee_id' => $settlement->teacher?->employee_id,
                'last_working_day' => $settlement->last_working_day?->toDateString(),
                'components' => $settlement->components,
                'gross_amount' => (float) $settlement->gross_amount,
                'deductions' => $settlement->deductions,
                'total_deductions' => (float) $settlement->total_deductions,
                'net_amount' => (float) $settlement->net_amount,
                'status' => $settlement->status,
                'payment_method' => $settlement->payment_method,
                'paid_at' => $settlement->paid_at?->toDateString(),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function calculate(Teacher $teacher, Request $request): array
    {
        $options = $this->settlementOptions($request);

        $result = $this->separation->calculateSettlement($teacher, $request->last_working_day, $options);

        if ($request->filled('separation_id') && ! array_key_exists('notice_penalty', $options)) {
            $separation = TeacherSeparation::where('tenant_id', $teacher->tenant_id)
                ->where('id', $request->separation_id)
                ->first();

            if ($separation) {
                $penalty = $this->separation->noticeAmount($separation, $result['per_day_salary']);

                if ($penalty > 0) {
                    $options['notice_penalty'] = $penalty;
                    $result = $this->separation->calculateSettlement($teacher, $request->last_working_day, $options);
                }
            }
        }

        return $result;
    }

    private function resolveTeacher(Request $request): Teacher
    {
        $validated = $request->validate([
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'last_working_day' => ['required', 'date'],
            'separation_id' => ['nullable', 'uuid', 'exists:teacher_separations,id'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        return Teacher::where('tenant_id', $request->user()->tenant->id)
            ->where('id', $validated['teacher_id'])
            ->firstOrFail();
    }

    /**
     * @return array<string, float>
     */
    private function settlementOptions(Request $request): array
    {
        $validated = $request->validate([
            'unpaid_salary' => ['nullable', 'numeric', 'min:0'],
            'festival_bonus' => ['nullable', 'numeric', 'min:0'],
            'provident_fund' => ['nullable', 'numeric', 'min:0'],
            'gratuity' => ['nullable', 'numeric', 'min:0'],
            'notice_pay' => ['nullable', 'numeric', 'min:0'],
            'loan' => ['nullable', 'numeric', 'min:0'],
            'advance' => ['nullable', 'numeric', 'min:0'],
            'notice_penalty' => ['nullable', 'numeric', 'min:0'],
            'other_deductions' => ['nullable', 'numeric', 'min:0'],
        ]);

        return array_filter($validated, fn ($value) => $value !== null);
    }

    private function ensureTenant(Request $request, TeacherSettlement $settlement): void
    {
        if ($settlement->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }
}
