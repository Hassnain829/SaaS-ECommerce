<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_site_cutovers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('connected_site_id')->nullable()->constrained('connected_sites')->nullOnDelete();
            $table->string('status', 32)->default('draft');
            $this->nullableUserForeign($table, 'started_by', 'csc_started_by_fk');
            $this->nullableUserForeign($table, 'activated_by', 'csc_activated_by_fk');
            $this->nullableUserForeign($table, 'rolled_back_by', 'csc_rolled_back_by_fk');
            $table->timestamp('backup_acknowledged_at')->nullable();
            $this->nullableUserForeign($table, 'backup_acknowledged_by', 'csc_backup_ack_by_fk');
            $table->timestamp('import_exceptions_acknowledged_at')->nullable();
            $this->nullableUserForeign($table, 'import_exceptions_acknowledged_by', 'csc_import_exc_ack_by_fk');
            $table->timestamp('tax_off_acknowledged_at')->nullable();
            $this->nullableUserForeign($table, 'tax_off_acknowledged_by', 'csc_tax_off_ack_by_fk');
            $table->timestamp('external_cache_acknowledged_at')->nullable();
            $this->nullableUserForeign($table, 'external_cache_acknowledged_by', 'csc_cache_ack_by_fk');
            $table->timestamp('rollback_acknowledged_at')->nullable();
            $this->nullableUserForeign($table, 'rollback_acknowledged_by', 'csc_rollback_ack_by_fk');
            $table->timestamp('woo_archive_acknowledged_at')->nullable();
            $this->nullableUserForeign($table, 'woo_archive_acknowledged_by', 'csc_woo_archive_ack_by_fk');
            $table->unsignedBigInteger('smoke_checkout_id')->nullable();
            $table->unsignedBigInteger('smoke_order_id')->nullable();
            $table->timestamp('last_verified_at')->nullable();
            $table->timestamp('activation_requested_at')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('rolled_back_at')->nullable();
            $table->json('verification_snapshot')->nullable();
            $table->timestamps();

            $table->unique('store_id');
            $table->index(['store_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_site_cutovers');
    }

    private function nullableUserForeign(Blueprint $table, string $column, string $constraint): void
    {
        $table->foreignId($column)->nullable();
        $table->foreign($column, $constraint)
            ->references('id')
            ->on('users')
            ->nullOnDelete();
    }
};
