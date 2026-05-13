<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use Illuminate\Http\JsonResponse;

class StudentController extends Controller
{
    /**
     * Display a listing of the students.
     */
    public function index(): JsonResponse
    {
        $students = Student::all();

        return response()->json([
            'status' => 'success',
            'count' => $students->count(),
            'data' => $students,
        ]);
    }
}
