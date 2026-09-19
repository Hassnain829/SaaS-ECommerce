<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_user', function (Blueprint $table) {
            $table->string('job_title', 80)->nullable()->after('role');
            $table->string('access_preset', 40)->nullable()->after('job_title');
            $table->json('location_ids')->nullable()->after('access_preset');
            $table->string('status', 20)->default('active')->after('location_ids');
        });

        Schema::create('store_member_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('permission', 80);
            $table->timestamps();

            $table->unique(['store_id', 'user_id', 'permission'], 'store_member_permissions_unique');
            $table->index(['store_id', 'user_id'], 'store_member_permissions_member_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('store_member_permissions');

        Schema::table('store_user', function (Blueprint $table) {
            $table->dropColumn(['job_title', 'access_preset', 'location_ids', 'status']);
        });
    }
};
