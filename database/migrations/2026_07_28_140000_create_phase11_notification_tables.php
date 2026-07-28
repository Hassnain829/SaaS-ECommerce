<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 100);
            $table->string('channel', 32);
            $table->string('title', 200);
            $table->text('body')->nullable();
            $table->string('status', 32)->default('queued');
            $table->json('data')->nullable();
            $table->string('dedupe_key', 190);
            $table->string('recipient_key', 120);
            $table->string('recipient_email')->nullable();
            $table->boolean('is_read')->default(false);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['store_id', 'type', 'channel', 'dedupe_key', 'recipient_key'],
                'notifications_store_type_channel_dedupe_recipient_unique'
            );
            $table->index(['store_id', 'user_id', 'is_read', 'created_at'], 'notifications_store_user_unread_idx');
            $table->index(['store_id', 'type', 'created_at'], 'notifications_store_type_idx');
            $table->index(['status', 'channel'], 'notifications_status_channel_idx');
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('channel', 32);
            $table->boolean('is_enabled')->default(true);
            $table->json('event_types')->nullable();
            $table->json('settings')->nullable();
            $table->json('quiet_hours')->nullable();
            $table->string('locale', 16)->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['store_id', 'user_id', 'channel'], 'notification_prefs_store_user_channel_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notifications');
    }
};
