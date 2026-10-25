<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeDocument;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Employee Management > Profile', description: 'Manage non-teaching staff profiles, departments, and documents.', weight: 61)]
class EmployeeController extends Controller
{
    /**
     * List all employees for the tenant.
     *
     * @operationId employee.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = Employee::where('tenant_id', $tenant->id)
            ->with(['user', 'department']);

        if ($request->filled('department_id')) {
            $query->where('department_id', $request->department_id);
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('employee_id')->get(),
        ]);
    }

    /**
     * Create a new employee profile.
     *
     * @operationId employee.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:200'],
            'name_bn' => ['nullable', 'string', 'max:200'],
            'email' => ['required', 'email', 'unique:users,email'],
            'phone' => ['required', 'string', 'max:20', 'unique:users,phone'],
            'designation' => ['required', 'string', 'max:100'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'employment_type' => ['required', Rule::in(['permanent', 'contractual', 'part_time', 'daily_basis', 'outsourced', 'intern'])],
            'joining_date' => ['required', 'date'],
            'bank_info' => ['nullable', 'array'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
        ]);

        $user = DB::transaction(function () use ($tenant, $validated, $request) {
            $user = \App\Models\User::create([
                'tenant_id' => $tenant->id,
                'user_type' => 'employee',
                'name_en' => $validated['name_en'],
                'name_bn' => $validated['name_bn'] ?? null,
                'email' => $validated['email'],
                'phone' => $validated['phone'],
                'username' => $validated['email'],
                'password_hash' => bcrypt('password'),
                'force_password_change' => true,
                'user_status' => 'active',
                'status' => 'active',
                'created_by' => $request->user()->id,
            ]);

            $employeeId = 'EMP-'.date('Y').'-'.str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

            $employee = Employee::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'designation' => $validated['designation'],
                'department_id' => $validated['department_id'] ?? null,
                'employment_type' => $validated['employment_type'],
                'joining_date' => $validated['joining_date'],
                'bank_info' => $validated['bank_info'] ?? null,
                'tin_number' => $validated['tin_number'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relation' => $validated['emergency_contact_relation'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return $employee;
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Employee created successfully.',
            'data' => $user->fresh()->load(['user', 'department']),
        ], 201);
    }

    /**
     * Show an employee profile with documents.
     *
     * @operationId employee.show
     */
    public function show(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureTenant($request, $employee);

        return response()->json([
            'status' => 'success',
            'data' => $employee->load(['user', 'department', 'documents']),
        ]);
    }

    /**
     * Update an employee profile.
     *
     * @operationId employee.update
     */
    public function update(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureTenant($request, $employee);

        $validated = $request->validate([
            'designation' => ['sometimes', 'required', 'string', 'max:100'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'employment_type' => ['sometimes', 'required', Rule::in(['permanent', 'contractual', 'part_time', 'daily_basis', 'outsourced', 'intern'])],
            'joining_date' => ['nullable', 'date'],
            'bank_info' => ['nullable', 'array'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $employee->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Employee updated successfully.',
            'data' => $employee->fresh()->load(['user', 'department']),
        ]);
    }

    /**
     * Soft delete an employee.
     *
     * @operationId employee.delete
     */
    public function delete(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureTenant($request, $employee);

        $employee->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Employee deleted successfully.',
        ]);
    }

    /**
     * List documents for an employee.
     *
     * @operationId employee.documents
     */
    public function documents(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureTenant($request, $employee);

        return response()->json([
            'status' => 'success',
            'data' => $employee->documents()->orderBy('created_at', 'desc')->get(),
        ]);
    }

    /**
     * Attach a document to an employee.
     *
     * @operationId employee.storeDocument
     */
    public function storeDocument(Request $request, Employee $employee): JsonResponse
    {
        $this->ensureTenant($request, $employee);

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'document_name' => ['nullable', 'string', 'max:200'],
            'file_url' => ['nullable', 'string', 'max:255'],
            'expiry_date' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string', 'max:2000'],
        ]);

        $document = $employee->documents()->create([
            'tenant_id' => $employee->tenant_id,
            'document_type' => $validated['document_type'],
            'document_name' => $validated['document_name'] ?? null,
            'file_url' => $validated['file_url'] ?? null,
            'expiry_date' => $validated['expiry_date'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document added successfully.',
            'data' => $document,
        ], 201);
    }

    /**
     * Verify a document.
     *
     * @operationId employee.verifyDocument
     */
    public function verifyDocument(Request $request, EmployeeDocument $document): JsonResponse
    {
        $this->ensureTenant($request, $document);

        $validated = $request->validate([
            'status' => ['required', Rule::in(['verified', 'rejected'])],
            'remarks' => ['nullable', 'string', 'max:500'],
        ]);

        $document->update([
            'verification_status' => $validated['status'],
            'remarks' => $validated['remarks'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Document verification status updated.',
            'data' => $document->fresh(),
        ]);
    }

    /**
     * Delete a document.
     *
     * @operationId employee.deleteDocument
     */
    public function deleteDocument(Request $request, EmployeeDocument $document): JsonResponse
    {
        $this->ensureTenant($request, $document);

        if ($document->employee_id !== $request->route('employee')) {
            abort(404);
        }

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Document deleted successfully.',
        ]);
    }

    private function ensureTenant(Request $request, $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }
}
