<?php

namespace Database\Factories;

use App\Models\TeacherSeparation;
use App\Models\TeacherSeparationDocument;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class TeacherSeparationDocumentFactory extends Factory
{
    protected $model = TeacherSeparationDocument::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'separation_id' => TeacherSeparation::factory(),
            'document_type' => TeacherSeparationDocument::TYPE_WARNING_LETTER,
            'title' => $this->faker->words(3, true),
            'file_url' => $this->faker->url(),
            'notes' => $this->faker->sentence(),
            'document_date' => now()->toDateString(),
        ];
    }

    public function forSeparation(TeacherSeparation $separation): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $separation->tenant_id,
            'separation_id' => $separation->id,
        ]);
    }
}
