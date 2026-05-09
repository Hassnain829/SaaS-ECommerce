<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table): void {
            $table->timestamp('blocked_at')->nullable()->after('status');
            $table->text('blocked_reason')->nullable()->after('blocked_at');
            $table->boolean('marketing_consent')->default(false)->after('accepts_marketing');
            $table->timestamp('marketing_consent_at')->nullable()->after('marketing_consent');
            $table->string('marketing_consent_source')->nullable()->after('marketing_consent_at');
        });

        Schema::create('customer_notes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['store_id', 'customer_id']);
            $table->index('created_at');
        });

        Schema::create('customer_tags', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('color')->nullable();
            $table->timestamps();

            $table->unique(['store_id', 'slug']);
            $table->index('store_id');
        });

        Schema::create('customer_customer_tag', function (Blueprint $table): void {
            $table->foreignId('customer_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->primary(['customer_id', 'customer_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_customer_tag');
        Schema::dropIfExists('customer_tags');
        Schema::dropIfExists('customer_notes');

        Schema::table('customers', function (Blueprint $table): void {
            $table->dropColumn([
                'blocked_at',
                'blocked_reason',
                'marketing_consent',
                'marketing_consent_at',
                'marketing_consent_source',
            ]);
        });
    }
};
