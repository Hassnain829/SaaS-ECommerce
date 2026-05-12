<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_provider_accounts', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_provider_accounts', 'provider_account_id')) {
                $table->string('provider_account_id')->nullable()->after('provider')->index('payment_provider_account_provider_account_index');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'capabilities')) {
                $table->json('capabilities')->nullable()->after('settings');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'last_verified_at')) {
                $table->timestamp('last_verified_at')->nullable()->after('metadata');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable()->after('last_verified_at')->index('payment_provider_account_created_by_index');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'onboarding_completed_at')) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('created_by');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'charges_enabled')) {
                $table->boolean('charges_enabled')->default(false)->after('onboarding_completed_at');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'payouts_enabled')) {
                $table->boolean('payouts_enabled')->default(false)->after('charges_enabled');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'requirements_currently_due')) {
                $table->json('requirements_currently_due')->nullable()->after('payouts_enabled');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'requirements_disabled_reason')) {
                $table->string('requirements_disabled_reason')->nullable()->after('requirements_currently_due');
            }

            if (! Schema::hasColumn('payment_provider_accounts', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        Schema::table('payment_intents', function (Blueprint $table): void {
            if (! Schema::hasColumn('payment_intents', 'provider_account_id')) {
                $table->string('provider_account_id')->nullable()->after('provider_intent_id')->index('payment_intents_provider_account_id_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('payment_intents', function (Blueprint $table): void {
            if (Schema::hasColumn('payment_intents', 'provider_account_id')) {
                $table->dropIndex('payment_intents_provider_account_id_index');
                $table->dropColumn('provider_account_id');
            }
        });

        Schema::table('payment_provider_accounts', function (Blueprint $table): void {
            $columns = [];

            foreach ([
                'provider_account_id',
                'capabilities',
                'last_verified_at',
                'created_by',
                'onboarding_completed_at',
                'charges_enabled',
                'payouts_enabled',
                'requirements_currently_due',
                'requirements_disabled_reason',
                'deleted_at',
            ] as $column) {
                if (Schema::hasColumn('payment_provider_accounts', $column)) {
                    $columns[] = $column;
                }
            }

            if (Schema::hasColumn('payment_provider_accounts', 'provider_account_id')) {
                $table->dropIndex('payment_provider_account_provider_account_index');
            }

            if (Schema::hasColumn('payment_provider_accounts', 'created_by')) {
                $table->dropIndex('payment_provider_account_created_by_index');
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
