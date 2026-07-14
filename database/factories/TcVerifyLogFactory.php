<?php

namespace Database\Factories;

use App\Models\TcVerifyLog;
use App\Models\Tenant;
use App\Models\TransferCertificate;
use Illuminate\Database\Eloquent\Factories\Factory;

class TcVerifyLogFactory extends Factory
{
    protected $model = TcVerifyLog::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'transfer_certificate_id' => TransferCertificate::factory(),
            'tc_number' => $this->faker->bothify('TC-####-#####'),
            'verified_at' => now(),
            'is_valid' => true,
        ];
    }

    public function withTenant(Tenant $tenant): static
    {
        return $this->state(fn (array $attributes) => [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function forTc(TransferCertificate $tc): static
    {
        return $this->state(fn (array $attributes) => [
            'transfer_certificate_id' => $tc->id,
            'tc_number' => $tc->tc_number,
            'tenant_id' => $tc->tenant_id,
        ]);
    }
}
