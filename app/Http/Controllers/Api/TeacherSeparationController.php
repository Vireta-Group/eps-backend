<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Teacher;
use App\Models\TeacherSeparation;
use App\Models\TeacherSeparationDocument;
use App\Services\TeacherSeparationService;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Teacher Management > Transfer & Resignation', description: 'Manage teacher resignations, terminations, documents, and final settlements.', weight: 65)]
class TeacherSeparationController extends Controller
{
    public function __construct(private TeacherSeparationService $separation)
    {
    }

    /**
     * List resignations and terminations for the tenant.
     *
     * @operationId teacherSeparation.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TeacherSeparation::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'approvedBy']);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('teacher_id')) {
            $query->where('teacher_id', $request->teacher_id);
        }

        $separations = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $separations,
        ]);
    }

    /**
     * Submit a resignation or termination.
     *
     * @operationId teacherSeparation.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'type' => ['required', Rule::in([TeacherSeparation::TYPE_RESIGNATION, TeacherSeparation::TYPE_TERMINATION])],
            'termination_type' => ['nullable', Rule::in(TeacherSeparation::TERMINATION_TYPES)],
            'resignation_date' => ['nullable', 'date'],
            'last_working_day' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:2000'],
            'notice_period_days' => ['nullable', 'integer', 'min:0', 'max:365'],
            'notice_served' => ['nullable', 'boolean'],
            'notice_waived' => ['nullable', 'boolean'],
            'notice_penalty_amount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $teacherId = $validated['teacher_id'] ?? null;

        if (! $teacherId) {
            $teacher = Teacher::where('tenant_id', $tenant->id)
                ->where('user_id', $request->user()->id)
                ->first();

            if (! $teacher) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'A teacher_id is required.',
                ], 422);
            }

            $teacherId = $teacher->id;
        }

        Teacher::where('tenant_id', $tenant->id)->where('id', $teacherId)->firstOrFail();

        if ($validated['type'] === TeacherSeparation::TYPE_TERMINATION && empty($validated['termination_type'])) {
            return response()->json([
                'status' => 'error',
                'message' => 'A termination_type is required for terminations.',
            ], 422);
        }

        $separation = TeacherSeparation::create([
            'tenant_id' => $tenant->id,
            'teacher_id' => $teacherId,
            'type' => $validated['type'],
            'termination_type' => $validated['termination_type'] ?? null,
            'submitted_by' => $request->user()->id,
            'resignation_date' => $validated['resignation_date'] ?? null,
            'last_working_day' => $validated['last_working_day'],
            'reason' => $validated['reason'] ?? null,
            'notice_period_days' => $validated['notice_period_days'] ?? 0,
            'notice_served' => $validated['notice_served'] ?? false,
            'notice_waived' => $validated['notice_waived'] ?? false,
            'notice_penalty_amount' => $validated['notice_penalty_amount'] ?? 0,
            'status' => TeacherSeparation::STATUS_PENDING,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => ucfirst($validated['type']).' submitted successfully.',
            'data' => $separation->fresh()->load(['teacher.user']),
        ], 201);
    }

    /**
     * Show a resignation or termination with documents.
     *
     * @operationId teacherSeparation.show
     */
    public function show(Request $request, TeacherSeparation $separation): JsonResponse
    {
        $this->ensureTenant($request, $separation);

        return response()->json([
            'status' => 'success',
            'data' => $separation->load(['teacher.user', 'documents', 'approvedBy', 'settlement']),
        ]);
    }

