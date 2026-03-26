<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE `products` MODIFY `product_type` VARCHAR(80) NOT NULL DEFAULT 'physical'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE `products` MODIFY `product_type` ENUM('physical', 'digital', 'service', 'subscription', 'virtual') NOT NULL DEFAULT 'physical'");
    }
};
