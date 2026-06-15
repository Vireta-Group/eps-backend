<?php

namespace Database\Factories;

use App\Models\Plan;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class TenantFactory extends Factory
{
    protected $model = Tenant::class;

    public function definition(): array
    {
        $name = $this->faker->company();
        $slug = Str::slug($name).'-'.Str::lower(Str::random(6));

        return [
            'name_bn' => $name.' (বাংলা)',
            'name_en' => $name,
            'name_short' => Str::limit($name, 10, ''),
            'slug' => $slug,
            'subdomain' => $slug,
            'school_type' => $this->faker->randomElement(['primary', 'high_school', 'college', 'madrasa']),
            'eiin_number' => $this->faker->unique()->numerify('######'),
            'email' => $this->faker->unique()->safeEmail(),
            'phone_primary' => $this->faker->phoneNumber(),
            'current_plan_id' => Plan::where('slug', 'trial')->first()?->id,
            'tenant_status' => 'setup',
            'status' => 'active',
            'timezone' => 'Asia/Dhaka',
            'default_language' => 'bn',
            'currency' => 'BDT',
            'settings' => [],
            'onboarding_step' => 1,
            'onboarding_done' => false,
        ];
    }

    public function onboardingComplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'onboarding_step' => 5,
            'onboarding_done' => true,
            'tenant_status' => 'active',
        ]);
    }
}