    /**
     * Accept or reject a separation request.
     *
     * @operationId teacherSeparation.review
     */
    public function review(Request $request, TeacherSeparation $separation): JsonResponse
    {
        $this->ensureTenant($request, $separation);

        if ($separation->status !== TeacherSeparation::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending records can be reviewed.',
            ], 422);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([TeacherSeparation::STATUS_APPROVED, TeacherSeparation::STATUS_REJECTED])],
            'review_remarks' => ['nullable', 'string', 'max:500'],
            'rejection_reason' => ['nullable', 'required_if:status,'.TeacherSeparation::STATUS_REJECTED, 'string', 'max:500'],
            'last_working_day' => ['nullable', 'date'],
        ]);

        $isApproved = $validated['status'] === TeacherSeparation::STATUS_APPROVED;

        $separation->update([
            'status' => $validated['status'],
            'review_remarks' => $validated['review_remarks'] ?? null,
            'rejection_reason' => $isApproved ? null : ($validated['rejection_reason'] ?? null),
            'last_working_day' => $validated['last_working_day'] ?? $separation->last_working_day,
            'approved_by' => $request->user()->id,
            'approved_at' => now(),
            'letter_reference' => $isApproved
                ? ($separation->letter_reference ?: $this->separation->generateReference($separation->type === TeacherSeparation::TYPE_RESIGNATION ? 'RES' : 'TER'))
                : $separation->letter_reference,
            'receipt_no' => $isApproved && $separation->type === TeacherSeparation::TYPE_RESIGNATION
                ? ($separation->receipt_no ?: $this->separation->generateReference('RCP'))
                : $separation->receipt_no,
        ]);

        if ($isApproved && $separation->last_working_day->lessThanOrEqualTo(now())) {
            $separation->teacher()->update(['is_active' => false]);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Separation '.$validated['status'].' successfully.',
            'data' => $separation->fresh()->load(['teacher.user', 'approvedBy']),
        ]);
    }

    /**
     * Withdraw a pending separation request.
     *
     * @operationId teacherSeparation.withdraw
     */
    public function withdraw(Request $request, TeacherSeparation $separation): JsonResponse
    {
        $this->ensureTenant($request, $separation);

        if ($separation->status !== TeacherSeparation::STATUS_PENDING) {
            return response()->json([
                'status' => 'error',
                'message' => 'Only pending records can be withdrawn.',
            ], 422);
        }

        $separation->update(['status' => TeacherSeparation::STATUS_WITHDRAWN]);

        return response()->json([
            'status' => 'success',
            'message' => 'Separation withdrawn successfully.',
            'data' => $separation->fresh(),
        ]);
    }

    /**
     * List the documents attached to a separation.
     *
     * @operationId teacherSeparation.documents
     */
    public function documents(Request $request, TeacherSeparation $separation): JsonResponse
    {
        $this->ensureTenant($request, $separation);

        return response()->json([
            'status' => 'success',
            'data' => $separation->documents()->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Attach a document (cause, warning, show cause, decision, letter) to a separation.
     *
     * @operationId teacherSeparation.storeDocument
     */
    public function storeDocument(Request $request, TeacherSeparation $separation): JsonResponse
    {
        $this->ensureTenant($request, $separation);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in(TeacherSeparationDocument::TYPES)],
            'title' => ['nullable', 'string', 'max:200'],
            'file_url' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'document_date' => ['nullable', 'date'],
        ]);

        $document = $separation->documents()->create([
            'tenant_id' => $separation->tenant_id,
            'document_type' => $validated['document_type'],
            'title' => $validated['title'] ?? null,
            'file_url' => $validated['file_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'document_date' => $validated['document_date'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document added successfully.',
            'data' => $document,
        ], 201);
    }

    /**
     * Delete a document from a separation.
     *
     * @operationId teacherSeparation.deleteDocument
     */
    public function deleteDocument(Request $request, TeacherSeparation $separation, TeacherSeparationDocument $document): JsonResponse
    {
        $this->ensureTenant($request, $separation);

        if ($document->separation_id !== $separation->id) {
            abort(404);
        }

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Document deleted successfully.',
        ]);
    }

    /**
     * Structured letter data for a separation.
     *
     * @operationId teacherSeparation.letter
     */
    public function letter(Request $request, TeacherSeparation $separation): JsonResponse
    {
        $this->ensureTenant($request, $separation);

        $separation->load('teacher.user');

        return response()->json([
            'status' => 'success',
            'data' => [
                'reference' => $separation->letter_reference,
                'receipt_no' => $separation->receipt_no,
                'type' => $separation->type,
                'termination_type' => $separation->termination_type,
                'status' => $separation->status,
                'teacher_name' => $separation->teacher?->user?->name_en,
                'employee_id' => $separation->teacher?->employee_id,
                'designation' => $separation->teacher?->designation,
                'resignation_date' => $separation->resignation_date?->toDateString(),
                'last_working_day' => $separation->last_working_day?->toDateString(),
                'notice_period_days' => $separation->notice_period_days,
                'reason' => $separation->reason,
                'issued_on' => $separation->approved_at?->toDateString(),
            ],
        ]);
    }

    private function ensureTenant(Request $request, TeacherSeparation $separation): void
    {
        if ($separation->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }
}
