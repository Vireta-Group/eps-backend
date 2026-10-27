<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

#[Group('School Setup & Onboarding', description: 'Tenant setup wizard, file uploads, and onboarding steps.', weight: 30)]
class FileUploadController extends Controller
{
    /**
     * Upload a file (logo, favicon, branding image).
     *
     * Upload an image file and receive a public URL to use in step forms.
     *
     * **Accepted types:** logo, logo_primary, logo_landscape, logo_dark, favicon
     * **File constraints:** image only, max 2MB, formats: jpg, jpeg, png, webp, svg
     *
     * The returned `url` can be sent in step 1 (`logo_url`) or step 4 (`logo_primary_url`, etc.).
     *
     * @operationId uploadFile
     */
    public function upload(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'type' => ['required', 'string', 'in:logo,logo_primary,logo_landscape,logo_dark,favicon'],
        ]);

        $tenant = $request->user()->tenant;
        $file = $validated['file'];
        $type = $validated['type'];

        $extension = $file->getClientOriginalExtension();
        $filename = $type.'_'.time().'_'.str()->random(6).'.'.$extension;
        $path = $file->storeAs("uploads/{$tenant->id}", $filename, 'public');

        $url = Storage::disk('public')->url($path);

        return response()->json([
            'status' => 'success',
            'data' => [
                'url' => $url,
                'type' => $type,
                'filename' => $filename,
            ],
        ], 201);
    }
}
