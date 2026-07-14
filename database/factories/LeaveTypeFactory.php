<?php

namespace Database\Factories;

use App\Models\LeaveType;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class LeaveTypeFactory extends Factory
{
    protected $model = LeaveType::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'leave_type_name' => $this->faker->randomElement(['Annual Leave', 'Sick Leave', 'Casual Leave', 'Maternity Leave', 'Paternity Leave', 'Study Leave']),
            'description' => $this->faker->optional()->sentence(),
            'default_days' => $this->faker->randomElement([10, 14, 20, 30, 90]),
            'is_paid' => $this->faker->boolean(80),
            'is_active' => true,
            'is_demo' => false,
        ];
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }
}
