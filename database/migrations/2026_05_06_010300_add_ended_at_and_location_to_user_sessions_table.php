<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_sessions', function (Blueprint $table): void {
            if (! Schema::hasColumn('user_sessions', 'location')) {
                $table->string('location')->nullable()->after('device_type');
            }

            if (! Schema::hasColumn('user_sessions', 'ended_at')) {
                $table->timestamp('ended_at')->nullable()->after('last_activity');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_sessions', function (Blueprint $table): void {
            $columns = [];

            if (Schema::hasColumn('user_sessions', 'ended_at')) {
                $columns[] = 'ended_at';
            }

            if (Schema::hasColumn('user_sessions', 'location')) {
                $columns[] = 'location';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
