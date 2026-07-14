<?php

namespace Database\Factories;

use App\Models\Teacher;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherFactory extends Factory
{
    protected $model = Teacher::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_id' => User::factory(),
            'employee_id' => 'TCH-'.now()->year.'-'.$this->faker->unique()->numerify('#####'),
            'designation' => $this->faker->randomElement(['Assistant Teacher', 'Senior Teacher', 'Head Teacher', 'Subject Teacher']),
            'department' => $this->faker->randomElement(['Science', 'Arts', 'Commerce', 'Mathematics', 'English']),
            'employment_type' => $this->faker->randomElement(['full_time', 'part_time', 'contractual']),
            'mpo_status' => $this->faker->boolean(),
            'is_active' => true,
        ];
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function withUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
