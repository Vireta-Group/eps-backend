<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\StudentGuardian;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class StudentGuardianFactory extends Factory
{
    protected $model = StudentGuardian::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'guardian_type' => 'father',
            'name_en' => $this->faker->name(),
            'mobile' => $this->faker->phoneNumber(),
            'is_primary' => false,
            'status' => 'active',
        ];
    }

    public function primary(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_primary' => true,
        ]);
    }
}
