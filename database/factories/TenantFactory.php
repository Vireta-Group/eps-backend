<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = $this->faker->company();
        $slug = Str::slug($name);

        return [
            'name_bn' => $name . ' (বাংলা)',
            'name_en' => $name,
            'name_short' => Str::limit($name, 10, ''),
            'slug' => $slug,
            'subdomain' => $slug . '.schoolsaas.test',
            'school_type' => $this->faker->randomElement(['primary', 'high_school', 'college', 'madrasa']),
            'eiin_number' => $this->faker->unique()->numerify('######'),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_primary' => $this->faker->phoneNumber(),
            'tenant_status' => 'active',
            'status' => 'active',
            'timezone' => 'Asia/Dhaka',
            'default_language' => 'bn',
            'currency' => 'BDT',
            'settings' => [],
            'onboarding_done' => true,
        ];
    }
}
