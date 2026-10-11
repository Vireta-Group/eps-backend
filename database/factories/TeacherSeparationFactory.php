<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\TeacherSeparation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherSeparationFactory extends Factory
{
    protected $model = TeacherSeparation::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'teacher_id' => Teacher::factory(),
            'type' => TeacherSeparation::TYPE_RESIGNATION,
            'termination_type' => null,
            'submitted_by' => User::factory(),
            'resignation_date' => now()->toDateString(),
            'last_working_day' => now()->addMonth()->toDateString(),
            'reason' => $this->faker->sentence(),
            'notice_period_days' => 30,
            'notice_served' => false,
            'notice_waived' => false,
            'notice_penalty_amount' => 0,
            'status' => TeacherSeparation::STATUS_PENDING,
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
            'tenant_id' => $teacher->tenant_id,
            'teacher_id' => $teacher->id,
        ]);
    }

    public function termination(string $type = TeacherSeparation::TERMINATION_MISCONDUCT): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => TeacherSeparation::TYPE_TERMINATION,
            'termination_type' => $type,
            'resignation_date' => null,
        ]);
    }
}
