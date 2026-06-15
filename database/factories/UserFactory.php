<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name_en' => $this->faker->name(),
            'name_bn' => $this->faker->name().' (বাংলা)',
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->unique()->phoneNumber(),
            'username' => $this->faker->unique()->userName(),
            'password_hash' => static::$password ??= Hash::make('password'),
            'user_type' => 'tenant_admin',
            'user_status' => 'active',
        ];
    }

    public function tenantAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_type' => 'tenant_admin',
        ]);
    }
}
