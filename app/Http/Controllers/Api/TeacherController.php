<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ClassTeacherAssignment;
use App\Models\Subject as SubjectModel;
use App\Models\SubjectTeacherAssignment;
use App\Models\Teacher;
use App\Models\TeacherDocument;
use App\Models\TeacherExperience;
use App\Models\TeacherQualification;
use App\Models\TeacherTraining;
use App\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Teacher Management > Profile', description: 'Manage teacher profiles, qualifications, trainings, experiences, and documents.', weight: 61)]
class TeacherController extends Controller
{
    /**
     * List all teachers for the tenant.
     *
     * @operationId teacher.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $teachers = Teacher::where('tenant_id', $tenant->id)
            ->with(['user', 'qualifications', 'highestQualification'])
            ->orderBy('employee_id')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $teachers,
        ]);
    }

    /**
     * Create a new teacher profile.
     *
     * Creates both the User account and Teacher profile.
     *
     * @operationId teacher.store
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
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::in(['full_time', 'part_time', 'contractual', 'visiting', 'mpo', 'non_mpo'])],
            'mpo_status' => ['nullable', 'boolean'],
            'mpo_index' => ['nullable', 'string', 'max:50'],
            'probation_end_date' => ['nullable', 'date'],
            'salary_grade' => ['nullable', 'string', 'max:50'],
            'bank_info' => ['nullable', 'array'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
        ]);

        $user = DB::transaction(function () use ($tenant, $validated, $request) {
            $user = User::create([
                'tenant_id' => $tenant->id,
                'user_type' => 'teacher',
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

            $employeeId = 'TCH-'.date('Y').'-'.str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT);

            $teacher = Teacher::create([
                'tenant_id' => $tenant->id,
                'user_id' => $user->id,
                'employee_id' => $employeeId,
                'designation' => $validated['designation'],
                'department' => $validated['department'] ?? null,
                'employment_type' => $validated['employment_type'],
                'mpo_status' => $validated['mpo_status'] ?? false,
                'mpo_index' => $validated['mpo_index'] ?? null,
                'probation_end_date' => $validated['probation_end_date'] ?? null,
                'salary_grade' => $validated['salary_grade'] ?? null,
                'bank_info' => $validated['bank_info'] ?? null,
                'tin_number' => $validated['tin_number'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
                'emergency_contact_relation' => $validated['emergency_contact_relation'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            return $teacher->load('user');
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher created successfully.',
            'data' => $user,
        ], 201);
    }

    /**
     * Show a single teacher profile.
     *
     * @operationId teacher.show
     */
    public function show(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $teacher->load(['user', 'qualifications', 'trainings', 'experiences', 'documents']);

        return response()->json([
            'status' => 'success',
            'data' => $teacher,
        ]);
    }

    /**
     * Update a teacher profile.
     *
     * @operationId teacher.update
     */
    public function update(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $validated = $request->validate([
            'designation' => ['sometimes', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['sometimes', Rule::in(['full_time', 'part_time', 'contractual', 'visiting', 'mpo', 'non_mpo'])],
            'mpo_status' => ['nullable', 'boolean'],
            'mpo_index' => ['nullable', 'string', 'max:50'],
            'probation_end_date' => ['nullable', 'date'],
            'confirmation_date' => ['nullable', 'date'],
            'salary_grade' => ['nullable', 'string', 'max:50'],
            'bank_info' => ['nullable', 'array'],
            'tin_number' => ['nullable', 'string', 'max:50'],
            'emergency_contact_name' => ['nullable', 'string', 'max:100'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:20'],
            'emergency_contact_relation' => ['nullable', 'string', 'max:50'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $teacher->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher updated successfully.',
            'data' => $teacher->fresh()->load('user'),
        ]);
    }

    /**
     * Activate a teacher.
     *
     * @operationId teacher.activate
     */
    public function activate(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $teacher->update(['is_active' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher activated successfully.',
        ]);
    }

    /**
     * Inactivate a teacher.
     *
     * @operationId teacher.inactivate
     */
    public function inactivate(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $teacher->update(['is_active' => false]);

        return response()->json([
            'status' => 'success',
            'message' => 'Teacher inactivated successfully.',
        ]);
    }

    // ── Qualifications ───────────────────────────────────────────────────

    /**
     * List qualifications for a teacher.
     *
     * @operationId teacher.qualifications
     */
    public function qualifications(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        return response()->json([
            'status' => 'success',
            'data' => $teacher->qualifications()->orderByDesc('passing_year')->get(),
        ]);
    }

    /**
     * Add a qualification.
     *
     * @operationId teacher.addQualification
     */
    public function addQualification(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $validated = $request->validate([
            'degree' => ['required', 'string', 'max:100'],
            'subject' => ['nullable', 'string', 'max:200'],
            'institution' => ['nullable', 'string', 'max:200'],
            'board' => ['nullable', 'string', 'max:100'],
            'result' => ['nullable', 'string', 'max:50'],
            'passing_year' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'certificate_url' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:20'],
            'is_highest' => ['nullable', 'boolean'],
        ]);

        if (($validated['is_highest'] ?? false)) {
            $teacher->qualifications()->update(['is_highest' => false]);
        }

        $qualification = $teacher->qualifications()->create([
            'tenant_id' => $teacher->tenant_id,
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Qualification added successfully.',
            'data' => $qualification,
        ], 201);
    }

    /**
     * Delete a qualification.
     *
     * @operationId teacher.deleteQualification
     */
    public function deleteQualification(Request $request, Teacher $teacher, TeacherQualification $qualification): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $qualification->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Qualification deleted successfully.',
        ]);
    }

