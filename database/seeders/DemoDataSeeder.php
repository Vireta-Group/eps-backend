<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\StudentGuardian;
use App\Models\Subject;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $tenant = Tenant::where('slug', 'demo-school')->first();

        if ($tenant) {
            StudentGuardian::where('tenant_id', $tenant->id)->forceDelete();
            StudentAcademicHistory::where('tenant_id', $tenant->id)->forceDelete();
            Student::where('tenant_id', $tenant->id)->forceDelete();
            Subject::where('tenant_id', $tenant->id)->forceDelete();
            SchoolSection::where('tenant_id', $tenant->id)->forceDelete();
            SchoolClass::where('tenant_id', $tenant->id)->forceDelete();
            AcademicYear::where('tenant_id', $tenant->id)->forceDelete();
            $tenant->forceDelete();
        }

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'demo-school'],
            [
                'name_bn' => 'ডেমো স্কুল অ্যান্ড কলেজ',
                'name_en' => 'Demo School & College',
                'name_short' => 'DSC',
                'subdomain' => 'demo.schoolsaas.test',
                'school_type' => 'high_school',
                'eiin_number' => '123456',
                'email' => 'admin@demo-school.com',
                'phone_primary' => '01700000000',
                'tenant_status' => 'active',
                'status' => 'active',
                'onboarding_done' => true,
                'timezone' => 'Asia/Dhaka',
                'currency' => 'BDT',
            ]
        );

        $classes = [];
        $classNames = [
            ['name_en' => 'Six', 'order' => 6],
            ['name_en' => 'Seven', 'order' => 7],
            ['name_en' => 'Eight', 'order' => 8],
            ['name_en' => 'Nine', 'order' => 9],
            ['name_en' => 'Ten', 'order' => 10],
        ];

        foreach ($classNames as $c) {
            $classes[] = SchoolClass::firstOrCreate(
                ['tenant_id' => $tenant->id, 'class_name' => $c['name_en']],
                [
                    'numeric_order' => $c['order'],
                    'class_type' => SchoolClass::CLASS_TYPE_REGULAR,
                    'is_active' => true,
                    'status' => 'active',
                ]
            );
        }

        $sections = [];
        foreach ($classes as $class) {
            foreach (['A', 'B'] as $sectionName) {
                $sections[] = SchoolSection::firstOrCreate(
                    ['tenant_id' => $tenant->id, 'school_class_id' => $class->id, 'section_name' => $sectionName],
                    [
                        'shift' => SchoolSection::SHIFT_MORNING,
                        'is_active' => true,
                        'status' => 'active',
                    ]
                );
            }
        }

        $subjectData = [
            ['name' => 'Mathematics', 'code' => 'MATH', 'type' => Subject::SUBJECT_TYPE_BOTH],
            ['name' => 'English', 'code' => 'ENG', 'type' => Subject::SUBJECT_TYPE_THEORY],
            ['name' => 'Bangla', 'code' => 'BAN', 'type' => Subject::SUBJECT_TYPE_THEORY],
            ['name' => 'General Science', 'code' => 'SCI', 'type' => Subject::SUBJECT_TYPE_PRACTICAL],
            ['name' => 'Religion', 'code' => 'REL', 'type' => Subject::SUBJECT_TYPE_THEORY],
            ['name' => 'Information Technology', 'code' => 'ICT', 'type' => Subject::SUBJECT_TYPE_BOTH],
        ];

        foreach ($subjectData as $s) {
            Subject::firstOrCreate(
                ['tenant_id' => $tenant->id, 'subject_code' => $s['code']],
                [
                    'subject_name' => $s['name'],
                    'subject_type' => $s['type'],
                    'is_active' => true,
                    'status' => 'active',
                ]
            );
        }

        $academicYear = AcademicYear::firstOrCreate(
            ['tenant_id' => $tenant->id, 'year_name' => '2026-2027'],
            [
                'start_date' => '2026-01-01',
                'end_date' => '2027-12-31',
                'is_current' => true,
                'year_status' => AcademicYear::YEAR_STATUS_ACTIVE,
                'status' => 'active',
            ]
        );

        $studentData = [
            ['name' => 'Abdur Rahman', 'gender' => 'male', 'class_index' => 0, 'section_index' => 0, 'roll' => 1],
            ['name' => 'Fatima Begum', 'gender' => 'female', 'class_index' => 0, 'section_index' => 1, 'roll' => 1],
            ['name' => 'Mohammad Hasan', 'gender' => 'male', 'class_index' => 1, 'section_index' => 0, 'roll' => 1],
            ['name' => 'Jannatul Ferdous', 'gender' => 'female', 'class_index' => 1, 'section_index' => 1, 'roll' => 1],
            ['name' => 'Md. Ibrahim', 'gender' => 'male', 'class_index' => 2, 'section_index' => 0, 'roll' => 1],
            ['name' => 'Sadia Islam', 'gender' => 'female', 'class_index' => 2, 'section_index' => 1, 'roll' => 1],
            ['name' => 'Rafi Ahmed', 'gender' => 'male', 'class_index' => 3, 'section_index' => 0, 'roll' => 1],
            ['name' => 'Nusrat Jahan', 'gender' => 'female', 'class_index' => 3, 'section_index' => 1, 'roll' => 1],
            ['name' => 'Mahbub Karim', 'gender' => 'male', 'class_index' => 4, 'section_index' => 0, 'roll' => 1],
            ['name' => 'Shamima Akhter', 'gender' => 'female', 'class_index' => 4, 'section_index' => 1, 'roll' => 1],
            ['name' => 'Tanvir Ahmmed', 'gender' => 'male', 'class_index' => 0, 'section_index' => 0, 'roll' => 2],
            ['name' => 'Mst. Rumana', 'gender' => 'female', 'class_index' => 1, 'section_index' => 1, 'roll' => 2],
            ['name' => 'Sabbir Hossain', 'gender' => 'male', 'class_index' => 2, 'section_index' => 0, 'roll' => 2],
            ['name' => 'Tahmina Sultana', 'gender' => 'female', 'class_index' => 3, 'section_index' => 1, 'roll' => 2],
            ['name' => 'Rashedul Islam', 'gender' => 'male', 'class_index' => 4, 'section_index' => 0, 'roll' => 2],
            ['name' => 'Karima Begum', 'gender' => 'female', 'class_index' => 0, 'section_index' => 1, 'roll' => 2],
            ['name' => 'Jahidul Haque', 'gender' => 'male', 'class_index' => 1, 'section_index' => 0, 'roll' => 2],
            ['name' => 'Ayesha Khatun', 'gender' => 'female', 'class_index' => 2, 'section_index' => 1, 'roll' => 2],
            ['name' => 'Mizanur Rahman', 'gender' => 'male', 'class_index' => 3, 'section_index' => 0, 'roll' => 2],
            ['name' => 'Shahnaz Parvin', 'gender' => 'female', 'class_index' => 4, 'section_index' => 1, 'roll' => 2],
        ];

        $studentSeq = 0;
        $rollCounterByClass = [];
        foreach ($studentData as $sd) {
            $class = $classes[$sd['class_index']];
            $section = $sections[$sd['section_index']];
            $key = $class->id.'_'.$sd['roll'];

            $studentSeq++;
            $studentId = 'STU-2026-'.str_pad((string) $studentSeq, 4, '0', STR_PAD_LEFT);

            $student = Student::firstOrCreate(
                ['tenant_id' => $tenant->id, 'student_id' => $studentId],
                [
                    'name_en' => $sd['name'],
                    'father_name' => explode(' ', $sd['name'])[0].' Father',
                    'mother_name' => explode(' ', $sd['name'])[0].' Mother',
                    'date_of_birth' => fake()->date('Y-m-d', '-14 years'),
                    'gender' => $sd['gender'],
                    'religion' => 'Islam',
                    'nationality' => 'Bangladeshi',
                    'admission_date' => '2026-01-01',
                    'student_type' => 'regular',
                    'is_active' => true,
                    'status' => 'active',
                ]
            );

            if (! isset($rollCounterByClass[$section->id])) {
                $rollCounterByClass[$section->id] = 1;
            }

            StudentAcademicHistory::firstOrCreate(
                [
                    'tenant_id' => $tenant->id,
                    'student_id' => $student->id,
                    'academic_year_id' => $academicYear->id,
                ],
                [
                    'school_class_id' => $class->id,
                    'school_section_id' => $section->id,
                    'roll_number' => $rollCounterByClass[$section->id],
                    'is_current' => true,
                    'status' => 'active',
                ]
            );

            $rollCounterByClass[$section->id]++;

            $guardianTypes = ['father', 'mother'];
            foreach ($guardianTypes as $i => $type) {
                StudentGuardian::firstOrCreate(
                    [
                        'tenant_id' => $tenant->id,
                        'student_id' => $student->id,
                        'guardian_type' => $type,
                    ],
                    [
                        'name_en' => $type === 'father' ? 'Md. '.explode(' ', $sd['name'])[0].' Ali' : 'Mst. '.explode(' ', $sd['name'])[0].' Begum',
                        'mobile' => '017'.str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT),
                        'is_primary' => $i === 0,
                        'status' => 'active',
                    ]
                );
            }
        }
    }
}
