<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Teacher;
use App\Models\TeacherAttendance;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherAttendanceFactory extends Factory
{
    protected $model = TeacherAttendance::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'teacher_id' => Teacher::factory(),
            'academic_year_id' => AcademicYear::factory(),
            'attendance_date' => $this->faker->date(),
            'check_in_time' => $this->faker->optional()->time('H:i:s'),
            'check_out_time' => $this->faker->optional()->time('H:i:s'),
            'status' => $this->faker->randomElement(['present', 'absent', 'late', 'half_day']),
            'source' => 'manual',
            'remarks' => $this->faker->optional()->sentence(),
            'marked_by' => User::factory(),
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

    public function present(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'present',
            'check_in_time' => '09:00:00',
            'check_out_time' => '17:00:00',
        ]);
    }

    public function absent(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'absent',
            'check_in_time' => null,
            'check_out_time' => null,
        ]);
    }
}
