<?php

namespace App\Services;

use App\Models\Store;
use App\Models\StoreOrderSequence;
use Illuminate\Support\Facades\DB;

class OrderNumberGenerator
{
    public function generate(Store $store): string
    {
        return DB::transaction(function () use ($store): string {
            StoreOrderSequence::query()->upsert(
                [[
                    'store_id' => $store->id,
                    'next_number' => 1001,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]],
                ['store_id'],
                ['updated_at']
            );

            $sequence = StoreOrderSequence::query()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->firstOrFail();

            $number = max(1001, (int) $sequence->next_number);

            $sequence->forceFill([
                'next_number' => $number + 1,
            ])->save();

            return '#'.$number;
        });
    }
}
