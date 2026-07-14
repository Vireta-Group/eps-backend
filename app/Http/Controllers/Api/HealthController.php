<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Allergy;
use App\Models\EmergencyContact;
use App\Models\HealthCheckup;
use App\Models\MedicalCondition;
use App\Models\Student;
use App\Models\StudentHealthRecord;
use App\Models\VaccinationRecord;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

#[Group('Student Management > Health', weight: 56)]
class HealthController extends Controller
{
    private function ensureTenantOwnership(Request $request, mixed $model): void
    {
        if ($model->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }
    }

    private function calculateBmi(?float $heightCm, ?float $weightKg): ?array
    {
        if (! $heightCm || ! $weightKg || $heightCm <= 0) {
            return null;
        }

        $heightM = $heightCm / 100;
        $bmi = round($weightKg / ($heightM * $heightM), 1);

        if ($bmi < 18.5) {
            $status = 'underweight';
        } elseif ($bmi < 25) {
            $status = 'normal';
        } elseif ($bmi < 30) {
            $status = 'overweight';
        } else {
            $status = 'obese';
        }

        return ['bmi' => $bmi, 'bmi_status' => $status];
    }

    public function healthRecords(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = StudentHealthRecord::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $records = $query->with('student')
            ->orderBy('record_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $records,
        ]);
    }

    public function storeHealthRecord(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'record_date' => ['required', 'date'],
            'height_cm' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'weight_kg' => ['nullable', 'numeric', 'min:0', 'max:999.9'],
            'notes' => ['nullable', 'string'],
        ]);

        $bmiData = $this->calculateBmi(
            $validated['height_cm'] ?? null,
            $validated['weight_kg'] ?? null,
        );

        $record = StudentHealthRecord::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'record_date' => $validated['record_date'],
            'height_cm' => $validated['height_cm'] ?? null,
            'weight_kg' => $validated['weight_kg'] ?? null,
            'bmi' => $bmiData['bmi'] ?? null,
            'bmi_status' => $bmiData['bmi_status'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'recorded_by' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Health record saved successfully.',
            'data' => $record,
        ], 201);
    }

    public function growthChart(Request $request): JsonResponse
    {
        $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
        ]);

        $tenant = $request->user()->tenant;

        $records = StudentHealthRecord::where('tenant_id', $tenant->id)
            ->where('student_id', $request->student_id)
            ->whereNotNull('height_cm')
            ->whereNotNull('weight_kg')
            ->orderBy('record_date', 'asc')
            ->get(['record_date', 'height_cm', 'weight_kg', 'bmi', 'bmi_status']);

        return response()->json([
            'status' => 'success',
            'data' => $records,
        ]);
    }

    public function vaccinations(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = VaccinationRecord::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('is_due')) {
            $query->whereNotNull('next_due_date')
                ->where('next_due_date', '<=', now()->addDays(30));
        }

        $records = $query->with('student')
            ->orderBy('vaccination_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $records,
        ]);
    }

    public function storeVaccination(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'vaccine_name' => ['required', 'string', 'max:200'],
            'dose_number' => ['nullable', 'string', 'max:20'],
            'vaccination_date' => ['required', 'date'],
            'next_due_date' => ['nullable', 'date', 'after_or_equal:vaccination_date'],
            'healthcare_provider' => ['nullable', 'string', 'max:200'],
            'certificate_url' => ['nullable', 'url'],
            'notes' => ['nullable', 'string'],
        ]);

        $record = VaccinationRecord::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'vaccine_name' => $validated['vaccine_name'],
            'dose_number' => $validated['dose_number'] ?? null,
            'vaccination_date' => $validated['vaccination_date'],
            'next_due_date' => $validated['next_due_date'] ?? null,
            'healthcare_provider' => $validated['healthcare_provider'] ?? null,
            'certificate_url' => $validated['certificate_url'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Vaccination record saved successfully.',
            'data' => $record,
        ], 201);
    }

    public function dueVaccinations(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $dueDate = now()->addDays(30);

        $records = VaccinationRecord::where('tenant_id', $tenant->id)
            ->whereNotNull('next_due_date')
            ->where('next_due_date', '<=', $dueDate)
            ->with('student')
            ->orderBy('next_due_date', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $records,
        ]);
    }

    public function medicalConditions(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = MedicalCondition::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $conditions = $query->with('student')
            ->orderBy('condition_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $conditions,
        ]);
    }

    public function storeMedicalCondition(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'condition_name' => ['required', 'string', 'max:200'],
            'condition_type' => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string'],
            'management_plan' => ['nullable', 'string'],
            'doctor_name' => ['nullable', 'string', 'max:200'],
            'doctor_contact' => ['nullable', 'string', 'max:50'],
            'medication' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $condition = MedicalCondition::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'condition_name' => $validated['condition_name'],
            'condition_type' => $validated['condition_type'] ?? 'chronic',
            'description' => $validated['description'] ?? null,
            'management_plan' => $validated['management_plan'] ?? null,
            'doctor_name' => $validated['doctor_name'] ?? null,
            'doctor_contact' => $validated['doctor_contact'] ?? null,
            'medication' => $validated['medication'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Medical condition saved successfully.',
            'data' => $condition,
        ], 201);
    }

    public function allergies(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = Allergy::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('severity')) {
            $query->where('severity', $request->severity);
        }

        $allergies = $query->with('student')
            ->orderBy('allergy_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $allergies,
        ]);
    }

    public function storeAllergy(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'allergy_name' => ['required', 'string', 'max:200'],
            'allergy_type' => ['required', 'string', 'max:50'],
            'severity' => ['nullable', Rule::in(['mild', 'moderate', 'severe', 'critical'])],
            'reaction' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
        ]);

        $allergy = Allergy::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'allergy_name' => $validated['allergy_name'],
            'allergy_type' => $validated['allergy_type'],
            'severity' => $validated['severity'] ?? 'moderate',
            'reaction' => $validated['reaction'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'is_active' => $validated['is_active'] ?? true,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Allergy recorded successfully.',
            'data' => $allergy,
        ], 201);
    }

    public function criticalAllergies(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $allergies = Allergy::where('tenant_id', $tenant->id)
            ->whereIn('severity', ['severe', 'critical'])
            ->where('is_active', true)
            ->with('student')
            ->orderBy('severity', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $allergies,
        ]);
    }

    public function checkups(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = HealthCheckup::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $checkups = $query->with('student')
            ->orderBy('checkup_date', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $checkups,
        ]);
    }

    public function storeCheckup(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'checkup_date' => ['required', 'date'],
            'checkup_type' => ['required', 'string', 'max:50'],
            'doctor_name' => ['nullable', 'string', 'max:200'],
            'findings' => ['nullable', 'string'],
            'recommendations' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date', 'after_or_equal:checkup_date'],
            'report_url' => ['nullable', 'url'],
        ]);

        $checkup = HealthCheckup::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'checkup_date' => $validated['checkup_date'],
            'checkup_type' => $validated['checkup_type'],
            'doctor_name' => $validated['doctor_name'] ?? null,
            'findings' => $validated['findings'] ?? null,
            'recommendations' => $validated['recommendations'] ?? null,
            'follow_up_date' => $validated['follow_up_date'] ?? null,
            'report_url' => $validated['report_url'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Health checkup saved successfully.',
            'data' => $checkup,
        ], 201);
    }

    public function emergencyContacts(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $query = EmergencyContact::where('tenant_id', $tenant->id);

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        $contacts = $query->with('student')
            ->orderBy('is_primary', 'desc')
            ->orderBy('contact_name')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $contacts,
        ]);
    }

    public function storeEmergencyContact(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $validated = $request->validate([
            'student_id' => ['required', 'uuid', 'exists:students,id'],
            'contact_name' => ['required', 'string', 'max:200'],
            'relationship' => ['required', 'string', 'max:50'],
            'mobile' => ['required', 'string', 'max:20'],
            'alternative_mobile' => ['nullable', 'string', 'max:20'],
            'is_primary' => ['nullable', 'boolean'],
            'preferred_hospital' => ['nullable', 'string'],
            'doctor_name' => ['nullable', 'string', 'max:200'],
            'doctor_contact' => ['nullable', 'string', 'max:50'],
            'insurance_info' => ['nullable', 'string'],
            'special_instructions' => ['nullable', 'string'],
        ]);

        $contact = EmergencyContact::create([
            'tenant_id' => $tenant->id,
            'student_id' => $validated['student_id'],
            'contact_name' => $validated['contact_name'],
            'relationship' => $validated['relationship'],
            'mobile' => $validated['mobile'],
            'alternative_mobile' => $validated['alternative_mobile'] ?? null,
            'is_primary' => $validated['is_primary'] ?? false,
            'preferred_hospital' => $validated['preferred_hospital'] ?? null,
            'doctor_name' => $validated['doctor_name'] ?? null,
            'doctor_contact' => $validated['doctor_contact'] ?? null,
            'insurance_info' => $validated['insurance_info'] ?? null,
            'special_instructions' => $validated['special_instructions'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Emergency contact saved successfully.',
            'data' => $contact,
        ], 201);
    }

    public function setPrimaryContact(Request $request, EmergencyContact $contact): JsonResponse
    {
        $this->ensureTenantOwnership($request, $contact);

        EmergencyContact::where('tenant_id', $request->user()->tenant->id)
            ->where('student_id', $contact->student_id)
            ->update(['is_primary' => false]);

        $contact->update(['is_primary' => true]);

        return response()->json([
            'status' => 'success',
            'message' => 'Primary contact set successfully.',
            'data' => $contact->fresh()->load('student'),
        ]);
    }

    public function studentHealthSummary(Request $request, Student $student): JsonResponse
    {
        if ($student->tenant_id !== $request->user()->tenant->id) {
            abort(404);
        }

        $records = StudentHealthRecord::where('student_id', $student->id)->orderBy('record_date', 'desc')->get();
        $vaccinations = VaccinationRecord::where('student_id', $student->id)->orderBy('vaccination_date', 'desc')->get();
        $conditions = MedicalCondition::where('student_id', $student->id)->where('is_active', true)->get();
        $allergies = Allergy::where('student_id', $student->id)->where('is_active', true)->get();
        $checkups = HealthCheckup::where('student_id', $student->id)->orderBy('checkup_date', 'desc')->get();
        $emergencyContacts = EmergencyContact::where('student_id', $student->id)->orderBy('is_primary', 'desc')->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'student' => $student->only(['id', 'student_id', 'name_en', 'blood_group']),
                'health_records' => $records,
                'vaccinations' => $vaccinations,
                'medical_conditions' => $conditions,
                'allergies' => $allergies,
                'checkups' => $checkups,
                'emergency_contacts' => $emergencyContacts,
            ],
        ]);
    }
}
