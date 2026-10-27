<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TcVerifyLog;
use App\Models\TransferCertificate;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

#[Group('Student Management', description: 'Student profiles, guardians, attendance, health, behaviour, dropout, transfer certificates, and reports.', weight: 50)]
class TransferCertificateController extends Controller
{
    /**
     * List all TCs for the tenant.
     *
     * Filter by student_id, status, is_received, is_duplicate.
     *
     * @operationId tc.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = TransferCertificate::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('is_received')) {
            $query->where('is_received', filter_var($request->is_received, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('is_duplicate')) {
            $query->where('is_duplicate', filter_var($request->is_duplicate, FILTER_VALIDATE_BOOLEAN));
        }

        $tcs = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $tcs,
        ]);
    }

    /**
     * Issue a TC for a student.
     *
     * Sets the student to inactive after issuing.
     *
     * @operationId tc.issue
     */
    public function issue(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'leaving_reason_category' => ['required', 'string', 'max:50'],
            'leaving_reason' => ['nullable', 'string'],
            'conduct' => ['nullable', 'string', 'max:20'],
            'last_attendance_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $student = Student::where('tenant_id', $tenant->id)
            ->where('id', $validated['student_id'])
            ->firstOrFail();

        if (! $student->is_active) {
            abort(422, 'Student is already inactive.');
        }

        $tc = DB::transaction(function () use ($tenant, $validated, $student, $request) {
            $tcNumber = 'TC-'.date('Y').'-'.str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

            $tc = TransferCertificate::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'tc_number' => $tcNumber,
                'issue_date' => now()->toDateString(),
                'conduct' => $validated['conduct'] ?? null,
                'leaving_reason' => $validated['leaving_reason'] ?? null,
                'leaving_reason_category' => $validated['leaving_reason_category'],
                'last_attendance_date' => $validated['last_attendance_date'] ?? null,
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $student->update([
                'is_active' => false,
                'updated_by' => $request->user()->id,
            ]);

            return $tc;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'TC issued successfully.',
            'data' => $tc,
        ], 201);
    }

    /**
     * Show a single TC.
     *
     * @operationId tc.show
     */
    public function show(Request $request, TransferCertificate $transferCertificate): JsonResponse
    {
        if ($transferCertificate->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $transferCertificate->load(['student', 'originalTc']);

        return response()->json([
            'status' => 'success',
            'data' => $transferCertificate,
        ]);
    }

    /**
     * Issue a duplicate TC.
     *
     * @operationId tc.issueDuplicate
     */
    public function issueDuplicate(Request $request, TransferCertificate $transferCertificate): JsonResponse
    {
        if ($transferCertificate->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $tenant = $request->user()->tenant;

        $duplicate = DB::transaction(function () use ($tenant, $transferCertificate, $request) {
            $tcNumber = 'TC-'.date('Y').'-'.str_pad((string) mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

            return TransferCertificate::create([
                'tenant_id' => $tenant->id,
                'student_id' => $transferCertificate->student_id,
                'tc_number' => $tcNumber,
                'issue_date' => now()->toDateString(),
                'leaving_reason_category' => $transferCertificate->leaving_reason_category,
                'leaving_reason' => $transferCertificate->leaving_reason,
                'conduct' => $transferCertificate->conduct,
                'is_duplicate' => true,
                'original_tc_id' => $transferCertificate->id,
                'created_by' => $request->user()->id,
            ]);
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Duplicate TC issued successfully.',
            'data' => $duplicate,
        ], 201);
    }

    /**
     * Record receiving a TC from another school.
     *
     * @operationId tc.receive
     */
    public function receive(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'tc_number' => ['required', 'string', 'max:50'],
            'previous_school_name' => ['required', 'string', 'max:200'],
            'previous_school_address' => ['nullable', 'string'],
            'issue_date' => ['required', 'date'],
            'scan_url' => ['nullable', 'url', 'max:500'],
            'remarks' => ['nullable', 'string'],
        ]);

        $student = Student::where('tenant_id', $tenant->id)
            ->where('id', $validated['student_id'])
            ->firstOrFail();

        $tc = TransferCertificate::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'tc_number' => $validated['tc_number'],
            'issue_date' => $validated['issue_date'],
            'previous_school_name' => $validated['previous_school_name'],
            'previous_school_address' => $validated['previous_school_address'] ?? null,
            'scan_url' => $validated['scan_url'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'is_received' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'TC received successfully.',
            'data' => $tc,
        ], 201);
    }

