<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentAcademicHistoryFactory extends Factory
{
    protected $model = StudentAcademicHistory::class;

    private static int $rollCounter = 1;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'school_class_id' => SchoolClass::factory(),
            'school_section_id' => SchoolSection::factory(),
            'roll_number' => self::$rollCounter++,
            'is_current' => true,
            'status' => 'active',
        ];
    }

    public function notCurrent(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => false,
        ]);
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
