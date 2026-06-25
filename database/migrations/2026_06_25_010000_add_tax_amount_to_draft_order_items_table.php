<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('draft_order_items') || Schema::hasColumn('draft_order_items', 'tax_amount')) {
            return;
        }

        Schema::table('draft_order_items', function (Blueprint $table): void {
            $table->decimal('tax_amount', 14, 2)->default(0)->after('line_total');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('draft_order_items') || ! Schema::hasColumn('draft_order_items', 'tax_amount')) {
            return;
        }

        Schema::table('draft_order_items', function (Blueprint $table): void {
            $table->dropColumn('tax_amount');
        });
    }
};
