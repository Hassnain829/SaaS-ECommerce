<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->uuid('claim_token')->nullable()->after('request_hash');
            $table->timestamp('completed_at')->nullable()->after('resource_id');
        });
    }

    public function down(): void
    {
        Schema::table('idempotency_keys', function (Blueprint $table): void {
            $table->dropColumn(['claim_token', 'completed_at']);
        });
    }
};
