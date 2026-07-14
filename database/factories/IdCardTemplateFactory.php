<?php

namespace Database\Factories;

use App\Models\IdCardTemplate;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class IdCardTemplateFactory extends Factory
{
    protected $model = IdCardTemplate::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'template_name' => fake()->word().' Card',
            'front_layout' => json_encode(['logo' => 'top', 'photo' => 'center', 'info' => 'bottom']),
            'card_width' => '85.6mm',
            'card_height' => '54mm',
            'is_default' => false,
            'is_active' => true,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_default' => true,
        ]);
    }
}
