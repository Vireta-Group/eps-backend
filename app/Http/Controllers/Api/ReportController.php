<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BehaviourIncident;
use App\Models\Student;
use App\Models\StudentAttendance;
use App\Models\StudentHealthRecord;
use App\Models\VaccinationRecord;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Student Management > Reports', description: 'Admission, student summary, attendance, and health analytics.', weight: 59)]
class ReportController extends Controller
{
    /**
     * Admission report — monthly and yearly admission counts.
     *
     * @operationId reports.admission
     */
    public function admission(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $year = $request->input('year', date('Y'));

        $monthly = Student::where('tenant_id', $tenant->id)
            ->whereYear('admission_date', $year)
            ->selectRaw('EXTRACT(MONTH FROM admission_date) as month')
            ->selectRaw('COUNT(*) as total')
            ->groupByRaw('EXTRACT(MONTH FROM admission_date)')
            ->orderByRaw('EXTRACT(MONTH FROM admission_date)')
            ->get();

        $byClass = Student::query()
            ->selectRaw("COALESCE(sah.school_class_id::text, 'unknown') as class_id")
            ->selectRaw('COUNT(*) as total')
            ->leftJoin('student_academic_histories as sah', function ($join) {
                $join->on('students.id', '=', 'sah.student_id')
                    ->where('sah.is_current', true);
            })
            ->where('students.tenant_id', $tenant->id)
            ->whereYear('admission_date', $year)
            ->groupByRaw("COALESCE(sah.school_class_id::text, 'unknown')")
            ->get();

        $byGender = Student::where('tenant_id', $tenant->id)
            ->whereYear('admission_date', $year)
            ->selectRaw('gender')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('gender')
            ->get();

        $totalAdmission = Student::where('tenant_id', $tenant->id)
            ->whereYear('admission_date', $year)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'year' => $year,
                'total_admission' => $totalAdmission,
                'monthly' => $monthly,
                'by_class' => $byClass,
                'by_gender' => $byGender,
            ],
        ]);
    }

    /**
     * Student summary report — counts by class, gender, blood group, status.
     *
     * @operationId reports.studentSummary
     */
    public function studentSummary(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $byClass = Student::query()
            ->selectRaw("COALESCE(sah.school_class_id::text, 'unknown') as class_id")
            ->selectRaw('COUNT(*) as total')
            ->leftJoin('student_academic_histories as sah', function ($join) {
                $join->on('students.id', '=', 'sah.student_id')
                    ->where('sah.is_current', true);
            })
            ->where('students.tenant_id', $tenant->id)
            ->where('students.is_active', true)
            ->groupByRaw("COALESCE(sah.school_class_id::text, 'unknown')")
            ->get();

        $byGender = Student::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->selectRaw('gender')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('gender')
            ->get();

        $byBloodGroup = Student::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->selectRaw("COALESCE(blood_group, 'unknown') as blood_group")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('blood_group')
            ->get();

        $byReligion = Student::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->selectRaw("COALESCE(religion, 'unknown') as religion")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('religion')
            ->get();

        $totalActive = Student::where('tenant_id', $tenant->id)->where('is_active', true)->count();
        $totalInactive = Student::where('tenant_id', $tenant->id)->where('is_active', false)->count();
        $totalStudents = Student::where('tenant_id', $tenant->id)->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'total_students' => $totalStudents,
                'active' => $totalActive,
                'inactive' => $totalInactive,
                'by_class' => $byClass,
                'by_gender' => $byGender,
                'by_blood_group' => $byBloodGroup,
                'by_religion' => $byReligion,
            ],
        ]);
    }

    /**
     * Health summary report — blood group distribution, vaccination coverage, BMI stats.
     *
     * @operationId reports.healthSummary
     */
    public function healthSummary(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;

        $bloodGroupDist = Student::where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->selectRaw("COALESCE(blood_group, 'unknown') as blood_group")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('blood_group')
            ->get();

        $vaccinationCoverage = VaccinationRecord::whereHas('student', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)->where('is_active', true);
        })
            ->selectRaw('vaccine_name')
            ->selectRaw('COUNT(DISTINCT student_id) as total')
            ->groupBy('vaccine_name')
            ->get();

        $bmiStats = StudentHealthRecord::whereHas('student', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)->where('is_active', true);
        })
            ->selectRaw('bmi_status')
            ->selectRaw('COUNT(*) as total')
            ->whereNotNull('bmi_status')
            ->groupBy('bmi_status')
            ->get();

        $commonConditions = StudentHealthRecord::whereHas('student', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id)->where('is_active', true);
        })
            ->selectRaw("COALESCE(bmi_status::text, 'unknown') as condition_name")
            ->selectRaw('COUNT(*) as total')
            ->groupBy('condition_name')
            ->orderByDesc('total')
            ->limit(10)
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'blood_group_distribution' => $bloodGroupDist,
                'vaccination_coverage' => $vaccinationCoverage,
                'bmi_distribution' => $bmiStats,
                'common_conditions' => $commonConditions,
            ],
        ]);
    }

    /**
     * Attendance summary report — overall attendance stats for a date range.
     *
     * @operationId reports.attendanceSummary
     */
    public function attendanceSummary(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $startDate = $request->input('start_date', now()->startOfMonth()->toDateString());
        $endDate = $request->input('end_date', now()->toDateString());

        $summary = StudentAttendance::where('tenant_id', $tenant->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('status')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('status')
            ->get();

        $dailyTrend = StudentAttendance::where('tenant_id', $tenant->id)
            ->whereBetween('attendance_date', [$startDate, $endDate])
            ->selectRaw('attendance_date')
            ->selectRaw('COUNT(*) as total')
            ->selectRaw("SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) as present")
            ->selectRaw("SUM(CASE WHEN status = 'absent' THEN 1 ELSE 0 END) as absent")
            ->groupBy('attendance_date')
            ->orderBy('attendance_date')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'by_status' => $summary,
                'daily_trend' => $dailyTrend,
            ],
        ]);
    }

    /**
     * Behaviour summary — incident and disciplinary action stats.
     *
     * @operationId reports.behaviourSummary
     */
    public function behaviourSummary(Request $request): JsonResponse
    {
        $tenant = $request->user()->tenant;
        $year = $request->input('year', date('Y'));

        $byType = BehaviourIncident::where('tenant_id', $tenant->id)
            ->whereYear('incident_date', $year)
            ->selectRaw('incident_type')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('incident_type')
            ->get();

        $bySeverity = BehaviourIncident::where('tenant_id', $tenant->id)
            ->whereYear('incident_date', $year)
            ->selectRaw('severity')
            ->selectRaw('COUNT(*) as total')
            ->groupBy('severity')
            ->get();

        $totalIncidents = BehaviourIncident::where('tenant_id', $tenant->id)
            ->whereYear('incident_date', $year)
            ->count();

        return response()->json([
            'status' => 'success',
            'data' => [
                'year' => $year,
                'total_incidents' => $totalIncidents,
                'by_type' => $byType,
                'by_severity' => $bySeverity,
            ],
        ]);
    }
}
