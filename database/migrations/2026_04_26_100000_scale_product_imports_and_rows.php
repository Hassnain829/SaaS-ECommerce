<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_imports', function (Blueprint $table) {
            if (! Schema::hasColumn('product_imports', 'last_processed_row')) {
                $table->unsignedBigInteger('last_processed_row')->default(0)->after('queued_at');
            }
            if (! Schema::hasColumn('product_imports', 'total_rows')) {
                $table->unsignedInteger('total_rows')->nullable()->after('last_processed_row');
            }
            if (! Schema::hasColumn('product_imports', 'import_state')) {
                $table->json('import_state')->nullable()->after('result_summary');
            }
        });

        if (! Schema::hasTable('product_import_rows')) {
            Schema::create('product_import_rows', function (Blueprint $table) {
                $table->id();
                $table->foreignId('product_import_id')->constrained('product_imports')->cascadeOnDelete();
                $table->unsignedInteger('row_number');
                $table->string('status', 32)->default('pending');
                $table->text('error_message')->nullable();
                $table->json('payload')->nullable();
                $table->timestamps();

                $table->unique(['product_import_id', 'row_number']);
                $table->index(['product_import_id', 'status']);
            });
        }

        Schema::table('products', function (Blueprint $table) {
            if (! Schema::hasColumn('products', 'deleted_at')) {
                $table->softDeletes();
            }
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            if (Schema::hasColumn('products', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
        });

        Schema::dropIfExists('product_import_rows');

        Schema::table('product_imports', function (Blueprint $table) {
            if (Schema::hasColumn('product_imports', 'import_state')) {
                $table->dropColumn('import_state');
            }
            if (Schema::hasColumn('product_imports', 'total_rows')) {
                $table->dropColumn('total_rows');
            }
            if (Schema::hasColumn('product_imports', 'last_processed_row')) {
                $table->dropColumn('last_processed_row');
            }
        });
    }
};
