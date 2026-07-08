<?php

namespace Database\Factories;

use App\Models\SchoolClass;
use App\Models\SchoolSection;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SchoolSectionFactory extends Factory
{
    protected $model = SchoolSection::class;

    private static array $sectionNames = ['A', 'B', 'C'];

    private static int $counter = 0;

    public function definition(): array
    {
        $name = self::$sectionNames[self::$counter % count(self::$sectionNames)];
        self::$counter++;

        return [
            'tenant_id' => Tenant::factory(),
            'school_class_id' => SchoolClass::factory(),
            'section_name' => $name,
            'shift' => SchoolSection::SHIFT_MORNING,
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

    public function withClass(SchoolClass $class): static
    {
        return $this->state(fn (array $attributes) => [
            'school_class_id' => $class->id,
            'tenant_id' => $class->tenant_id,
        ]);
    }
}
