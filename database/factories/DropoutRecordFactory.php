<?php

namespace Database\Factories;

use App\Models\DropoutRecord;
use App\Models\Student;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class DropoutRecordFactory extends Factory
{
    protected $model = DropoutRecord::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'dropout_date' => $this->faker->date(),
            'dropout_category' => $this->faker->randomElement([
                'financial', 'family', 'distance', 'employment', 'marriage',
                'transfer_school', 'illness', 'disinterest', 'unknown', 'other',
            ]),
            'status' => 'active',
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
