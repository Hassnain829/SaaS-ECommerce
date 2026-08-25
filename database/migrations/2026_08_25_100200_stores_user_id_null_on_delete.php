<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * stores.user_id is a legacy creator pointer — never cascade-delete a store when a user is removed.
 * Authorization remains store_user membership.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stores') || ! Schema::hasColumn('stores', 'user_id')) {
            return;
        }

        Schema::table('stores', function (Blueprint $table): void {
            $this->dropForeignCompat($table, 'stores_user_id_foreign', ['user_id']);
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable()->change();
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('stores') || ! Schema::hasColumn('stores', 'user_id')) {
            return;
        }

        // Cannot safely restore NOT NULL + CASCADE if any store.user_id is already NULL.
        if (DB::table('stores')->whereNull('user_id')->exists()) {
            throw new \RuntimeException(
                'Cannot safely roll back stores.user_id nullOnDelete: historical rows contain NULL user references.'
            );
        }

        Schema::table('stores', function (Blueprint $table): void {
            $this->dropForeignCompat($table, 'stores_user_id_foreign', ['user_id']);
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
        });

        Schema::table('stores', function (Blueprint $table): void {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }

    /**
     * @param  list<string>  $columns
     */
    private function dropForeignCompat(Blueprint $table, string $fkName, array $columns): void
    {
        if (Schema::getConnection()->getDriverName() === 'sqlite') {
            $table->dropForeign($columns);

            return;
        }

        $table->dropForeign($fkName);
    }
};
