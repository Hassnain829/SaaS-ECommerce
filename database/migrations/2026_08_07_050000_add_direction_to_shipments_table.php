<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (! Schema::hasColumn('shipments', 'direction')) {
                $table->string('direction', 16)->default('outbound')->after('status');
                $table->index(['store_id', 'order_id', 'direction']);
            }
        });

        // Backfill return shipments previously flagged only in metadata.
        if (Schema::hasColumn('shipments', 'direction') && Schema::hasColumn('shipments', 'metadata')) {
            $driver = Schema::getConnection()->getDriverName();
            if ($driver === 'mysql') {
                DB::statement("UPDATE shipments SET direction = 'return' WHERE JSON_EXTRACT(metadata, '$.fedex.return_shipment') = true");
            } elseif ($driver === 'pgsql') {
                DB::statement("UPDATE shipments SET direction = 'return' WHERE (metadata->'fedex'->>'return_shipment') = 'true'");
            } else {
                // sqlite / others: update in PHP
                DB::table('shipments')
                    ->orderBy('id')
                    ->chunkById(100, function ($rows): void {
                        foreach ($rows as $row) {
                            $meta = json_decode((string) ($row->metadata ?? ''), true);
                            if (is_array($meta) && ! empty($meta['fedex']['return_shipment'])) {
                                DB::table('shipments')->where('id', $row->id)->update(['direction' => 'return']);
                            }
                        }
                    });
            }
        }
    }

    public function down(): void
    {
        Schema::table('shipments', function (Blueprint $table): void {
            if (Schema::hasColumn('shipments', 'direction')) {
                $table->dropIndex(['store_id', 'order_id', 'direction']);
                $table->dropColumn('direction');
            }
        });
    }
};
