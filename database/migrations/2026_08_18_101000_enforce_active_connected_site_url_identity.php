<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $activeKeys = [];
        $duplicates = [];
        $backfill = [];
        foreach (DB::table('connected_sites')->orderBy('id')->get() as $site) {
            $normalized = $this->normalizeUrl((string) ($site->site_url_normalized ?: $site->site_url));
            $activeKey = (string) $site->status === 'active' ? $normalized : null;

            if ($activeKey !== null) {
                if (isset($activeKeys[$activeKey])) {
                    $duplicates[$activeKey] = true;
                }
                $activeKeys[$activeKey] = (int) $site->id;
            }
            $backfill[(int) $site->id] = [
                'site_url_normalized' => $normalized,
                'active_site_url_key' => $activeKey,
            ];
        }

        if ($duplicates !== []) {
            throw new RuntimeException(
                'Duplicate active connected-site URLs must be resolved before deployment: '.implode(', ', array_keys($duplicates))
            );
        }

        $missingCredentialStores = [];
        if (Schema::hasColumn('stores', 'developer_storefront_token_hash')) {
            foreach (DB::table('stores')->whereNotNull('developer_storefront_token_hash')->orderBy('id')->get() as $store) {
                $preserved = DB::table('connected_sites')
                    ->where('store_id', $store->id)
                    ->where('credential_hash', $store->developer_storefront_token_hash)
                    ->exists();
                if (! $preserved) {
                    $missingCredentialStores[] = (int) $store->id;
                }
            }

            if ($missingCredentialStores !== []) {
                throw new RuntimeException(
                    'Legacy storefront credentials were not preserved for store IDs: '.implode(', ', $missingCredentialStores).'. Repair connected_sites before rerunning this migration.'
                );
            }
        }

        Schema::table('connected_sites', function (Blueprint $table): void {
            $table->string('active_site_url_key', 255)->nullable()->after('site_url_normalized');
        });

        foreach ($backfill as $siteId => $values) {
            DB::table('connected_sites')->where('id', $siteId)->update($values);
        }

        Schema::table('connected_sites', function (Blueprint $table): void {
            $table->unique('active_site_url_key', 'connected_sites_active_site_url_unique');
        });

        if (Schema::hasColumn('stores', 'developer_storefront_token_hash')) {
            $clear = ['developer_storefront_token_hash' => null];
            if (Schema::hasColumn('stores', 'developer_storefront_token_created_at')) {
                $clear['developer_storefront_token_created_at'] = null;
            }
            DB::table('stores')->whereNotNull('developer_storefront_token_hash')->update($clear);
        }
    }

    public function down(): void
    {
        Schema::table('connected_sites', function (Blueprint $table): void {
            $table->dropUnique('connected_sites_active_site_url_unique');
            $table->dropColumn('active_site_url_key');
        });
    }

    private function normalizeUrl(string $url): ?string
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? 'https'));
        if (! in_array($scheme, ['http', 'https'], true)) {
            return null;
        }

        $host = strtolower((string) $parts['host']);
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = isset($parts['path']) ? rtrim((string) $parts['path'], '/') : '';

        return $scheme.'://'.$host.$port.$path;
    }
};
