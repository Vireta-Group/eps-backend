<?php

namespace Database\Factories;

use App\Models\SalaryStructure;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalaryStructureFactory extends Factory
{
    protected $model = SalaryStructure::class;

    public function definition(): array
    {
        $basic = $this->faker->randomFloat(2, 15000, 80000);
        $houseRent = round($basic * 0.5, 2);
        $medical = 2000;
        $transport = 1500;
        $other = ['special_allowance' => 1000];

        return [
            'tenant_id' => Tenant::factory(),
            'structure_name' => $this->faker->randomElement(['Grade 1', 'Grade 2', 'Grade 3', 'Senior Scale', 'Entry Level']),
            'description' => $this->faker->sentence(),
            'basic_pay' => $basic,
            'house_rent' => $houseRent,
            'medical_allowance' => $medical,
            'transport_allowance' => $transport,
            'other_allowances' => $other,
            'total' => $basic + $houseRent + $medical + $transport + array_sum($other),
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
