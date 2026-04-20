<?php

use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Product::query()->orderBy('id')->chunkById(100, function ($products): void {
            foreach ($products as $product) {
                $meta = is_array($product->meta) ? $product->meta : [];
                $paths = collect($meta['image_paths'] ?? [])
                    ->merge(array_filter([$meta['image_path'] ?? null]))
                    ->filter()
                    ->unique()
                    ->values();

                if ($paths->isEmpty()) {
                    continue;
                }

                if ($product->images()->exists()) {
                    continue;
                }

                DB::transaction(function () use ($product, $paths, $meta): void {
                    foreach ($paths as $index => $path) {
                        ProductImage::query()->create([
                            'product_id' => $product->id,
                            'image_path' => (string) $path,
                            'alt_text' => null,
                            'sort_order' => (int) $index,
                            'is_primary' => $index === 0,
                            'created_by' => null,
                            'updated_by' => null,
                        ]);
                    }

                    unset($meta['image_path'], $meta['image_paths']);
                    $product->update(['meta' => $meta]);
                });
            }
        });
    }

    public function down(): void
    {
        // Non-destructive: do not restore meta keys from rows (paths may have moved).
    }
};
