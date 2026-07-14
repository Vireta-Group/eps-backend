<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\StudentGuardian;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

#[Group('Student Management > Profile', description: 'Manage student profiles, admission, section assignment, and roll numbers.', weight: 51)]
class StudentController extends Controller
{
    /**
     * List all students for the tenant.
     *
     * Optionally filter by `class_id`, `section_id`, `academic_year_id`, `is_active`, `student_type`.
     *
     * @operationId student.index
     */
    public function index(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = Student::where('students.tenant_id', $tenant->id)
            ->join('student_academic_histories', function ($join) {
                $join->on('students.id', '=', 'student_academic_histories.student_id')
                    ->where('student_academic_histories.is_current', true);
            })
            ->select('students.*', 'student_academic_histories.roll_number',
                'student_academic_histories.academic_year_id',
                'student_academic_histories.school_class_id',
                'student_academic_histories.school_section_id');

        if ($request->filled('academic_year_id')) {
            $query->where('student_academic_histories.academic_year_id', $request->academic_year_id);
        }

        if ($request->filled('class_id')) {
            $query->where('student_academic_histories.school_class_id', $request->class_id);
        }

        if ($request->filled('section_id')) {
            $query->where('student_academic_histories.school_section_id', $request->section_id);
        }

        if ($request->filled('is_active')) {
            $query->where('students.is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->filled('student_type')) {
            $query->where('students.student_type', $request->student_type);
        }

        $students = $query->orderBy('students.name_en')->get();

        return response()->json([
            'status' => 'success',
            'data' => $students,
        ]);
    }

    /**
     * List students by class.
     *
     * **Required:** class_id
     *
     * @operationId student.byClass
     */
    public function byClass(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'class_id' => ['required', 'uuid', 'exists:school_classes,id'],
        ]);

        $class = SchoolClass::where('tenant_id', $tenant->id)
            ->where('id', $validated['class_id'])
            ->firstOrFail();

