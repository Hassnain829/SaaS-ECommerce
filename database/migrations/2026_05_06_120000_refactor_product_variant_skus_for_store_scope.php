<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_variants', 'store_id')) {
                $table->foreignId('store_id')
                    ->nullable()
                    ->after('id')
                    ->constrained('stores')
                    ->cascadeOnDelete();
            }
        });

        DB::table('product_variants')
            ->whereNull('store_id')
            ->orderBy('id')
            ->chunkById(500, function ($variants): void {
                $storeIdsByProduct = DB::table('products')
                    ->whereIn('id', $variants->pluck('product_id')->filter()->unique()->values())
                    ->pluck('store_id', 'id');

                foreach ($variants as $variant) {
                    $storeId = $storeIdsByProduct[$variant->product_id] ?? null;
                    if ($storeId !== null) {
                        DB::table('product_variants')
                            ->where('id', $variant->id)
                            ->update(['store_id' => $storeId]);
                    }
                }
            });

        Schema::table('product_variants', function (Blueprint $table): void {
            if (Schema::hasIndex('product_variants', 'product_variants_sku_unique')) {
                $table->dropUnique('product_variants_sku_unique');
            }
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            if (! Schema::hasIndex('product_variants', 'product_variants_store_id_index')) {
                $table->index('store_id', 'product_variants_store_id_index');
            }

            if (! Schema::hasIndex('product_variants', 'product_variants_store_id_sku_unique')) {
                $table->unique(['store_id', 'sku'], 'product_variants_store_id_sku_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            if (Schema::hasIndex('product_variants', 'product_variants_store_id_sku_unique')) {
                $table->dropUnique('product_variants_store_id_sku_unique');
            }
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            if (Schema::hasColumn('product_variants', 'store_id')) {
                $table->dropForeign(['store_id']);
            }
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            if (Schema::hasIndex('product_variants', 'product_variants_store_id_index')) {
                $table->dropIndex('product_variants_store_id_index');
            }
        });

        $hasDuplicateSkus = DB::table('product_variants')
            ->select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        Schema::table('product_variants', function (Blueprint $table) use ($hasDuplicateSkus): void {
            if (Schema::hasColumn('product_variants', 'store_id')) {
                $table->dropColumn('store_id');
            }

            if (! $hasDuplicateSkus && ! Schema::hasIndex('product_variants', 'product_variants_sku_unique')) {
                $table->unique('sku', 'product_variants_sku_unique');
            }
        });
    }
};
