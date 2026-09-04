<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_variant_images')) {
            Schema::create('product_variant_images', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
                $table->foreignId('product_image_id')->constrained('product_images')->cascadeOnDelete();
                $table->unsignedInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['product_variant_id', 'product_image_id']);
                $table->index(['product_image_id', 'sort_order']);
            });
        }

        if (Schema::hasColumn('product_variants', 'product_image_id')) {
            $now = now();
            DB::table('product_variants')
                ->whereNotNull('product_image_id')
                ->orderBy('id')
                ->chunkById(250, function ($rows) use ($now): void {
                    foreach ($rows as $variant) {
                        $exists = DB::table('product_variant_images')
                            ->where('product_variant_id', $variant->id)
                            ->where('product_image_id', $variant->product_image_id)
                            ->exists();
                        if ($exists) {
                            continue;
                        }

                        DB::table('product_variant_images')->insert([
                            'product_variant_id' => $variant->id,
                            'product_image_id' => $variant->product_image_id,
                            'sort_order' => 0,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
        }

        if (Schema::hasColumn('product_images', 'product_variant_id')) {
            $now = now();
            DB::table('product_images')
                ->whereNotNull('product_variant_id')
                ->orderBy('id')
                ->chunkById(250, function ($rows) use ($now): void {
                    foreach ($rows as $image) {
                        $exists = DB::table('product_variant_images')
                            ->where('product_variant_id', $image->product_variant_id)
                            ->where('product_image_id', $image->id)
                            ->exists();
                        if ($exists) {
                            continue;
                        }

                        $nextSort = (int) DB::table('product_variant_images')
                            ->where('product_variant_id', $image->product_variant_id)
                            ->max('sort_order');

                        DB::table('product_variant_images')->insert([
                            'product_variant_id' => $image->product_variant_id,
                            'product_image_id' => $image->id,
                            'sort_order' => $nextSort + 1,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ]);
                    }
                });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_images');
    }
};
