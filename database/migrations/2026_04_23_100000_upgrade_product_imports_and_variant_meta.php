<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('product_imports', 'custom_field_mappings')) {
                $table->json('custom_field_mappings')->nullable()->after('column_mapping');
            }
            if (! Schema::hasColumn('product_imports', 'queued_at')) {
                $table->timestamp('queued_at')->nullable()->after('started_at');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (! Schema::hasColumn('product_variants', 'meta')) {
                $table->json('meta')->nullable()->after('image');
            }
        });
    }

    public function down(): void
    {
        Schema::table('product_imports', function (Blueprint $table) {
            if (Schema::hasColumn('product_imports', 'custom_field_mappings')) {
                $table->dropColumn('custom_field_mappings');
            }
            if (Schema::hasColumn('product_imports', 'queued_at')) {
                $table->dropColumn('queued_at');
            }
        });

        Schema::table('product_variants', function (Blueprint $table) {
            if (Schema::hasColumn('product_variants', 'meta')) {
                $table->dropColumn('meta');
            }
        });
    }
};
