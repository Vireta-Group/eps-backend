<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\TeacherFingerprintLog;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFingerprintLogFactory extends Factory
{
    protected $model = TeacherFingerprintLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'teacher_id' => Teacher::factory(),
            'device_id' => null,
            'punch_time' => now(),
            'direction' => 'entry',
            'raw_data' => null,
            'processed' => false,
            'synced' => true,
            'synced_at' => now(),
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
