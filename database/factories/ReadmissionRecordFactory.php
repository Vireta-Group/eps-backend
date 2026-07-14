<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\DropoutRecord;
use App\Models\ReadmissionRecord;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReadmissionRecordFactory extends Factory
{
    protected $model = ReadmissionRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'dropout_record_id' => DropoutRecord::factory(),
            'readmission_date' => $this->faker->date(),
            'new_academic_year_id' => AcademicYear::factory(),
            'new_class_id' => SchoolClass::factory(),
            'new_section_id' => SchoolSection::factory(),
            'fee_settled' => false,
        ];
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forStudent(Student $student): static
    {
        return $this->state(fn (array $attributes) => [
            'student_id' => $student->id,
            'tenant_id' => $student->tenant_id,
        ]);
    }
}
