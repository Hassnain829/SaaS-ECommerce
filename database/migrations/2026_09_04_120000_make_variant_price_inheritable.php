<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Variant price becomes an override instead of a copy.
 *
 * NULL now means "use the product's base price", matching how shipping weight
 * already resolves (variant -> product -> store fallback). Variants that were
 * only ever holding a copy of the base price are backfilled to NULL so that
 * editing the base price flows through to them; variants priced differently
 * keep their number as a deliberate override.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->nullable()->default(null)->change();
        });

        // Collected first, then updated by id: MySQL refuses a subquery that
        // selects from the same table an UPDATE targets, and SQLite has no
        // UPDATE ... JOIN. Chunking keeps this safe on large catalogs too.
        DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereColumn('product_variants.price', 'products.base_price')
            ->pluck('product_variants.id')
            ->chunk(500)
            ->each(function ($ids): void {
                DB::table('product_variants')->whereIn('id', $ids->all())->update(['price' => null]);
            });
    }

    public function down(): void
    {
        // Put the resolved price back on every row before the column stops being nullable.
        DB::table('product_variants')
            ->whereNull('price')
            ->update([
                'price' => DB::raw('(select base_price from products where products.id = product_variants.product_id)'),
            ]);

        DB::table('product_variants')->whereNull('price')->update(['price' => 0]);

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->decimal('price', 12, 2)->default(0)->nullable(false)->change();
        });
    }
};
