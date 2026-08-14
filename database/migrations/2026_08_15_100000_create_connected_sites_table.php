<?php

use App\Models\Store;
use App\Support\ConnectedSiteScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connected_sites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('store_id')->constrained()->cascadeOnDelete();
            $table->string('public_id', 40)->unique();
            $table->string('site_url', 2048)->nullable();
            $table->string('site_url_normalized', 255)->nullable();
            $table->string('credential_hash', 64)->unique();
            $table->string('status', 32)->default('active');
            $table->boolean('is_primary')->default(true);
            $table->json('scopes');
            $table->string('plugin_version', 32)->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->string('last_seen_ip', 45)->nullable();
            $table->timestamp('last_health_at')->nullable();
            $table->json('last_health')->nullable();
            $table->timestamp('credential_created_at')->nullable();
            $table->timestamp('credential_rotated_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['store_id', 'is_primary']);
            $table->index(['store_id', 'status']);
            $table->index('site_url_normalized');
        });

        if (! Schema::hasColumn('stores', 'developer_storefront_token_hash')) {
            return;
        }

        $scopes = json_encode(ConnectedSiteScope::connectorDefaults());

        Store::query()
            ->whereNotNull('developer_storefront_token_hash')
            ->orderBy('id')
            ->chunkById(100, function ($stores) use ($scopes): void {
                foreach ($stores as $store) {
                    $hash = (string) $store->developer_storefront_token_hash;
                    if ($hash === '') {
                        continue;
                    }

                    $exists = DB::table('connected_sites')->where('credential_hash', $hash)->exists();
                    if ($exists) {
                        continue;
                    }

                    $url = data_get($store->settings, 'connected_website_url');
                    $url = is_string($url) ? trim($url) : '';
                    $normalized = $url !== '' ? $this->normalizeUrl($url) : null;
                    if ($normalized !== null && DB::table('connected_sites')->where('site_url_normalized', $normalized)->exists()) {
                        $normalized = null;
                    }

                    DB::table('connected_sites')->insert([
                        'store_id' => $store->id,
                        'public_id' => 'csite_'.Str::lower(Str::random(24)),
                        'site_url' => $url !== '' ? $url : null,
                        'site_url_normalized' => $normalized,
                        'credential_hash' => $hash,
                        'status' => 'active',
                        'is_primary' => true,
                        'scopes' => $scopes,
                        'last_seen_at' => $store->developer_storefront_last_seen_at,
                        'credential_created_at' => $store->developer_storefront_token_created_at ?? now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            });
    }

    public function down(): void
    {
        Schema::dropIfExists('connected_sites');
    }

    private function normalizeUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.$path;
    }
};
