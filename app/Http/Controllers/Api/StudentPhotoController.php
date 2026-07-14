<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Student Management > Profile', description: 'Manage student profiles, admission, section assignment, and roll numbers.', weight: 51)]
class StudentPhotoController extends Controller
{
    /**
     * Get current photo for a student.
     *
     * @operationId student.currentPhoto
     */
    public function show(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $photo = $student->currentPhoto;

        return response()->json([
            'status' => 'success',
            'data' => $photo,
        ]);
    }

    /**
     * Upload or update student photo.
     *
     * Previous current photo is archived automatically.
     * **Required:** photo_url
     *
     * @operationId student.uploadPhoto
     */
    public function store(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $validated = $request->validate([
            'photo_url' => ['required', 'url', 'max:500'],
        ]);

        $tenant = $request->user()->tenant;

        $student->photos()->where('is_current', true)->update([
            'is_current' => false,
            'archived_at' => now(),
        ]);

        $photo = $student->photos()->create([
            'tenant_id' => $tenant->id,
            'photo_url' => $validated['photo_url'],
            'is_current' => true,
            'created_by' => $request->user()->id,
        ]);

        $student->update([
            'photo_url' => $validated['photo_url'],
            'updated_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Photo uploaded successfully.',
            'data' => $photo,
        ], 201);
    }

    /**
     * Get all photo history for a student.
     *
     * @operationId student.photoHistory
     */
    public function history(Request $request, Student $student): JsonResponse
    {
        $this->ensureTenantOwnership($request, $student);

        $photos = $student->photos()->orderBy('created_at', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => $photos,
        ]);
    }

    private function ensureTenantOwnership(Request $request, Student $student): void
    {
        if ($student->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }
}
