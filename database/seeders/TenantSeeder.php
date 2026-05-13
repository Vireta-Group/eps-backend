<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create a default tenant
        Tenant::firstOrCreate(
            ['slug' => 'demo-school'],
            [
                'name_bn' => 'ডেমো স্কুল অ্যান্ড কলেজ',
                'name_en' => 'Demo School & College',
                'name_short' => 'DSC',
                'subdomain' => 'demo.schoolsaas.test',
                'school_type' => 'high_school',
                'eiin_number' => '123456',
                'email' => 'admin@demo-school.com',
                'phone_primary' => '01700000000',
                'tenant_status' => 'active',
                'status' => 'active',
                'onboarding_done' => true,
            ]
        );

        // Create 5 more random tenants
        Tenant::factory()->count(5)->create();
    }
}
