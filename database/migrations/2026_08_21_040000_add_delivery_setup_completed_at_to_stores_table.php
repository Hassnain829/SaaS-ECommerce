<?php

use App\Models\Store;
use App\Services\Delivery\DeliverySetupLifecycleService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (! Schema::hasColumn('stores', 'delivery_setup_completed_at')) {
                $table->timestamp('delivery_setup_completed_at')->nullable()->after('onboarding_completed');
            }
        });

        // Existing stores that already satisfy operational readiness are treated as
        // previously completed — never leave them looking like first-time merchants,
        // and never mark unconfigured stores as completed.
        if (Schema::hasColumn('stores', 'delivery_setup_completed_at')) {
            app(DeliverySetupLifecycleService::class)->backfillCompletedAtForReadyStores();
        }
    }

    public function down(): void
    {
        Schema::table('stores', function (Blueprint $table) {
            if (Schema::hasColumn('stores', 'delivery_setup_completed_at')) {
                $table->dropColumn('delivery_setup_completed_at');
            }
        });
    }
};
