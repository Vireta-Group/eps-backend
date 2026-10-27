<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ParentNotificationPref;
use App\Models\Student;
use App\Models\StudentParent;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Student Management', description: 'Student profiles, guardians, attendance, health, behaviour, dropout, transfer certificates, and reports.', weight: 50)]
class ParentPortalController extends Controller
{
    /**
     * Link a user as a parent of a student.
     *
     * **Required:** student_id, relationship
     *
     * @operationId parent.link
     */
    public function link(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'relationship' => ['required', Rule::in(['father', 'mother', 'local_guardian', 'guardian', 'custom'])],
            'is_primary' => ['nullable', 'boolean'],
        ]);

        $student = Student::where('tenant_id', $request->user()->tenant->id)
            ->where('id', $validated['student_id'])
            ->firstOrFail();

        $exists = StudentParent::where('user_id', $request->user()->id)
            ->where('student_id', $student->id)
            ->exists();

        if ($exists) {
            abort(422, 'This parent is already linked to this student.');
        }

        if ($validated['is_primary'] ?? false) {
            StudentParent::where('student_id', $student->id)->update(['is_primary' => false]);
        }

        $link = StudentParent::create([
            'user_id' => $request->user()->id,
            'student_id' => $student->id,
            'relationship' => $validated['relationship'],
            'is_primary' => $validated['is_primary'] ?? false,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Parent linked successfully.',
            'data' => $link->fresh()->load('student'),
        ], 201);
    }

    /**
     * List children for the authenticated parent.
     *
     * @operationId parent.children
     */
    public function children(Request $request): JsonResponse
    {
        $children = $request->user()->children()
            ->with(['currentAcademic.schoolClass', 'currentAcademic.schoolSection', 'currentPhoto'])
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $children,
        ]);
    }

    /**
     * Parent dashboard — summary of all children.
     *
     * Returns attendance summary, recent result, fee status for each child.
     *
     * @operationId parent.dashboard
     */
    public function dashboard(Request $request): JsonResponse
    {
        $children = $request->user()->children()
            ->with([
                'currentAcademic.schoolClass',
                'currentAcademic.schoolSection',
                'currentPhoto',
            ])
            ->get();

        $dashboardData = $children->map(function ($child) {
            $currentHistory = $child->currentAcademic;

            return [
                'student' => $child,
                'class' => $currentHistory?->schoolClass,
                'section' => $currentHistory?->schoolSection,
                'roll' => $currentHistory?->roll_number,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $dashboardData,
        ]);
    }

    /**
     * Get notification preferences for the authenticated parent.
     *
     * @operationId parent.notificationPrefs
     */
    public function notificationPrefs(Request $request): JsonResponse
    {
        $prefs = $request->user()->notificationPrefs()
            ->orderBy('event_type')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $prefs,
        ]);
    }

    /**
     * Update notification preferences.
     *
     * **Required:** event_type, sms, email, push
     *
     * @operationId parent.updateNotificationPrefs
     */
    public function updateNotificationPrefs(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_type' => ['required', Rule::in([
                'attendance_alert', 'fee_reminder', 'result', 'notice', 'emergency',
            ])],
            'sms' => ['nullable', 'boolean'],
            'email' => ['nullable', 'boolean'],
            'push' => ['nullable', 'boolean'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
        ]);

        $pref = ParentNotificationPref::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'event_type' => $validated['event_type'],
                'student_id' => $validated['student_id'] ?? null,
            ],
            [
                'sms' => $validated['sms'] ?? true,
                'email' => $validated['email'] ?? true,
                'push' => $validated['push'] ?? true,
                'created_by' => $request->user()->id,
            ]
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Notification preference updated.',
            'data' => $pref->fresh(),
        ]);
    }
}
