<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Default local reset: `php artisan migrate:fresh --seed`
 *
 * Demo logins (password for all: `password`):
 * - Merchant: user@erdcore.test
 * - Store manager: manager@erdcore.test
 * - Store staff: staff@erdcore.test
 * - Global admin: admin@erdcore.test
 */
class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            DemoStoreSeeder::class,
        ]);
    }
}
