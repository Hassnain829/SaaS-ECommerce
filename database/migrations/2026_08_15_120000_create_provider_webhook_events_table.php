<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_webhook_events', function (Blueprint $table): void {
            $table->id();
            $table->string('provider', 40);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 120);
            $table->string('provider_intent_id', 191)->nullable();
            $table->string('status', 32)->default('processing');
            $table->json('payload')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->string('skip_reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'provider_webhook_events_unique');
            $table->index(['provider', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_events');
    }
};
