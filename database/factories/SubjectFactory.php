<?php

namespace Database\Factories;

use App\Models\Subject;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

class SubjectFactory extends Factory
{
    protected $model = Subject::class;

    private static array $subjectNames = [
        'Mathematics', 'English', 'Bangla', 'Science', 'Religion',
    ];

    private static int $counter = 0;

    public function definition(): array
    {
        $name = self::$subjectNames[self::$counter % count(self::$subjectNames)];
        self::$counter++;

        return [
            'tenant_id' => Tenant::factory(),
            'subject_name' => $name,
            'subject_type' => Subject::SUBJECT_TYPE_THEORY,
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

    public function optional(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_optional' => true,
        ]);
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function withCode(string $code): static
    {
        return $this->state(fn (array $attributes) => [
            'subject_code' => $code,
        ]);
    }
}