        $students = Student::where('students.tenant_id', $tenant->id)
            ->join('student_academic_histories', function ($join) {
                $join->on('students.id', '=', 'student_academic_histories.student_id')
                    ->where('student_academic_histories.is_current', true);
            })
            ->where('student_academic_histories.school_class_id', $class->id)
            ->select('students.*', 'student_academic_histories.roll_number',
                'student_academic_histories.school_section_id')
            ->orderBy('student_academic_histories.roll_number')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $students,
        ]);
    }

    /**
     * List students by section.
     *
     * **Required:** section_id
     *
     * @operationId student.bySection
     */
    public function bySection(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'section_id' => ['required', 'uuid', 'exists:school_sections,id'],
        ]);

        $section = SchoolSection::where('tenant_id', $tenant->id)
            ->where('id', $validated['section_id'])
            ->firstOrFail();

        $students = Student::where('students.tenant_id', $tenant->id)
            ->join('student_academic_histories', function ($join) {
                $join->on('students.id', '=', 'student_academic_histories.student_id')
                    ->where('student_academic_histories.is_current', true);
            })
            ->where('student_academic_histories.school_section_id', $section->id)
            ->select('students.*', 'student_academic_histories.roll_number',
                'student_academic_histories.school_class_id')
            ->orderBy('student_academic_histories.roll_number')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $students,
        ]);
    }

    /**
     * Create a new student with admission.
     *
     * Student ID is auto-generated in format `STU-{year}-{4digit}`.
     * Roll number is manually provided by the user (must be unique per class & year).
     * Guardians can be included in the same request.
     *
     * **Required:** name_en, date_of_birth, gender, admission_date, academic_year_id, school_class_id, roll_number
     * **Optional:** All other fields + guardians array
     *
     * @operationId student.store
     */
    public function store(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'name_en' => ['required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::in(['male', 'female', 'other'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'religion' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'birth_reg_no' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'current_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'admission_date' => ['required', 'date'],
            'student_type' => ['nullable', Rule::in(['regular', 'special', 'scholarship'])],
            'is_active' => ['nullable', 'boolean'],

            'academic_year_id' => ['required', 'uuid', 'exists:academic_years,id'],
            'school_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'school_section_id' => ['nullable', 'uuid', 'exists:school_sections,id'],
            'roll_number' => [
                'required', 'integer', 'min:1',
                Rule::unique('student_academic_histories', 'roll_number')
                    ->where('tenant_id', $tenant->id)
                    ->where('academic_year_id', $request->academic_year_id)
                    ->where('school_class_id', $request->school_class_id)
                    ->whereNull('deleted_at'),
            ],

            'guardians' => ['nullable', 'array'],
            'guardians.*.guardian_type' => ['required', Rule::in(['father', 'mother', 'local_guardian', 'custom'])],
            'guardians.*.name_en' => ['required', 'string', 'max:100'],
            'guardians.*.name_bn' => ['nullable', 'string', 'max:200'],
            'guardians.*.occupation' => ['nullable', 'string', 'max:100'],
            'guardians.*.mobile' => ['nullable', 'string', 'max:20'],
            'guardians.*.email' => ['nullable', 'email', 'max:100'],
            'guardians.*.nid_number' => ['nullable', 'string', 'max:50'],
            'guardians.*.address' => ['nullable', 'string'],
            'guardians.*.photo_url' => ['nullable', 'url', 'max:500'],
            'guardians.*.is_primary' => ['nullable', 'boolean'],
        ]);

        $this->ensureClassOwnership($request, $validated['school_class_id']);

        $year = now()->year;

        $prefix = 'STU';

        $lastStudent = Student::where('tenant_id', $tenant->id)
            ->where('student_id', 'like', "{$prefix}-{$year}-%")
            ->orderBy('student_id', 'desc')
            ->first();

        if ($lastStudent) {
            $lastSeq = (int) substr($lastStudent->student_id, -4);
            $nextSeq = str_pad($lastSeq + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $nextSeq = '0001';
        }

        $studentId = "{$prefix}-{$year}-{$nextSeq}";

        $student = DB::transaction(function () use ($tenant, $request, $validated, $studentId) {
            $student = Student::create([
                'tenant_id' => $tenant->id,
                'student_id' => $studentId,
                'name_en' => $validated['name_en'],
                'name_bn' => $validated['name_bn'] ?? null,
                'name_ar' => $validated['name_ar'] ?? null,
                'father_name' => $validated['father_name'] ?? null,
                'mother_name' => $validated['mother_name'] ?? null,
                'date_of_birth' => $validated['date_of_birth'],
                'gender' => $validated['gender'],
                'blood_group' => $validated['blood_group'] ?? null,
                'religion' => $validated['religion'] ?? null,
                'nationality' => $validated['nationality'] ?? 'Bangladeshi',
                'birth_reg_no' => $validated['birth_reg_no'] ?? null,
                'mobile' => $validated['mobile'] ?? null,
                'email' => $validated['email'] ?? null,
                'current_address' => $validated['current_address'] ?? null,
                'permanent_address' => $validated['permanent_address'] ?? null,
                'photo_url' => $validated['photo_url'] ?? null,
                'admission_date' => $validated['admission_date'],
                'student_type' => $validated['student_type'] ?? 'regular',
                'is_active' => $validated['is_active'] ?? true,
                'created_by' => $request->user()->id,
            ]);

            StudentAcademicHistory::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'academic_year_id' => $validated['academic_year_id'],
                'school_class_id' => $validated['school_class_id'],
                'school_section_id' => $validated['school_section_id'] ?? null,
                'roll_number' => $validated['roll_number'],
                'is_current' => true,
                'created_by' => $request->user()->id,
            ]);

            if (! empty($validated['guardians'])) {
                foreach ($validated['guardians'] as $guardianData) {
                    StudentGuardian::create([
                        'tenant_id' => $tenant->id,
                        'student_id' => $student->id,
                        'guardian_type' => $guardianData['guardian_type'],
                        'name_en' => $guardianData['name_en'],
                        'name_bn' => $guardianData['name_bn'] ?? null,
                        'occupation' => $guardianData['occupation'] ?? null,
                        'mobile' => $guardianData['mobile'] ?? null,
                        'email' => $guardianData['email'] ?? null,
                        'nid_number' => $guardianData['nid_number'] ?? null,
                        'address' => $guardianData['address'] ?? null,
                        'photo_url' => $guardianData['photo_url'] ?? null,
                        'is_primary' => $guardianData['is_primary'] ?? false,
                        'created_by' => $request->user()->id,
                    ]);
                }
            }

            return $student;
        });

        $student->load(['academicHistories', 'guardians']);

        return response()->json([
            'status' => 'success',
            'message' => 'Student admitted successfully.',
            'data' => $student,
        ], 201);
    }

    /**
     * Update a student's profile and/or roll number.
     *
     * All fields are optional — only send what needs to change.
     * Roll number is updated in the current academic history record.
     *
     * @operationId student.update
     */
    public function update(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $tenant = $request->user()->tenant;

        $currentHistory = StudentAcademicHistory::where('student_id', $student->id)
            ->where('is_current', true)
            ->first();

        $rollRules = ['nullable', 'integer', 'min:1'];

        if ($currentHistory) {
            $rollRules[] = Rule::unique('student_academic_histories', 'roll_number')
                ->where('tenant_id', $tenant->id)
                ->where('academic_year_id', $currentHistory->academic_year_id)
                ->where('school_class_id', $currentHistory->school_class_id)
                ->whereNull('deleted_at')
                ->ignore($currentHistory->id);
        }

        $validated = $request->validate([
            'name_en' => ['sometimes', 'required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:200'],
            'name_ar' => ['nullable', 'string', 'max:200'],
            'father_name' => ['nullable', 'string', 'max:100'],
            'mother_name' => ['nullable', 'string', 'max:100'],
            'date_of_birth' => ['sometimes', 'required', 'date', 'before_or_equal:today'],
            'gender' => ['sometimes', 'required', Rule::in(['male', 'female', 'other'])],
            'blood_group' => ['nullable', Rule::in(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-'])],
            'religion' => ['nullable', 'string', 'max:50'],
            'nationality' => ['nullable', 'string', 'max:50'],
            'birth_reg_no' => ['nullable', 'string', 'max:50'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'current_address' => ['nullable', 'string'],
            'permanent_address' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'student_type' => ['nullable', Rule::in(['regular', 'special', 'scholarship'])],
            'is_active' => ['nullable', 'boolean'],
            'roll_number' => $rollRules,
        ]);

        DB::transaction(function () use ($student, $validated, $request, $currentHistory) {
            $studentData = collect($validated)->except('roll_number')->toArray();

            if (! empty($studentData)) {
                $studentData['updated_by'] = $request->user()->id;
                $student->update($studentData);
            }

            if (isset($validated['roll_number']) && $currentHistory) {
                $currentHistory->update([
                    'roll_number' => $validated['roll_number'],
                    'updated_by' => $request->user()->id,
                ]);
            }
        });

        $student->load(['academicHistories', 'guardians']);

        return response()->json([
            'status' => 'success',
            'message' => 'Student updated successfully.',
            'data' => $student,
        ]);
    }

    /**
     * Get a single student with academic history and guardians.
     *
     * @operationId student.show
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $student->load(['academicHistories.academicYear', 'academicHistories.schoolClass', 'academicHistories.schoolSection', 'guardians']);

        return response()->json([
            'status' => 'success',
            'data' => $student,
        ]);
    }

    /**
     * Activate a student.
     *
     * @operationId student.activate
     */
    public function activate(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $student->update([
            'is_active' => true,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Student activated successfully.',
            'data' => $student->fresh(),
        ]);
    }

    /**
     * Inactivate a student.
     *
     * @operationId student.inactivate
     */
    public function inactivate(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $student->update([
            'is_active' => false,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Student inactivated successfully.',
            'data' => $student->fresh(),
        ]);
    }

    /**
     * Soft-delete a student.
     *
     * @operationId student.delete
     */
    public function delete(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $student->update([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $validated['delete_reason'] ?? null,
        ]);

        $student->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Student deleted successfully.',
        ]);
    }

    /**
     * Change a student's class (updates current academic record).
     *
     * Optionally update section and roll number in the same request.
     * **Required:** school_class_id
     * **Optional:** school_section_id, roll_number
     *
     * @operationId student.changeClass
     */
    public function changeClass(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $tenant = $request->user()->tenant;

        $currentHistory = StudentAcademicHistory::where('student_id', $student->id)
            ->where('is_current', true)
            ->firstOrFail();

        $validated = $request->validate([
            'school_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'school_section_id' => ['nullable', 'uuid', 'exists:school_sections,id'],
            'roll_number' => [
                'nullable', 'integer', 'min:1',
                Rule::unique('student_academic_histories', 'roll_number')
                    ->where('tenant_id', $tenant->id)
                    ->where('academic_year_id', $currentHistory->academic_year_id)
                    ->where('school_class_id', $request->school_class_id)
                    ->whereNull('deleted_at')
                    ->ignore($currentHistory->id),
            ],
        ]);

        $this->ensureClassOwnership($request, $validated['school_class_id']);

        if ($currentHistory->school_class_id === $validated['school_class_id']) {
            abort(422, 'Student is already in this class.');
        }

        if ($request->filled('school_section_id')) {
            $section = SchoolSection::where('tenant_id', $tenant->id)
                ->where('id', $validated['school_section_id'])
                ->where('school_class_id', $validated['school_class_id'])
                ->first();

            if (! $section) {
                abort(422, 'The selected section does not belong to the new class.');
            }
        }

        $currentHistory->update([
            'school_class_id' => $validated['school_class_id'],
            'school_section_id' => $validated['school_section_id'] ?? $currentHistory->school_section_id,
            'roll_number' => $validated['roll_number'] ?? $currentHistory->roll_number,
            'updated_by' => $request->user()->id,
        ]);

        $student->load(['academicHistories', 'guardians']);

        return response()->json([
            'status' => 'success',
            'message' => 'Class changed successfully.',
            'data' => $student,
        ]);
    }

    /**
     * Change a student's section (updates current academic record).
     *
     * **Required:** school_section_id
     *
     * @operationId student.changeSection
     */
    public function changeSection(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $tenant = $request->user()->tenant;

        $currentHistory = StudentAcademicHistory::where('student_id', $student->id)
            ->where('is_current', true)
            ->firstOrFail();

        $validated = $request->validate([
            'school_section_id' => ['required', 'uuid', 'exists:school_sections,id'],
        ]);

        $section = SchoolSection::where('tenant_id', $tenant->id)
            ->where('id', $validated['school_section_id'])
            ->firstOrFail();

        if ($currentHistory->school_section_id === $section->id) {
            abort(422, 'Student is already in this section.');
        }

        $currentHistory->update([
            'school_section_id' => $section->id,
            'updated_by' => $request->user()->id,
        ]);

        $student->load(['academicHistories', 'guardians']);

        return response()->json([
            'status' => 'success',
            'message' => 'Section changed successfully.',
            'data' => $student,
        ]);
    }

    /**
     * Assign a student to a new section (creates academic history record).
     *
     * **Required:** school_section_id
     *
     * @operationId student.assignSection
     */
    public function assignSection(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $validated = $request->validate([
            'school_section_id' => ['required', 'uuid', 'exists:school_sections,id'],
        ]);

        $tenant = $request->user()->tenant;

        $section = SchoolSection::where('tenant_id', $tenant->id)
            ->where('id', $validated['school_section_id'])
            ->firstOrFail();

        $currentHistory = StudentAcademicHistory::where('student_id', $student->id)
            ->where('is_current', true)
            ->first();

        if (! $currentHistory) {
            abort(422, 'Student has no current academic record.');
        }

        if ($currentHistory->school_section_id === $section->id) {
            abort(422, 'Student is already in this section.');
        }

        DB::transaction(function () use ($currentHistory, $student, $section, $request, $tenant) {
            $currentHistory->update(['is_current' => false]);

            StudentAcademicHistory::create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'academic_year_id' => $currentHistory->academic_year_id,
                'school_class_id' => $currentHistory->school_class_id,
                'school_section_id' => $section->id,
                'roll_number' => $currentHistory->roll_number,
                'is_current' => true,
                'created_by' => $request->user()->id,
            ]);
        });

        $student->load(['academicHistories', 'guardians']);

        return response()->json([
            'status' => 'success',
            'message' => 'Section assigned successfully.',
            'data' => $student,
        ]);
    }

    /**
     * Manually set roll number for a student's current academic record.
     *
     * **Required:** roll_number
     *
     * @operationId student.setRoll
     */
    public function setRoll(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $validated = $request->validate([
            'roll_number' => ['required', 'integer', 'min:1'],
        ]);

        $currentHistory = StudentAcademicHistory::where('student_id', $student->id)
            ->where('is_current', true)
            ->firstOrFail();

        $currentHistory->update([
            'roll_number' => $validated['roll_number'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Roll number updated successfully.',
            'data' => $currentHistory->fresh(),
        ]);
    }

    #[Group('Student Management > Guardians', weight: 52)]
    /**
     * List guardians for a student.
     *
     * @operationId student.guardians
     */
    public function guardians(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        return response()->json([
            'status' => 'success',
            'data' => $student->guardians,
        ]);
    }

    #[Group('Student Management > Guardians', weight: 52)]
    /**
     * Add a guardian to a student.
     *
     * @operationId student.addGuardian
     */
    public function addGuardian(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $validated = $request->validate([
            'guardian_type' => ['required', Rule::in(['father', 'mother', 'local_guardian', 'custom'])],
            'name_en' => ['required', 'string', 'max:100'],
            'name_bn' => ['nullable', 'string', 'max:200'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:100'],
            'nid_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string'],
            'photo_url' => ['nullable', 'url', 'max:500'],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $guardian = $student->guardians()->create([
            'tenant_id' => $request->user()->tenant->id,
            'guardian_type' => $validated['guardian_type'],
            'name_en' => $validated['name_en'],
            'name_bn' => $validated['name_bn'] ?? null,
            'occupation' => $validated['occupation'] ?? null,
            'mobile' => $validated['mobile'] ?? null,
            'email' => $validated['email'] ?? null,
            'nid_number' => $validated['nid_number'] ?? null,
            'address' => $validated['address'] ?? null,
            'photo_url' => $validated['photo_url'] ?? null,
            'is_primary' => $validated['is_primary'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Guardian added successfully.',
            'data' => $guardian->fresh(),
        ], 201);
    }

    #[Group('Student Management > Guardians', weight: 52)]
    /**
     * Set a guardian as the primary guardian for a student.
     *
     * @operationId student.setPrimaryGuardian
     */
    public function setPrimaryGuardian(Request $request, Student $student, StudentGuardian $guardian): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        if ($guardian->student_id !== $student->id) {
            abort(404);
        }

        DB::transaction(function () use ($student) {
            $student->guardians()->update(['is_primary' => false]);
        });

        $guardian->update([
            'is_primary' => true,
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Primary guardian updated successfully.',
            'data' => $guardian->fresh(),
        ]);
    }

    #[Group('Student Management > Guardians', weight: 52)]
    /**
     * Soft-delete a guardian.
     *
     * @operationId student.deleteGuardian
     */
    public function deleteGuardian(Request $request, Student $student, StudentGuardian $guardian): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        if ($guardian->student_id !== $student->id) {
            abort(404);
        }

        $validated = $request->validate([
            'delete_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $guardian->update([
            'deleted_by' => $request->user()->id,
            'delete_reason' => $validated['delete_reason'] ?? null,
        ]);

        $guardian->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Guardian deleted successfully.',
        ]);
    }

    private function ensureTenantOwnership(Request $request, Student $student): void
    {
        if ($student->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }

    private function ensureClassOwnership(Request $request, string $classId): void
    {
        $exists = SchoolClass::where('tenant_id', $request->user()->tenant->id)
            ->where('id', $classId)
            ->exists();

        if (! $exists) {
            abort(422, 'The selected school class is invalid.');
        }
    }
}
