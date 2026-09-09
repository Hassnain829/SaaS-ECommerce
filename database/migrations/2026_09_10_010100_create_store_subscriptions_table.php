<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('store_subscriptions')) {
            return;
        }

        Schema::create('store_subscriptions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained('stores')->cascadeOnDelete();
            $table->foreignId('package_id')->nullable()->constrained('saas_packages')->nullOnDelete();
            $table->string('status', 32)->default('trial');
            $table->unsignedInteger('trial_days')->nullable();
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('access_ends_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('store_id');
            $table->index('status');
            $table->index('access_ends_at');
            $table->index('trial_ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_subscriptions');
    }
};
