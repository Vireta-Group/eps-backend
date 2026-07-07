<?php

namespace Database\Factories;

use App\Models\AcademicYear;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class AcademicYearFactory extends Factory
{
    protected $model = AcademicYear::class;

    private static int $yearCounter = 0;

    public function definition(): array
    {
        self::$yearCounter++;
        $startYear = 2024 + self::$yearCounter;

        return [
            'tenant_id' => Tenant::factory(),
            'year_name' => "{$startYear}-".($startYear + 1),
            'year_name_bn' => $this->faker->optional()->word(),
            'start_date' => "{$startYear}-01-01",
            'end_date' => ($startYear + 1).'-12-31',
            'is_current' => false,
            'year_status' => AcademicYear::YEAR_STATUS_UPCOMING,
            'status' => 'active',
        ];
    }

    public function current(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => true,
            'year_status' => AcademicYear::YEAR_STATUS_ACTIVE,
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_current' => false,
            'year_status' => AcademicYear::YEAR_STATUS_ARCHIVED,
            'is_locked' => true,
        ]);
    }

    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_locked' => true,
        ]);
    }

    public function transitionComplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'result_published' => true,
            'promotion_completed' => true,
            'fee_settled' => true,
            'library_cleared' => true,
        ]);
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
