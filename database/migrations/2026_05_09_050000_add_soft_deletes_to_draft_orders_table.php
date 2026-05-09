<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('draft_orders') || Schema::hasColumn('draft_orders', 'deleted_at')) {
            return;
        }

        Schema::table('draft_orders', function (Blueprint $table): void {
            $table->softDeletes()->after('updated_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('draft_orders') || ! Schema::hasColumn('draft_orders', 'deleted_at')) {
            return;
        }

        Schema::table('draft_orders', function (Blueprint $table): void {
            $table->dropSoftDeletes();
        });
    }
};
