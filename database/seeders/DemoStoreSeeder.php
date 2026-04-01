<?php

namespace Database\Seeders;

use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoStoreSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $merchant = User::query()
            ->where('email', 'user@erdcore.test')
            ->firstOrFail();
        $manager = User::query()
            ->where('email', 'manager@erdcore.test')
            ->firstOrFail();
        $staff = User::query()
            ->where('email', 'staff@erdcore.test')
            ->firstOrFail();

        $fashionStore = Store::query()->updateOrCreate(
            ['slug' => 'demo-fashion'],
            [
                'user_id' => $merchant->id,
                'name' => 'Demo Fashion',
                'logo' => null,
                'address' => 'Karachi, Pakistan',
                'currency' => 'PKR',
                'timezone' => 'Asia/Karachi',
                'category' => 'physical',
                'settings' => [
                    'primary_market' => 'South Asia',
                    'business_models' => ['Retail'],
                ],
                'onboarding_completed' => true,
            ]
        );

        $digitalStore = Store::query()->updateOrCreate(
            ['slug' => 'demo-digital'],
            [
                'user_id' => $merchant->id,
                'name' => 'Demo Digital',
                'logo' => null,
                'address' => 'Lahore, Pakistan',
                'currency' => 'USD',
                'timezone' => 'Asia/Karachi',
                'category' => 'digital',
                'settings' => [
                    'primary_market' => 'Global Market',
                    'business_models' => ['Digital Products'],
                ],
                'onboarding_completed' => true,
            ]
        );

        $fashionStore->members()->syncWithoutDetaching([
            $merchant->id => ['role' => Store::ROLE_OWNER],
            $manager->id => ['role' => Store::ROLE_MANAGER],
            $staff->id => ['role' => Store::ROLE_STAFF],
        ]);

        $digitalStore->members()->syncWithoutDetaching([
            $merchant->id => ['role' => Store::ROLE_OWNER],
        ]);
    }
}
