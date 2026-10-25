<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TrainingSession;
use App\Models\TrainingParticipant;
use App\Models\TrainingCertificate;
use App\Models\Teacher;
use App\Models\Employee;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

#[Group('Employee Management > Training', description: 'Manage staff training sessions, enrollment, and certification.', weight: 68)]
class TrainingController extends Controller
{
    /**
     * List all training sessions.
     *
     * @operationId training.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TrainingSession::where('tenant_id', $tenant->id);

        if ($request->filled('type')) {
            $query->where('training_type', $request->type);
        }

        if ($request->filled('is_mandatory')) {
            $query->where('is_mandatory', $request->is_mandatory === 'true');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('scheduled_at', 'asc')->get(),
        ]);
    }

    /**
     * Create a new training session.
     *
     * @operationId training.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'training_type' => ['required', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'trainer_name' => ['nullable', 'string', 'max:200'],
            'trainer_type' => ['nullable', Rule::in(['internal', 'external'])],
            'duration_minutes' => ['nullable', 'integer', 'min:15'],
            'scheduled_at' => ['required', 'date'],
            'location' => ['nullable', 'string', 'max:255'],
            'meeting_link' => ['nullable', 'string'],
            'max_participants' => ['nullable', 'integer', 'min:1'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'is_mandatory' => ['nullable', 'boolean'],
        ]);

        $session = TrainingSession::create(array_merge($validated, [
            'tenant_id' => $tenant->id,
            'created_by' => $request->user()->id,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Training session created successfully.',
            'data' => $session,
        ], 201);
    }

    /**
     * Enroll staff in a training session.
     *
     * @operationId training.enroll
     */
    public function enroll(Request $request, TrainingSession $session): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($session->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'staff_id' => ['required', 'uuid'],
            'staff_type' => ['required', Rule::in([Teacher::class, Employee::class])],
        ]);

        // Verify staff exists in tenant
        $staffModel = $validated['staff_type'];
        $staff = $staffModel::where('tenant_id', $tenant->id)->where('id', $validated['staff_id'])->firstOrFail();

        if ($session->max_participants && $session->participants()->count() >= $session->max_participants) {
            return response()->json([
                'status' => 'error',
                'message' => 'Training session is full.',
            ], 422);
        }

        $participant = TrainingParticipant::firstOrCreate([
            'tenant_id' => $tenant->id,
            'training_session_id' => $session->id,
            'staff_id' => $staff->id,
            'staff_type' => $staffModel,
        ], [
            'status' => TrainingParticipant::STATUS_ENROLLED,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Staff enrolled successfully.',
            'data' => $participant,
        ], 201);
    }

    /**
     * Mark a participant as attended or completed.
     *
     * @operationId training.markAttendance
     */
    public function markAttendance(Request $request, TrainingParticipant $participant): JsonResponse
    {
        $tenant = $request->user()->tenant;
        if ($participant->tenant_id !== $tenant->id) abort(404);

        $validated = $request->validate([
            'status' => ['required', Rule::in([TrainingParticipant::STATUS_ATTENDED, TrainingParticipant::STATUS_ABSENT, TrainingParticipant::STATUS_COMPLETED])],
            'attended_at' => ['nullable', 'date'],
            'score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'feedback' => ['nullable', 'string'],
        ]);

        $participant->update([
            'status' => $validated['status'],
            'attended_at' => $validated['attended_at'] ?? null,
            'score' => $validated['score'] ?? null,
            'feedback' => $validated['feedback'] ?? null,
        ]);

        // Auto-issue certificate if completed
        if ($validated['status'] === TrainingParticipant::STATUS_COMPLETED) {
            $this->issueCertificate($participant);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Attendance and feedback updated.',
            'data' => $participant->fresh(),
        ]);
    }

    /**
     * Issue a training certificate.
     *
     * @operationId training.issueCertificate
     */
    public function issueCertificate(TrainingParticipant $participant): void
    {
        $session = $participant->trainingSession;
        
        TrainingCertificate::create([
            'tenant_id' => $participant->tenant_id,
            'training_session_id' => $session->id,
            'staff_id' => $participant->staff_id,
            'staff_type' => $participant->staff_type,
            'certificate_number' => 'CERT-'.now()->format('Y').'-'.strtoupper(Str::random(8)),
            'issue_date' => now()->toDateString(),
            'created_by' => null, // Admin usually
        ]);
    }

    /**
     * List certificates for a staff member.
     *
     * @operationId training.staffCertificates
     */
    public function staffCertificates(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'staff_id' => ['required', 'uuid'],
            'staff_type' => ['required', 'string'],
        ]);

        $certificates = TrainingCertificate::where('tenant_id', $tenant->id)
            ->where('staff_id', $validated['staff_id'])
            ->where('staff_type', $validated['staff_type'])
            ->with('trainingSession')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $certificates,
        ]);
    }

    /**
     * Reporting: Training analytics.
     *
     * @operationId training.analytics
     */
    public function analytics(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $stats = [
            'total_sessions' => TrainingSession::where('tenant_id', $tenant->id)->count(),
            'total_participants' => TrainingParticipant::where('tenant_id', $tenant->id)->count(),
            'completion_rate' => TrainingParticipant::where('tenant_id', $tenant->id)
                ->where('status', TrainingParticipant::STATUS_COMPLETED)
                ->count() / max(1, TrainingParticipant::where('tenant_id', $tenant->id)->count()) * 100,
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
        ]);
    }
}
