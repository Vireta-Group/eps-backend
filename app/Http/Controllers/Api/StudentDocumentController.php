<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentDocument;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Student Management', description: 'Student profiles, guardians, attendance, health, behaviour, dropout, transfer certificates, and reports.', weight: 50)]
class StudentDocumentController extends Controller
{
    /**
     * List documents for a student.
     *
     * @operationId student.documents
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $documents = $student->documents()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $documents,
        ]);
    }

    /**
     * Upload a document for a student.
     *
     * **Required:** document_type, document_name, file_url
     * **Optional:** issue_date, expiry_date, remarks
     *
     * @operationId student.uploadDocument
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $validated = $request->validate([
            'document_type' => ['required', Rule::in([
                'birth_certificate', 'transfer_certificate', 'previous_marksheet',
                'nid', 'passport', 'medical_certificate', 'disability_certificate',
                'photo', 'custom',
            ])],
            'document_name' => ['required', 'string', 'max:200'],
            'file_url' => ['required', 'url', 'max:500'],
            'issue_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'remarks' => ['nullable', 'string'],
        ]);

        $document = $student->documents()->create([
            'tenant_id' => $request->user()->tenant->id,
            'document_type' => $validated['document_type'],
            'document_name' => $validated['document_name'],
            'file_url' => $validated['file_url'],
            'issue_date' => $validated['issue_date'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document uploaded successfully.',
            'data' => $document->fresh(),
        ], 201);
    }

    /**
     * Delete a student document.
     *
     * @operationId student.deleteDocument
     */
    public function delete(Request $request, Student $student, StudentDocument $document): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        if ($document->student_id !== $student->id) {
            abort(404);
        }

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $document->update([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $validated['delete_reason'] ?? null,
        ]);

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Document deleted successfully.',
        ]);
    }

    private function ensureTenantOwnership(Request $request, Student $student): void
    {
        if ($student->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }
}