    /**
     * Verify a TC (QR scan).
     *
     * Creates a verification log entry.
     *
     * @operationId tc.verify
     */
    public function verify(Request $request, TransferCertificate $transferCertificate): JsonResponse
    {
        $tenant = $request->user()->tenant;

        if ($transferCertificate->tenant_id !== $tenant->id) {
            abort(404);
        }

        $isValid = $transferCertificate->status === 'active';

        TcVerifyLog::create([
            'tenant_id' => $tenant->id,
            'transfer_certificate_id' => $transferCertificate->id,
            'tc_number' => $transferCertificate->tc_number,
            'verified_by_ip' => $request->ip(),
            'verified_by_user_id' => $request->user()->id,
            'verified_at' => now(),
            'is_valid' => $isValid,
            'verification_data' => [
                'tc_number' => $transferCertificate->tc_number,
                'student_name' => $transferCertificate->student?->name_en,
                'issue_date' => $transferCertificate->issue_date?->toDateString(),
                'status' => $transferCertificate->status,
                'is_duplicate' => $transferCertificate->is_duplicate,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_valid' => $isValid,
                'tc_number' => $transferCertificate->tc_number,
                'issue_date' => $transferCertificate->issue_date?->toDateString(),
                'status' => $transferCertificate->status,
                'is_duplicate' => $transferCertificate->is_duplicate,
            ],
        ]);
    }

    /**
     * Verify a TC by number (public endpoint compatible).
     *
     * @operationId tc.verifyByNumber
     */
    public function verifyByNumber(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tc_number' => ['required', 'string', 'max:50'],
        ]);

        $tc = TransferCertificate::where('tc_number', $validated['tc_number'])->first();

        if (! $tc) {
            return response()->json([
                'status' => 'success',
                'data' => [
                    'is_valid' => false,
                    'message' => 'No TC found with this number.',
                ],
            ]);
        }

        $isValid = $tc->status === 'active';

        TcVerifyLog::create([
            'tenant_id' => $tc->tenant_id,
            'transfer_certificate_id' => $tc->id,
            'tc_number' => $tc->tc_number,
            'verified_by_ip' => $request->ip(),
            'verified_by_user_id' => $request->user()?->id,
            'verified_at' => now(),
            'is_valid' => $isValid,
            'verification_data' => [
                'tc_number' => $tc->tc_number,
                'issue_date' => $tc->issue_date?->toDateString(),
                'status' => $tc->status,
                'is_duplicate' => $tc->is_duplicate,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'is_valid' => $isValid,
                'tc_number' => $tc->tc_number,
                'issue_date' => $tc->issue_date?->toDateString(),
                'status' => $tc->status,
                'is_duplicate' => $tc->is_duplicate,
            ],
        ]);
    }

    /**
     * Cancel a TC and reactivate the student.
     *
     * @operationId tc.cancel
     */
    public function cancel(Request $request, TransferCertificate $transferCertificate): JsonResponse
    {
        if ($transferCertificate->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $validated = $request->validate([
            'cancel_reason' => ['required', 'string', 'max:500'],
        ]);

        DB::transaction(function () use ($transferCertificate, $validated, $request) {
            $transferCertificate->update([
                'status' => 'cancelled',
                'cancelled_at' => now(),
                'cancel_reason' => $validated['cancel_reason'],
            ]);

            $student = $transferCertificate->student;

            if ($student && ! $student->is_active) {
                $student->update([
                    'is_active' => true,
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'TC cancelled and student reactivated successfully.',
            'data' => $transferCertificate->fresh(),
        ]);
    }
}
