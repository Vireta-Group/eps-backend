<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Student;
use App\Models\StudentAcademicHistory;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentFactory extends Factory
{
    protected $model = Student::class;

    private static int $rollCounter = 1;

    public function configure(): static
    {
        return $this->afterCreating(function (Student $student) {
            $tenant = $student->tenant;

            $academicYear = AcademicYear::where('tenant_id', $tenant->id)->first()
                ?? AcademicYear::factory()->withTenant($tenant)->create();

            $class = SchoolClass::where('tenant_id', $tenant->id)->first()
                ?? SchoolClass::factory()->withTenant($tenant)->create();

            $section = SchoolSection::where('tenant_id', $tenant->id)
                ->where('school_class_id', $class->id)
                ->first()
                ?? SchoolSection::factory()->withClass($class)->create();

            StudentAcademicHistory::factory()->create([
                'tenant_id' => $tenant->id,
                'student_id' => $student->id,
                'academic_year_id' => $academicYear->id,
                'school_class_id' => $class->id,
                'school_section_id' => $section->id,
                'roll_number' => self::$rollCounter++,
                'is_current' => true,
            ]);
        });
    }

    public function definition(): array
    {
        $year = now()->year;

        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => 'STU-'.$year.'-'.$this->faker->unique()->numerify('####'),
            'name_en' => $this->faker->name(),
            'name_bn' => $this->faker->optional()->word(),
            'father_name' => $this->faker->name('male'),
            'mother_name' => $this->faker->name('female'),
            'date_of_birth' => $this->faker->date('Y-m-d', '-6 years'),
            'gender' => $this->faker->randomElement(['male', 'female']),
            'blood_group' => $this->faker->optional()->randomElement(['A+', 'A-', 'B+', 'B-', 'AB+', 'AB-', 'O+', 'O-']),
            'religion' => $this->faker->optional()->word(),
            'nationality' => 'Bangladeshi',
            'admission_date' => $this->faker->date(),
            'student_type' => 'regular',
            'is_active' => true,
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
