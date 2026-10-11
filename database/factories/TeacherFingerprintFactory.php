<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\TeacherFingerprint;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFingerprintFactory extends Factory
{
    protected $model = TeacherFingerprint::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'teacher_id' => Teacher::factory(),
            'device_id' => null,
            'finger_index' => $this->faker->numberBetween(1, 2),
            'template_data' => $this->faker->sha256(),
            'label' => $this->faker->optional()->word(),
            'is_active' => true,
            'enrolled_at' => now(),
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
}