    // ── Trainings ─────────────────────────────────────────────────────────

    /**
     * List trainings for a teacher.
     *
     * @operationId teacher.trainings
     */
    public function trainings(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        return response()->json([
            'status' => 'success',
            'data' => $teacher->trainings()->orderByDesc('completion_date')->get(),
        ]);
    }

    /**
     * Add a training record.
     *
     * @operationId teacher.addTraining
     */
    public function addTraining(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $validated = $request->validate([
            'training_name' => ['required', 'string', 'max:200'],
            'provider' => ['nullable', 'string', 'max:200'],
            'duration' => ['nullable', 'string', 'max:100'],
            'completion_date' => ['nullable', 'date'],
            'expiry_date' => ['nullable', 'date'],
            'certificate_url' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
        ]);

        $training = $teacher->trainings()->create([
            'tenant_id' => $teacher->tenant_id,
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Training added successfully.',
            'data' => $training,
        ], 201);
    }

    /**
     * Delete a training record.
     *
     * @operationId teacher.deleteTraining
     */
    public function deleteTraining(Request $request, Teacher $teacher, TeacherTraining $training): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $training->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Training deleted successfully.',
        ]);
    }

    // ── Experiences ───────────────────────────────────────────────────────

    /**
     * List experiences for a teacher.
     *
     * @operationId teacher.experiences
     */
    public function experiences(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        return response()->json([
            'status' => 'success',
            'data' => $teacher->experiences()->orderByDesc('from_date')->get(),
        ]);
    }

    /**
     * Add an experience record.
     *
     * @operationId teacher.addExperience
     */
    public function addExperience(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $validated = $request->validate([
            'organization' => ['required', 'string', 'max:200'],
            'designation' => ['nullable', 'string', 'max:100'],
            'from_date' => ['nullable', 'date'],
            'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'is_current' => ['nullable', 'boolean'],
            'responsibilities' => ['nullable', 'string'],
            'certificate_url' => ['nullable', 'string'],
        ]);

        if (($validated['is_current'] ?? false)) {
            $teacher->experiences()->where('is_current', true)->update(['is_current' => false]);
        }

        $experience = $teacher->experiences()->create([
            'tenant_id' => $teacher->tenant_id,
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Experience added successfully.',
            'data' => $experience,
        ], 201);
    }

    /**
     * Delete an experience record.
     *
     * @operationId teacher.deleteExperience
     */
    public function deleteExperience(Request $request, Teacher $teacher, TeacherExperience $experience): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $experience->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Experience deleted successfully.',
        ]);
    }

    // ── Documents ─────────────────────────────────────────────────────────

    /**
     * List documents for a teacher.
     *
     * @operationId teacher.documents
     */
    public function documents(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        return response()->json([
            'status' => 'success',
            'data' => $teacher->documents()->orderByDesc('created_at')->get(),
        ]);
    }

    /**
     * Upload a document.
     *
     * @operationId teacher.addDocument
     */
    public function addDocument(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:50'],
            'document_name' => ['nullable', 'string', 'max:200'],
            'file_url' => ['required', 'string'],
            'expiry_date' => ['nullable', 'date'],
            'verification_status' => ['nullable', Rule::in(['pending', 'verified', 'rejected'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $document = $teacher->documents()->create([
            'tenant_id' => $teacher->tenant_id,
            ...$validated,
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
     * @operationId teacher.verifyDocument
     */
    public function verifyDocument(Request $request, Teacher $teacher, TeacherDocument $document): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $validated = $request->validate([
            'verification_status' => ['required', Rule::in(['verified', 'rejected'])],
            'remarks' => ['nullable', 'string'],
        ]);

        $document->update($validated);

        return response()->json([
            'status' => 'success',
            'message' => 'Document verification status updated.',
            'data' => $document->fresh(),
        ]);
    }

    /**
     * Delete a document.
     *
     * @operationId teacher.deleteDocument
     */
    public function deleteDocument(Request $request, Teacher $teacher, TeacherDocument $document): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $document->update([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $request->input('delete_reason'),
        ]);

        $document->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Document deleted successfully.',
        ]);
    }

    // ── Subject Assignments ───────────────────────────────────────────────

    /**
     * Assign a subject teacher.
     *
     * @operationId teacher.assignSubject
     */
    public function assignSubject(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'school_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'school_section_id' => ['required', 'uuid', 'exists:school_sections,id'],
            'subject_id' => ['required', 'uuid', 'exists:subjects,id'],
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $teacher = Teacher::where('tenant_id', $tenant->id)->findOrFail($validated['teacher_id']);

        $existing = SubjectTeacherAssignment::where([
            'academic_year_id' => $validated['academic_year_id'],
            'school_section_id' => $validated['school_section_id'],
            'subject_id' => $validated['subject_id'],
        ])->where('is_active', true)->first();

        if ($existing) {
            $existing->update(['is_active' => false, 'end_date' => now()]);
        }

        $assignment = SubjectTeacherAssignment::create([
            'tenant_id' => $tenant->id,
            ...$validated,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subject teacher assigned successfully.',
            'data' => $assignment->load(['teacher.user', 'subject', 'schoolSection']),
        ], 201);
    }

    /**
     * List subject assignments for a teacher.
     *
     * @operationId teacher.subjectAssignments
     */
    public function subjectAssignments(Request $request, Teacher $teacher): JsonResponse
    {
        $this->ensureTenantOwnership($request, $teacher);

        $assignments = $teacher->subjectAssignments()
            ->with(['subject', 'schoolSection.schoolClass', 'academicYear'])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $assignments,
        ]);
    }

    /**
     * Remove a subject assignment.
     *
     * @operationId teacher.removeSubjectAssignment
     */
    public function removeSubjectAssignment(Request $request, SubjectTeacherAssignment $assignment): JsonResponse
    {
        $assignment->update(['is_active' => false, 'end_date' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Subject assignment removed.',
        ]);
    }

    // ── Class Teacher Assignments ─────────────────────────────────────────

    /**
     * Assign a class teacher to a section.
     *
     * @operationId teacher.assignClassTeacher
     */
    public function assignClassTeacher(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'school_section_id' => ['required', 'uuid', 'exists:school_sections,id'],
            'teacher_id' => ['required', 'uuid', 'exists:teachers,id'],
            'effective_date' => ['nullable', 'date'],
        ]);

        $teacher = Teacher::where('tenant_id', $tenant->id)->findOrFail($validated['teacher_id']);

        $existing = ClassTeacherAssignment::where([
            'academic_year_id' => $validated['academic_year_id'],
            'school_section_id' => $validated['school_section_id'],
        ])->where('is_active', true)->first();

        if ($existing) {
            $existing->update(['is_active' => false, 'end_date' => now()]);
        }

        $assignment = ClassTeacherAssignment::create([
            'tenant_id' => $tenant->id,
            ...$validated,
            'is_active' => true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Class teacher assigned successfully.',
            'data' => $assignment->load(['teacher.user', 'schoolSection.schoolClass']),
        ], 201);
    }

    /**
     * List class teacher assignments.
     *
     * @operationId teacher.classTeacherAssignments
     */
    public function classTeacherAssignments(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $assignments = ClassTeacherAssignment::where('tenant_id', $tenant->id)
            ->with(['teacher.user', 'schoolSection.schoolClass', 'academicYear'])
            ->where('is_active', true)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $assignments,
        ]);
    }

    /**
     * Remove a class teacher assignment.
     *
     * @operationId teacher.removeClassTeacherAssignment
     */
    public function removeClassTeacherAssignment(Request $request, ClassTeacherAssignment $assignment): JsonResponse
    {
        $assignment->update(['is_active' => false, 'end_date' => now()]);

        return response()->json([
            'status' => 'success',
            'message' => 'Class teacher assignment removed.',
        ]);
    }

    // ── Workload ──────────────────────────────────────────────────────────

    /**
     * Teacher workload overview.
     *
     * Returns subject count, class count, total assignments per teacher.
     *
     * @operationId teacher.workload
     */
    public function workload(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $teachers = Teacher::where('tenant_id', $tenant->id)
            ->withCount(['subjectAssignments as subject_count' => function ($q) {
                $q->where('is_active', true);
            }])
            ->withCount(['classTeacherAssignments as class_teacher_count' => function ($q) {
                $q->where('is_active', true);
            }])
            ->get(['id', 'employee_id', 'designation', 'department']);

        $workload = $teachers->map(function ($teacher) {
            return [
                'id' => $teacher->id,
                'employee_id' => $teacher->employee_id,
                'designation' => $teacher->designation,
                'department' => $teacher->department,
                'subjects' => $teacher->subject_count,
                'class_teacher_sections' => $teacher->class_teacher_count,
                'total_assignments' => $teacher->subject_count + $teacher->class_teacher_count,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $workload,
        ]);
    }

    private function ensureTenantOwnership(Request $request, Teacher $teacher): void
    {
        if ($teacher->tenant_id !== $request->user()->tenant->id) {
            abort(404, 'Teacher not found.');
        }
    }
}
