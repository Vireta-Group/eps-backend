<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\TeacherSettlement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherSettlementFactory extends Factory
{
    protected $model = TeacherSettlement::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'teacher_id' => Teacher::factory(),
            'separation_id' => null,
            'last_working_day' => now()->toDateString(),
            'components' => [],
            'gross_amount' => 0,
            'deductions' => [],
            'total_deductions' => 0,
            'net_amount' => 0,
            'status' => TeacherSettlement::STATUS_DRAFT,
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
}
