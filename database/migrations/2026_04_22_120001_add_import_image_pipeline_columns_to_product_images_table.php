<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->string('status', 20)->default('ready')->after('is_primary');
            $table->timestamp('processing_started_at')->nullable()->after('status');
            $table->timestamp('processed_at')->nullable()->after('processing_started_at');
            $table->text('failure_reason')->nullable()->after('processed_at');
            $table->text('source_url')->nullable()->after('failure_reason');
            $table->foreignId('product_import_id')->nullable()->after('source_url')
                ->constrained('product_imports')->nullOnDelete();
            $table->index(['product_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table) {
            $table->dropIndex(['product_id', 'status']);
            $table->dropConstrainedForeignId('product_import_id');
            $table->dropColumn([
                'status',
                'processing_started_at',
                'processed_at',
                'failure_reason',
                'source_url',
            ]);
        });
    }
};
