<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'product_image_id')) {
                $table->foreignId('product_image_id')
                    ->nullable()
                    ->after('compare_at_price')
                    ->constrained('product_images')
                    ->nullOnDelete();
                $table->index(['product_id', 'product_image_id']);
            }
        });

        // Backfill: each image could only point at one variant before. Copy that link onto the variant.
        if (Schema::hasColumn('product_images', 'product_variant_id')
            && Schema::hasColumn('product_variants', 'product_image_id')) {
            $rows = DB::table('product_images')
                ->whereNotNull('product_variant_id')
                ->select(['id', 'product_id', 'product_variant_id'])
                ->get();

            foreach ($rows as $row) {
                DB::table('product_variants')
                    ->where('id', $row->product_variant_id)
                    ->where('product_id', $row->product_id)
                    ->whereNull('product_image_id')
                    ->update(['product_image_id' => $row->id]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'product_image_id')) {
                $table->dropForeign(['product_image_id']);
                $table->dropColumn('product_image_id');
            }
        });
    }
};
