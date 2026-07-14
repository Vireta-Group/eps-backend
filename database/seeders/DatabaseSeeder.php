<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name_en' => 'Test User',
                'password_hash' => bcrypt('password'),
                'user_type' => 'tenant_admin',
                'user_status' => 'active',
            ]
        );

        $this->call([
            DemoDataSeeder::class,
        ]);
    }
}
