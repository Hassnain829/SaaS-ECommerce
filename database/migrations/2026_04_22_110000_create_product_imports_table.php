<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bulk catalog import tracking (Sprint 2 Day 12).
     */
    public function up(): void
    {
        Schema::create('product_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('original_filename');
            $table->string('stored_disk', 32)->default('local');
            $table->string('stored_path');
            $table->string('mime_type')->nullable();
            $table->string('file_extension', 12);
            $table->string('status', 32)->default('uploaded');
            $table->json('headers')->nullable();
            $table->json('column_mapping')->nullable();
            $table->json('preview_summary')->nullable();
            $table->json('result_summary')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_imports');
    }
};
