<?php

namespace Database\Factories;

use App\Models\Student;
use App\Models\Tenant;
use App\Models\TransferCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransferCertificateFactory extends Factory
{
    protected $model = TransferCertificate::class;

    private static int $tcCounter = 1;

    public function definition(): array
    {
        $year = now()->year;

        return [
            'tenant_id' => Tenant::factory(),
            'student_id' => Student::factory(),
            'tc_number' => 'TC-'.$year.'-'.str_pad((string) self::$tcCounter++, 5, '0', STR_PAD_LEFT),
            'issue_date' => $this->faker->date(),
            'conduct' => $this->faker->randomElement(['good', 'satisfactory', 'needs_improvement']),
            'leaving_reason_category' => $this->faker->randomElement(['financial', 'family', 'transfer_school']),
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

    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);
    }
}
