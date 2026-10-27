<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Student Management', description: 'Student profiles, guardians, attendance, health, behaviour, dropout, transfer certificates, and reports.', weight: 50)]
class StudentTimelineController extends Controller
{
    /**
     * Get timeline for a student.
     *
     * All activities (admission, document upload, photo change, class/section change,
     * TC, dropout, behaviour) are listed chronologically.
     *
     * @operationId student.timeline
     */
    public function index(Request $request, Student $student): JsonResponse
    {
        if ($student->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $logs = $student->timelineLogs()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $logs,
        ]);
    }
}
