<?php

namespace Database\Seeders;

use App\Models\Store;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class StoreMembershipSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $now = now();

        Store::query()
            ->whereNotNull('user_id')
            ->select(['id', 'user_id'])
            ->chunkById(100, function ($stores) use ($now): void {
                $rows = $stores->map(function (Store $store) use ($now): array {
                    return [
                        'store_id' => $store->id,
                        'user_id' => $store->user_id,
                        'role' => 'owner',
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                })->all();

                DB::table('store_user')->upsert(
                    $rows,
                    ['store_id', 'user_id'],
                    ['role', 'updated_at']
                );
            });
    }
}
