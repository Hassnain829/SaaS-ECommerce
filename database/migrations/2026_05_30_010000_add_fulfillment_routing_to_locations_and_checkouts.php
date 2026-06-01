<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('locations')) {
            Schema::table('locations', function (Blueprint $table): void {
                if (! Schema::hasColumn('locations', 'fulfills_online_orders')) {
                    $table->boolean('fulfills_online_orders')->default(true)->after('is_active');
                }

                if (! Schema::hasColumn('locations', 'pickup_enabled')) {
                    $table->boolean('pickup_enabled')->default(false)->after('fulfills_online_orders');
                }

                if (! Schema::hasColumn('locations', 'routing_priority')) {
                    $table->unsignedInteger('routing_priority')->default(100)->after('pickup_enabled');
                }

                if (! Schema::hasColumn('locations', 'service_countries')) {
                    $table->json('service_countries')->nullable()->after('routing_priority');
                }

                if (! Schema::hasColumn('locations', 'service_regions')) {
                    $table->json('service_regions')->nullable()->after('service_countries');
                }

                if (! Schema::hasColumn('locations', 'service_postal_patterns')) {
                    $table->json('service_postal_patterns')->nullable()->after('service_regions');
                }
            });
        }

        if (Schema::hasTable('checkouts')) {
            Schema::table('checkouts', function (Blueprint $table): void {
                if (! Schema::hasColumn('checkouts', 'fulfillment_origin_location_id')) {
                    $table->foreignId('fulfillment_origin_location_id')
                        ->nullable()
                        ->after('shipping_snapshot')
                        ->constrained('locations')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('checkouts', 'pickup_location_id')) {
                    $table->foreignId('pickup_location_id')
                        ->nullable()
                        ->after('fulfillment_origin_location_id')
                        ->constrained('locations')
                        ->nullOnDelete();
                }

                if (! Schema::hasColumn('checkouts', 'fulfillment_routing_snapshot')) {
                    $table->json('fulfillment_routing_snapshot')->nullable()->after('pickup_location_id');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('checkouts')) {
            Schema::table('checkouts', function (Blueprint $table): void {
                if (Schema::hasColumn('checkouts', 'fulfillment_origin_location_id')) {
                    $table->dropConstrainedForeignId('fulfillment_origin_location_id');
                }

                if (Schema::hasColumn('checkouts', 'pickup_location_id')) {
                    $table->dropConstrainedForeignId('pickup_location_id');
                }

                if (Schema::hasColumn('checkouts', 'fulfillment_routing_snapshot')) {
                    $table->dropColumn('fulfillment_routing_snapshot');
                }
            });
        }

        if (Schema::hasTable('locations')) {
            Schema::table('locations', function (Blueprint $table): void {
                foreach ([
                    'service_postal_patterns',
                    'service_regions',
                    'service_countries',
                    'routing_priority',
                    'pickup_enabled',
                    'fulfills_online_orders',
                ] as $column) {
                    if (Schema::hasColumn('locations', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
