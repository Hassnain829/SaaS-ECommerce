<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $this->renameDefaultedOrderColumnsForCommerce();

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('customer_id')->nullable()->after('store_id')->constrained()->nullOnDelete();
            $table->dropColumn('customer_name');
            $table->renameColumn('reference', 'order_number');
            $table->string('external_order_number')->nullable()->after('order_number');
            $table->string('fulfillment_status', 32)->default('unfulfilled')->after('status');
            $table->string('payment_status', 32)->default('pending')->after('fulfillment_status');
            $table->string('channel')->nullable()->after('order_source');
            $table->decimal('exchange_rate', 10, 6)->default(1.0)->after('currency_code');
            $table->integer('item_count')->default(0)->after('exchange_rate');
            $table->integer('total_quantity')->default(0)->after('item_count');
            $table->decimal('subtotal', 14, 2)->default(0)->after('total_quantity');
            $table->decimal('discount', 14, 2)->default(0)->after('subtotal');
            $table->decimal('discount_tax', 14, 2)->default(0)->after('discount');
            $table->decimal('shipping', 14, 2)->default(0)->after('discount_tax');
            $table->decimal('shipping_tax', 14, 2)->default(0)->after('shipping');
            $table->decimal('tax', 14, 2)->default(0)->after('shipping_tax');
            $table->decimal('grand_total', 14, 2)->default(0)->after('total');
            $table->decimal('refunded_total', 14, 2)->default(0)->after('grand_total');
            $table->decimal('outstanding_total', 14, 2)->default(0)->after('refunded_total');
            $table->string('payment_method')->nullable()->after('outstanding_total');
            $table->string('payment_gateway')->nullable()->after('payment_method');
            $table->string('payment_reference')->nullable()->after('payment_gateway');
            $table->string('transaction_id')->nullable()->after('payment_reference');
            $table->string('fraud_status')->nullable()->after('transaction_id');
            $table->string('invoice_status')->nullable()->after('fraud_status');
            $table->string('customer_phone')->nullable()->after('customer_email');
            $table->boolean('billing_same_as_shipping')->default(true)->after('customer_phone');
            $table->text('notes')->nullable()->after('billing_same_as_shipping');
            $table->timestamp('placed_at')->nullable()->after('meta');
            $table->timestamp('confirmed_at')->nullable()->after('placed_at');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            $table->timestamp('closed_at')->nullable()->after('refunded_at');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->integer('refunded_quantity')->default(0)->after('quantity');
            $table->integer('returned_quantity')->default(0)->after('refunded_quantity');
            $table->decimal('subtotal', 14, 2)->default(0)->after('unit_price');
            $table->decimal('discount_amount', 14, 2)->default(0)->after('subtotal');
            $table->decimal('tax_amount', 14, 2)->default(0)->after('discount_amount');
            $table->renameColumn('line_total', 'total');
            $table->decimal('cost_price_snapshot', 14, 2)->nullable()->after('total');
            $table->decimal('weight_snapshot', 10, 3)->nullable()->after('cost_price_snapshot');
            $table->string('sku_snapshot')->nullable()->after('weight_snapshot');
            $table->string('barcode_snapshot')->nullable()->after('sku_snapshot');
            $table->string('product_slug_snapshot')->nullable()->after('product_name');
            $table->string('brand_name_snapshot')->nullable()->after('product_slug_snapshot');
            $table->string('product_image_snapshot')->nullable()->after('brand_name_snapshot');
            $table->string('product_type_snapshot')->nullable()->after('product_image_snapshot');
            $table->string('fulfillment_status', 32)->default('unfulfilled')->after('product_type_snapshot');
            $table->json('variant_details')->nullable()->after('fulfillment_status');
            $table->json('meta')->nullable()->after('variant_details');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $this->rollbackOrderItemColumns();
        $this->rollbackOrderColumns();
    }

    private function renameDefaultedOrderColumnsForCommerce(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'source') && ! Schema::hasColumn('orders', 'order_source')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `orders` CHANGE `source` `order_source` varchar(32) NOT NULL DEFAULT 'developer_storefront'");
            } else {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->renameColumn('source', 'order_source');
                });
            }
        }

        if (Schema::hasColumn('orders', 'currency') && ! Schema::hasColumn('orders', 'currency_code')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `orders` CHANGE `currency` `currency_code` varchar(8) NOT NULL DEFAULT 'USD'");
            } else {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->renameColumn('currency', 'currency_code');
                });
            }
        }
    }

    private function rollbackOrderItemColumns(): void
    {
        if (! Schema::hasTable('order_items')) {
            return;
        }

        $columnsToDrop = array_values(array_filter([
            'refunded_quantity',
            'returned_quantity',
            'subtotal',
            'discount_amount',
            'tax_amount',
            'cost_price_snapshot',
            'weight_snapshot',
            'sku_snapshot',
            'barcode_snapshot',
            'product_slug_snapshot',
            'brand_name_snapshot',
            'product_image_snapshot',
            'product_type_snapshot',
            'fulfillment_status',
            'variant_details',
            'meta',
        ], fn (string $column): bool => Schema::hasColumn('order_items', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('order_items', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }

        if (Schema::hasColumn('order_items', 'total') && ! Schema::hasColumn('order_items', 'line_total')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $table->renameColumn('total', 'line_total');
            });
        }
    }

    private function rollbackOrderColumns(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'customer_id')) {
                $table->dropForeign(['customer_id']);
            }

            if (Schema::hasColumn('orders', 'created_by')) {
                $table->dropForeign(['created_by']);
            }

            if (Schema::hasColumn('orders', 'updated_by')) {
                $table->dropForeign(['updated_by']);
            }
        });

        if (Schema::hasColumn('orders', 'order_number') && ! Schema::hasColumn('orders', 'reference')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->renameColumn('order_number', 'reference');
            });
        }

        if (Schema::hasColumn('orders', 'order_source') && ! Schema::hasColumn('orders', 'source')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `orders` CHANGE `order_source` `source` varchar(32) NOT NULL DEFAULT 'developer_storefront'");
            } else {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->renameColumn('order_source', 'source');
                });
            }
        }

        if (Schema::hasColumn('orders', 'currency_code') && ! Schema::hasColumn('orders', 'currency')) {
            if (DB::getDriverName() === 'mysql') {
                DB::statement("ALTER TABLE `orders` CHANGE `currency_code` `currency` varchar(8) NOT NULL DEFAULT 'USD'");
            } else {
                Schema::table('orders', function (Blueprint $table): void {
                    $table->renameColumn('currency_code', 'currency');
                });
            }
        }

        $columnsToDrop = array_values(array_filter([
            'customer_id',
            'external_order_number',
            'fulfillment_status',
            'payment_status',
            'channel',
            'exchange_rate',
            'item_count',
            'total_quantity',
            'subtotal',
            'discount',
            'discount_tax',
            'shipping',
            'shipping_tax',
            'tax',
            'grand_total',
            'refunded_total',
            'outstanding_total',
            'payment_method',
            'payment_gateway',
            'payment_reference',
            'transaction_id',
            'fraud_status',
            'invoice_status',
            'customer_phone',
            'billing_same_as_shipping',
            'notes',
            'placed_at',
            'confirmed_at',
            'cancelled_at',
            'refunded_at',
            'closed_at',
            'created_by',
            'updated_by',
            'deleted_at',
        ], fn (string $column): bool => Schema::hasColumn('orders', $column)));

        if ($columnsToDrop !== []) {
            Schema::table('orders', function (Blueprint $table) use ($columnsToDrop): void {
                $table->dropColumn($columnsToDrop);
            });
        }

        if (! Schema::hasColumn('orders', 'customer_name')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->string('customer_name')->nullable()->after('status');
            });
        }
    }
};
