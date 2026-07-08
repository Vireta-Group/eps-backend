<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolClassFactory extends Factory
{
    protected $model = SchoolClass::class;

    private static array $classNames = [
        'Six', 'Seven', 'Eight', 'Nine', 'Ten',
    ];

    private static int $counter = 0;

    public function definition(): array
    {
        $name = self::$classNames[self::$counter % count(self::$classNames)];
        self::$counter++;

        return [
            'tenant_id' => Tenant::factory(),
            'class_name' => $name,
            'numeric_order' => self::$counter,
            'class_type' => SchoolClass::CLASS_TYPE_REGULAR,
            'is_active' => true,
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function withOrder(int $order): static
    {
        return $this->state(fn (array $attributes) => [
            'numeric_order' => $order,
        ]);
    }
}
