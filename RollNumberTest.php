<?php

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class RollNumberTest extends TestCase
{
    use DatabaseTransactions;

    public function test_roll_number_appears_in_by_class_response(): void
    {
        $tenant = Tenant::factory()->create();
        $user = User::factory()->create(['tenant_id' => $tenant->id]);
        $class = SchoolClass::factory()->withTenant($tenant)->create();
        $year = AcademicYear::factory()->withTenant($tenant)->create();
        $section = SchoolSection::factory()->withClass($class)->create();

        $student = Student::create([
            'tenant_id' => $tenant->id,
            'student_id' => 'STU-2026-0001',
            'name_en' => 'Test',
            'date_of_birth' => '2010-01-01',
            'gender' => 'male',
            'admission_date' => '2026-01-01',
        ]);

        StudentAcademicHistory::create([
            'tenant_id' => $tenant->id,
            'student_id' => $student->id,
            'academic_year_id' => $year->id,
            'school_class_id' => $class->id,
            'school_section_id' => $section->id,
            'roll_number' => 5,
            'is_current' => true,
        ]);

        $result = Student::where('students.tenant_id', $tenant->id)
            ->join('student_academic_histories', function ($j) {
                $j->on('students.id', '=', 'student_academic_histories.student_id')
                    ->where('student_academic_histories.is_current', true);
            })
            ->where('student_academic_histories.school_class_id', $class->id)
            ->select('students.*', 'student_academic_histories.roll_number',
                'student_academic_histories.school_section_id')
            ->get();

        $data = $result->first()->toArray();

        echo "\n=== Attributes ===\n";
        print_r(array_keys($data));
        echo "\nroll_number: ".(isset($data['roll_number']) ? $data['roll_number'] : 'MISSING')."\n";
        echo 'school_section_id: '.(isset($data['school_section_id']) ? $data['school_section_id'] : 'MISSING')."\n";
        echo "\n=== Full JSON ===\n";
        echo json_encode($data, JSON_PRETTY_PRINT)."\n";

        $this->assertArrayHasKey('roll_number', $data);
    }
}
