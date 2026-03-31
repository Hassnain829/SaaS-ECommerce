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

        Store::query()->updateOrCreate(
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

        Store::query()->updateOrCreate(
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

        $stores = Store::query()
            ->whereIn('slug', ['demo-fashion', 'demo-digital'])
            ->get();

        foreach ($stores as $store) {
            $store->members()->syncWithoutDetaching([
                $merchant->id => ['role' => 'owner'],
            ]);
        }
    }
}
