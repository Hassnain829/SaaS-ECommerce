<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills nullable checkout_id + order unique for databases that already ran the
 * original coupons migration before order-only external redemptions were supported.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('coupon_redemptions')) {
            return;
        }

        $indexNames = collect(Schema::getConnection()->getSchemaBuilder()->getIndexes('coupon_redemptions'))
            ->pluck('name')
            ->all();

        $checkoutColumn = collect(Schema::getConnection()->getSchemaBuilder()->getColumns('coupon_redemptions'))
            ->firstWhere('name', 'checkout_id');

        if ($checkoutColumn && ! ($checkoutColumn['nullable'] ?? false)) {
            Schema::table('coupon_redemptions', function (Blueprint $table) use ($indexNames): void {
                if (in_array('coupon_redemptions_checkout_unique', $indexNames, true)) {
                    $table->dropUnique('coupon_redemptions_checkout_unique');
                }
                $table->dropForeign(['checkout_id']);
            });

            Schema::table('coupon_redemptions', function (Blueprint $table): void {
                $table->unsignedBigInteger('checkout_id')->nullable()->change();
                $table->unique('checkout_id', 'coupon_redemptions_checkout_unique');
                $table->foreign('checkout_id')->references('id')->on('checkouts')->cascadeOnDelete();
            });

            $indexNames = collect(Schema::getConnection()->getSchemaBuilder()->getIndexes('coupon_redemptions'))
                ->pluck('name')
                ->all();
        }

        if (! in_array('coupon_redemptions_order_unique', $indexNames, true)) {
            Schema::table('coupon_redemptions', function (Blueprint $table): void {
                $table->unique('order_id', 'coupon_redemptions_order_unique');
            });
        }
    }

    public function down(): void
    {
        // Keep nullable checkout_id; removing it would break order-only redemptions.
    }
};
