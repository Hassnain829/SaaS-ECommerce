<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Append-only stock ledger must survive catalog product deletion.
 * - product_id: CASCADE → SET NULL
 * - identity snapshots for readable history after catalog purge
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        if (Schema::hasColumn('stock_movements', 'product_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $this->dropForeignCompat($table, 'stock_movements_product_id_foreign', ['product_id']);
            });

            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->nullOnDelete();
            });
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            if (! Schema::hasColumn('stock_movements', 'product_name_snapshot')) {
                $table->string('product_name_snapshot')->nullable()->after('variant_id');
            }
            if (! Schema::hasColumn('stock_movements', 'sku_snapshot')) {
                $table->string('sku_snapshot')->nullable()->after('product_name_snapshot');
            }
            if (! Schema::hasColumn('stock_movements', 'variant_label_snapshot')) {
                $table->string('variant_label_snapshot')->nullable()->after('sku_snapshot');
            }
        });

        $this->backfillSnapshots();
    }

    public function down(): void
    {
        if (! Schema::hasTable('stock_movements')) {
            return;
        }

        Schema::table('stock_movements', function (Blueprint $table): void {
            foreach (['product_name_snapshot', 'sku_snapshot', 'variant_label_snapshot'] as $column) {
                if (Schema::hasColumn('stock_movements', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        if (Schema::hasColumn('stock_movements', 'product_id')) {
            Schema::table('stock_movements', function (Blueprint $table): void {
                $this->dropForeignCompat($table, 'stock_movements_product_id_foreign', ['product_id']);
            });

            Schema::table('stock_movements', function (Blueprint $table): void {
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->cascadeOnDelete();
            });
        }
    }

    private function backfillSnapshots(): void
    {
        if (! Schema::hasColumn('stock_movements', 'product_name_snapshot')) {
            return;
        }

        $lastId = 0;
        do {
            $rows = DB::table('stock_movements')
                ->where('id', '>', $lastId)
                ->whereNotNull('product_id')
                ->where(function ($query): void {
                    $query->whereNull('product_name_snapshot')
                        ->orWhereNull('sku_snapshot');
                })
                ->orderBy('id')
                ->limit(500)
                ->get(['id', 'product_id', 'variant_id']);

            if ($rows->isEmpty()) {
                break;
            }

            $productIds = $rows->pluck('product_id')->unique()->filter()->values()->all();
            $variantIds = $rows->pluck('variant_id')->unique()->filter()->values()->all();

            $products = DB::table('products')
                ->whereIn('id', $productIds)
                ->get(['id', 'name', 'sku'])
                ->keyBy('id');

            $variants = $variantIds === []
                ? collect()
                : DB::table('product_variants')
                    ->whereIn('id', $variantIds)
                    ->get(['id', 'sku'])
                    ->keyBy('id');

            foreach ($rows as $row) {
                $product = $products->get($row->product_id);
                if (! $product) {
                    $lastId = (int) $row->id;

                    continue;
                }

                $variant = $row->variant_id ? $variants->get($row->variant_id) : null;
                $sku = $variant && filled($variant->sku) ? (string) $variant->sku : (string) ($product->sku ?? '');

                DB::table('stock_movements')
                    ->where('id', $row->id)
                    ->update([
                        'product_name_snapshot' => (string) $product->name,
                        'sku_snapshot' => $sku !== '' ? $sku : null,
                        // Variant labels require option joins; leave null rather than inventing.
                        'variant_label_snapshot' => null,
                    ]);

                $lastId = (int) $row->id;
            }
        } while ($rows->count() === 500);
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignCompat(Blueprint $table, string $fkName, array $columns): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $table->dropForeign($columns);

            return;
        }

        $table->dropForeign($fkName);
    }
};
