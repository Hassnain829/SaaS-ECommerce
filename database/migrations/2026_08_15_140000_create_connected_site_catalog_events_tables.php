<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connected_sites', function (Blueprint $table) {
            if (! Schema::hasColumn('connected_sites', 'event_signing_secret')) {
                $table->text('event_signing_secret')->nullable()->after('credential_hash');
            }
        });

        Schema::create('connected_site_outbox_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('public_id', 40)->unique();
            $table->string('type', 64);
            $table->json('payload');
            $table->string('catalog_version', 80);
            $table->timestamp('occurred_at');
            $table->timestamps();

            $table->index(['store_id', 'id']);
            $table->index(['store_id', 'type']);
        });

        Schema::create('connected_site_event_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('connected_site_id')->constrained('connected_sites')->cascadeOnDelete();
            $table->foreignId('outbox_event_id')->constrained('connected_site_outbox_events')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->unsignedInteger('attempt_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->unsignedSmallInteger('last_http_status')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();

            $table->unique(['connected_site_id', 'outbox_event_id'], 'cs_event_deliveries_site_event_unique');
            $table->index(['status', 'next_retry_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_site_event_deliveries');
        Schema::dropIfExists('connected_site_outbox_events');

        Schema::table('connected_sites', function (Blueprint $table) {
            if (Schema::hasColumn('connected_sites', 'event_signing_secret')) {
                $table->dropColumn('event_signing_secret');
            }
        });
    }
};
