<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\LeaveType;
use App\Models\Teacher;
use App\Models\TeacherLeaveApplication;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherLeaveApplicationFactory extends Factory
{
    protected $model = TeacherLeaveApplication::class;

    public function definition(): array
    {
        $startDate = $this->faker->date();
        $endDate = $this->faker->dateTimeBetween($startDate, '+10 days')->format('Y-m-d');

        return [
            'tenant_id' => Tenant::factory(),
            'teacher_id' => Teacher::factory(),
            'leave_type_id' => LeaveType::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'total_days' => fake()->numberBetween(1, 5),
            'reason' => $this->faker->sentence(),
            'status' => 'pending',
            'is_demo' => false,
        ];
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function withTeacher(Teacher $teacher): static
    {
        return $this->state(fn (array $attributes) => [
            'teacher_id' => $teacher->id,
        ]);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'approved',
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'rejected',
        ]);
    }
}
