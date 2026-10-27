<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\IdCardTemplate;
use App\Models\Student;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Student Management', description: 'Student profiles, guardians, attendance, health, behaviour, dropout, transfer certificates, and reports.', weight: 50)]
class IdCardController extends Controller
{
    /**
     * List ID card templates for the tenant.
     *
     * @operationId idCard.templates
     */
    public function templates(Request $request): JsonResponse
    {
        $templates = IdCardTemplate::where('tenant_id', $request->user()->tenant->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('template_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $templates,
        ]);
    }

    /**
     * Create an ID card template.
     *
     * @operationId idCard.storeTemplate
     */
    public function storeTemplate(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'template_name' => ['required', 'string', 'max:100'],
            'front_layout' => ['nullable', 'json'],
            'back_layout' => ['nullable', 'json'],
            'card_width' => ['nullable', 'string', 'max:20'],
            'card_height' => ['nullable', 'string', 'max:20'],
            'font_family' => ['nullable', 'string', 'max:100'],
            'color_theme' => ['nullable', 'string', 'max:20'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        if ($validated['is_default'] ?? false) {
            IdCardTemplate::where('tenant_id', $tenant->id)->update(['is_default' => false]);
        }

        $template = IdCardTemplate::create([
            'tenant_id' => $tenant->id,
            'template_name' => $validated['template_name'],
            'front_layout' => $validated['front_layout'] ?? null,
            'back_layout' => $validated['back_layout'] ?? null,
            'card_width' => $validated['card_width'] ?? '85.6mm',
            'card_height' => $validated['card_height'] ?? '54mm',
            'font_family' => $validated['font_family'] ?? null,
            'color_theme' => $validated['color_theme'] ?? null,
            'is_default' => $validated['is_default'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'ID card template created successfully.',
            'data' => $template->fresh(),
        ], 201);
    }

    /**
     * Generate ID card for a student.
     *
     * Uses default template. Returns student data with template merged.
     *
     * @operationId idCard.generate
     */
    public function generate(Request $request, Student $student): JsonResponse
    {
        if ($student->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $template = IdCardTemplate::where('tenant_id', $student->tenant_id)
            ->where('is_default', true)
            ->first();

        $student->load(['currentPhoto', 'currentAcademic.schoolClass', 'currentAcademic.schoolSection', 'guardians' => function ($q) {
            $q->where('is_primary', true);
        }]);

        return response()->json([
            'status' => 'success',
            'data' => [
                'student' => $student,
                'template' => $template,
            ],
        ]);
    }

    /**
     * Generate ID cards in bulk for a class or section.
     *
     * **Required:** school_class_id
     * **Optional:** school_section_id
     *
     * @operationId idCard.bulkGenerate
     */
    public function bulkGenerate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'school_class_id' => ['required', 'uuid', 'exists:school_classes,id'],
            'school_section_id' => ['nullable', 'uuid', 'exists:school_sections,id'],
        ]);

        $tenant = $request->user()->tenant;

        $template = IdCardTemplate::where('tenant_id', $tenant->id)
            ->where('is_default', true)
            ->first();

        $query = Student::where('students.tenant_id', $tenant->id)
            ->join('student_academic_histories', function ($join) {
                $join->on('students.id', '=', 'student_academic_histories.student_id')
                    ->where('student_academic_histories.is_current', true);
            })
            ->where('student_academic_histories.school_class_id', $validated['school_class_id']);

        if ($request->filled('school_section_id')) {
            $query->where('student_academic_histories.school_section_id', $validated['school_section_id']);
        }

        $students = $query->select('students.*')->get();
        $students->load(['currentPhoto', 'currentAcademic.schoolClass', 'currentAcademic.schoolSection']);

        return response()->json([
            'status' => 'success',
            'data' => [
                'students' => $students,
                'template' => $template,
                'total' => $students->count(),
            ],
        ]);
    }
}
