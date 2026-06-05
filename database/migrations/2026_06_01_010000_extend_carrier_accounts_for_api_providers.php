<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->string('provider', 32)->default('manual')->index()->after('carrier_id');
            $table->string('environment', 16)->default('sandbox')->index()->after('provider');
            $table->string('connection_mode', 40)->default('manual')->after('connection_type');
            $table->string('billing_owner', 32)->default('merchant')->after('connection_mode');
            $table->string('provider_account_number', 64)->nullable()->after('billing_owner');
            $table->json('capabilities')->nullable()->after('settings');
            $table->string('connection_status', 32)->default('not_connected')->index()->after('status');
            $table->timestamp('last_verified_at')->nullable()->after('connection_status');
            $table->string('last_error_code', 64)->nullable()->after('last_verified_at');
            $table->text('last_error_message')->nullable()->after('last_error_code');
        });

        Schema::create('carrier_api_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->foreignId('carrier_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('shipment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider', 32);
            $table->string('environment', 16)->default('sandbox');
            $table->string('action', 64);
            $table->string('status', 32);
            $table->string('request_id')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->json('request_summary')->nullable();
            $table->json('response_summary')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'provider', 'created_at']);
            $table->index(['carrier_account_id', 'created_at']);
        });

        DB::table('carrier_accounts')->orderBy('id')->chunkById(100, function ($accounts): void {
            foreach ($accounts as $account) {
                $carrier = DB::table('carriers')->where('id', $account->carrier_id)->first();
                $code = $carrier?->code ?? 'manual-delivery';
                $provider = match ($code) {
                    'fedex' => 'fedex',
                    'ups' => 'ups',
                    'dhl' => 'dhl',
                    'usps' => 'usps',
                    default => 'manual',
                };

                $connectionMode = $account->connection_type === 'api' ? 'fedex_integrator_account' : 'manual';
                $connectionStatus = match (true) {
                    $provider === 'manual' && $account->status === 'enabled' => 'connected',
                    $provider === 'manual' => 'not_connected',
                    $account->status === 'enabled' => 'connected',
                    $account->status === 'setup_required' => 'setup_required',
                    default => 'not_connected',
                };

                DB::table('carrier_accounts')->where('id', $account->id)->update([
                    'provider' => $provider,
                    'environment' => 'sandbox',
                    'connection_mode' => $connectionMode,
                    'billing_owner' => 'merchant',
                    'connection_status' => $connectionStatus,
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('carrier_api_events');

        Schema::table('carrier_accounts', function (Blueprint $table): void {
            $table->dropColumn([
                'provider',
                'environment',
                'connection_mode',
                'billing_owner',
                'provider_account_number',
                'capabilities',
                'connection_status',
                'last_verified_at',
                'last_error_code',
                'last_error_message',
            ]);
        });
    }
};
