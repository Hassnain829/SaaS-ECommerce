<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const REPLACED_BY_FK = 'ca_replaced_by_carrier_account_fk';

    private const REPLACING_FK = 'cars_replacing_carrier_account_fk';

    public function up(): void
    {
        if (Schema::hasTable('carrier_accounts')) {
            Schema::table('carrier_accounts', function (Blueprint $table): void {
                if (! Schema::hasColumn('carrier_accounts', 'disconnected_at')) {
                    $table->timestamp('disconnected_at')->nullable()->after('last_error_message');
                }
                if (! Schema::hasColumn('carrier_accounts', 'replaced_at')) {
                    $table->timestamp('replaced_at')->nullable()->after('disconnected_at');
                }
                if (! Schema::hasColumn('carrier_accounts', 'replaced_by_carrier_account_id')) {
                    $table->unsignedBigInteger('replaced_by_carrier_account_id')
                        ->nullable()
                        ->after('replaced_at');
                    $table->foreign('replaced_by_carrier_account_id', self::REPLACED_BY_FK)
                        ->references('id')
                        ->on('carrier_accounts')
                        ->nullOnDelete();
                }
            });
        }

        if (Schema::hasTable('carrier_account_registration_sessions')) {
            Schema::table('carrier_account_registration_sessions', function (Blueprint $table): void {
                if (! Schema::hasColumn('carrier_account_registration_sessions', 'replacing_carrier_account_id')) {
                    $table->unsignedBigInteger('replacing_carrier_account_id')
                        ->nullable()
                        ->after('carrier_account_id');
                    // MySQL identifier limit is 64 chars; default Laravel name exceeds it.
                    $table->foreign('replacing_carrier_account_id', self::REPLACING_FK)
                        ->references('id')
                        ->on('carrier_accounts')
                        ->nullOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        if (
            Schema::hasTable('carrier_account_registration_sessions')
            && Schema::hasColumn('carrier_account_registration_sessions', 'replacing_carrier_account_id')
        ) {
            Schema::table('carrier_account_registration_sessions', function (Blueprint $table): void {
                $this->dropForeignCompat($table, self::REPLACING_FK, ['replacing_carrier_account_id']);
                $table->dropColumn('replacing_carrier_account_id');
            });
        }

        if (Schema::hasTable('carrier_accounts')) {
            Schema::table('carrier_accounts', function (Blueprint $table): void {
                if (Schema::hasColumn('carrier_accounts', 'replaced_by_carrier_account_id')) {
                    $this->dropForeignCompat($table, self::REPLACED_BY_FK, ['replaced_by_carrier_account_id']);
                    $table->dropColumn('replaced_by_carrier_account_id');
                }
                foreach (['replaced_at', 'disconnected_at'] as $column) {
                    if (Schema::hasColumn('carrier_accounts', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }

    /**
     * MySQL must drop by the short custom FK name; SQLite only supports drop-by-columns.
     *
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
