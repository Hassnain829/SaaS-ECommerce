<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Merchant "delete store" becomes soft closure. Final hard purge is a separate internal operation.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores') || Schema::hasColumn('stores', 'deleted_at')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table): void {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stores') || ! Schema::hasColumn('stores', 'deleted_at')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
