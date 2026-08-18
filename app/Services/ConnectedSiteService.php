<?php

namespace App\Services;

use App\Models\ConnectedSite;
use App\Models\ConnectedSiteEventDelivery;
use App\Models\ConnectedSiteOutboxEvent;
use App\Models\Product;
use App\Models\Store;
use App\Services\Payments\PaymentProviderManager;
use App\Support\CatalogRevision;
use App\Support\ConnectedSiteScope;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConnectedSiteService
{
    private const ACTIVE_SITE_URL_UNIQUE_INDEX = 'connected_sites_active_site_url_unique';

    public function __construct(
        private readonly SecurityLogRecorder $securityLogRecorder,
        private readonly PaymentProviderManager $paymentProviderManager,
    ) {}

    /**
     * @return array{site: ConnectedSite, plain: string, rotated: bool}
     */
    public function issuePrimaryCredential(Store $store): array
    {
        $plain = 'baa_dev_'.Str::lower(Str::random(40));
        $hash = hash('sha256', $plain);
        $now = now();

        try {
            return DB::transaction(function () use ($store, $plain, $hash, $now): array {
                $lockedStore = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
                $site = $this->primarySiteForUpdate($lockedStore);
                $rotated = $site !== null && $site->isActive();

                if ($site === null) {
                    $url = $lockedStore->connectedWebsiteUrl();
                    $normalized = $this->normalizeSiteUrl((string) ($url ?? ''));
                    if ($normalized !== null) {
                        $this->assertNormalizedUrlAvailable($normalized);
                    }

                    $site = new ConnectedSite([
                        'store_id' => $lockedStore->id,
                        'public_id' => 'csite_'.Str::lower(Str::random(24)),
                        'status' => ConnectedSite::STATUS_ACTIVE,
                        'is_primary' => true,
                        'scopes' => ConnectedSiteScope::connectorDefaults(),
                        'site_url' => $url,
                        'site_url_normalized' => $normalized,
                    ]);
                } elseif (filled($site->site_url_normalized)) {
                    $this->assertNormalizedUrlAvailable((string) $site->site_url_normalized, $site->id);
                }

                $site->forceFill([
                    'credential_hash' => $hash,
                    'event_signing_secret' => $this->newEventSigningSecret(),
                    'status' => ConnectedSite::STATUS_ACTIVE,
                    'is_primary' => true,
                    'scopes' => $site->grantedScopes() !== [] ? $site->grantedScopes() : ConnectedSiteScope::connectorDefaults(),
                    'credential_created_at' => $site->credential_created_at ?? $now,
                    'credential_rotated_at' => $rotated ? $now : $site->credential_rotated_at,
                    'revoked_at' => null,
                    'last_seen_at' => null,
                ])->save();

                return ['site' => $site->fresh(), 'plain' => $plain, 'rotated' => $rotated];
            });
        } catch (QueryException $exception) {
            $this->throwConnectedSiteUrlException($exception);
        }
    }

    public function revokePrimary(Store $store): ?ConnectedSite
    {
        return DB::transaction(function () use ($store): ?ConnectedSite {
            $lockedStore = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
            $site = $this->primarySiteForUpdate($lockedStore);

            if ($site !== null && $site->isActive()) {
                $site->forceFill([
                    'status' => ConnectedSite::STATUS_REVOKED,
                    'revoked_at' => now(),
                    'event_signing_secret' => null,
                ])->save();
            }

            return $site?->fresh();
        });
    }

    public function resolveActiveByPlainToken(string $plain): ?ConnectedSite
    {
        $hash = hash('sha256', $plain);

        $site = ConnectedSite::query()
            ->with('store')
            ->where('credential_hash', $hash)
            ->first();

        if ($site?->isActive()) {
            return $site;
        }

        return null;
    }

    public function primarySite(Store $store): ?ConnectedSite
    {
        if (! Schema::hasTable('connected_sites')) {
            return null;
        }

        return ConnectedSite::query()
            ->where('store_id', $store->id)
            ->where('is_primary', true)
            ->orderByDesc('id')
            ->first();
    }

    public function bindWebsiteUrl(Store $store, string $url): Store
    {
        $normalized = $url === '' ? null : $this->normalizeSiteUrl($url);
        if ($url !== '' && $normalized === null) {
            throw ValidationException::withMessages([
                'website_url' => 'Enter a valid website address, including http:// or https://.',
            ]);
        }

        if ($normalized !== null) {
            $this->assertHttpsAllowed($url);
        }

        try {
            return DB::transaction(function () use ($store, $url, $normalized): Store {
                $lockedStore = Store::query()->whereKey($store->id)->lockForUpdate()->firstOrFail();
                $site = $this->primarySiteForUpdate($lockedStore);

                if ($normalized !== null) {
                    $this->assertNormalizedUrlAvailable($normalized, $site?->id);
                }

                $settings = is_array($lockedStore->settings) ? $lockedStore->settings : [];
                if ($url === '') {
                    unset($settings['connected_website_url']);
                } else {
                    $settings['connected_website_url'] = $url;
                }
                $lockedStore->forceFill(['settings' => $settings])->save();

                if ($site !== null) {
                    $site->forceFill([
                        'site_url' => $url !== '' ? $url : null,
                        'site_url_normalized' => $normalized,
                    ])->save();
                }

                return $lockedStore->fresh();
            });
        } catch (QueryException $exception) {
            $this->throwConnectedSiteUrlException($exception);
        }
    }

    public function observeAuthenticatedRequest(ConnectedSite $site, Request $request): void
    {
        $updates = [
            'last_seen_at' => now(),
            'last_seen_ip' => $request->ip(),
        ];

        $version = trim((string) $request->header('X-Eco-Plugin-Version', ''));
        if ($version !== '' && $version !== (string) $site->plugin_version) {
            $updates['plugin_version'] = Str::limit($version, 32, '');
        }

        $site->forceFill($updates)->save();

        $site->store?->stampDeveloperStorefrontLastSeen();
    }

    public function recordHealth(ConnectedSite $site, array $payload): void
    {
        $site->forceFill([
            'last_health_at' => now(),
            'last_health' => $payload,
        ])->save();
    }

    /**
     * @param  array{production_ready:?bool,conflicts?:list<array<string, string>>}|null  $diagnostics
     * @return array<string, mixed>
     */
    public function healthPayload(Store $store, ?ConnectedSite $site, Request $request, ?array $diagnostics = null): array
    {
        $reportedUrl = trim((string) $request->header('X-Eco-Site-Url', ''));
        $normalizedReported = $this->normalizeSiteUrl($reportedUrl);
        $expected = $site?->site_url_normalized;
        $urlMatch = $expected === null || $normalizedReported === null
            ? null
            : hash_equals($expected, $normalizedReported);

        $pluginVersion = trim((string) $request->header('X-Eco-Plugin-Version', $site?->plugin_version ?? ''));
        $compatible = $pluginVersion === '' || version_compare($this->numericVersion($pluginVersion), '1.2.0', '>=');
        $receivesCatalogEvents = $pluginVersion !== '' && version_compare($this->numericVersion($pluginVersion), '1.6.0', '>=');

        $stripeReady = false;
        try {
            $stripeReady = $this->paymentProviderManager->accountForCheckout($store) !== null;
        } catch (\Throwable) {
            $stripeReady = false;
        }

        $hasLocation = $store->locations()->where('is_active', true)->exists();
        $catalogCount = Product::query()
            ->where('store_id', $store->id)
            ->where('status', true)
            ->count();

        $messages = [];
        if ($urlMatch === false) {
            $messages[] = 'This WordPress address does not match the website saved for this store.';
        }
        if (! $stripeReady) {
            $messages[] = 'Connect Stripe in Payments before shoppers can pay.';
        }
        if (! $hasLocation) {
            $messages[] = 'Add a fulfillment location before checkout can quote delivery.';
        }
        if ($catalogCount < 1) {
            $messages[] = 'Publish a product so the shop is not empty.';
        }
        if (! $compatible) {
            $messages[] = 'Update the Eco Portal plugin to continue using this connection.';
        } elseif ($pluginVersion !== '' && ! $receivesCatalogEvents) {
            $messages[] = 'Update the Eco Portal plugin so product changes appear on the website without a manual cache clear.';
        }

        $reachable = $site === null || $site->isActive();
        $diagnostics = is_array($diagnostics) ? $diagnostics : ['production_ready' => null, 'conflicts' => []];
        $conflicts = is_array($diagnostics['conflicts'] ?? null) ? $diagnostics['conflicts'] : [];
        if ($conflicts === [] && is_array($site?->last_health['conflicts'] ?? null)) {
            $conflicts = $site->last_health['conflicts'];
        }
        $productionReady = $diagnostics['production_ready'] ?? null;
        if ($productionReady === null && array_key_exists('production_ready', is_array($site?->last_health) ? $site->last_health : [])) {
            $productionReady = (bool) $site->last_health['production_ready'];
        }
        if ($productionReady === null && $conflicts === [] && $pluginVersion !== '') {
            $messages[] = 'Run Test connection in WordPress (Settings → Eco Portal) so this portal can check for WooCommerce and cache conflicts.';
        }
        if ($conflicts !== []) {
            $productionReady = false;
            foreach ($conflicts as $conflict) {
                $title = trim((string) ($conflict['title'] ?? ''));
                $instruction = trim((string) ($conflict['instruction'] ?? ''));
                if ($title !== '') {
                    $messages[] = $instruction !== '' ? $title.'. '.$instruction : $title;
                }
            }
        }

        $catalogCache = is_array($diagnostics['catalog_cache'] ?? null)
            ? $diagnostics['catalog_cache']
            : (is_array($site?->last_health['catalog_cache'] ?? null) ? $site->last_health['catalog_cache'] : []);
        $catalogSync = $this->catalogSyncSummary($store, $site, $catalogCache);

        return [
            'ok' => $reachable && $urlMatch !== false,
            'store' => [
                'id' => $store->id,
                'name' => $store->name,
                'currency' => $store->currency,
            ],
            'site' => [
                'public_id' => $site?->public_id,
                'url' => $site?->site_url,
                'url_match' => $urlMatch,
                'primary' => (bool) ($site?->is_primary ?? true),
            ],
            'credential' => [
                'valid' => true,
                'status' => $site?->status ?? 'active',
            ],
            'plugin' => [
                'reported_version' => $pluginVersion !== '' ? $pluginVersion : null,
                'compatible' => $compatible,
                'catalog_events' => $receivesCatalogEvents,
            ],
            'readiness' => [
                'stripe' => $stripeReady,
                'currency' => filled($store->currency),
                'location' => $hasLocation,
                'catalog' => $catalogCount > 0,
                'production' => $productionReady === true
                    && $stripeReady
                    && $hasLocation
                    && $catalogCount > 0
                    && $urlMatch !== false
                    && $compatible,
            ],
            'site_diagnostics' => [
                'production_ready' => $productionReady,
                'conflicts' => $conflicts,
                'catalog_cache' => $catalogCache,
            ],
            'catalog_sync' => $catalogSync,
            'last_successful_contact_at' => optional($site?->last_seen_at ?? $store->developer_storefront_last_seen_at)?->toIso8601String(),
            'catalog_version' => $catalogSync['catalog_version'],
            'messages' => $messages,
        ];
    }

    public function requiredScopeForRequest(Request $request): string
    {
        $path = $request->path();
        $method = strtoupper($request->method());

        if (str_starts_with($path, 'api/v1/site/health') || str_starts_with($path, 'api/v1/site/events/config')) {
            return ConnectedSiteScope::SITE_HEALTH;
        }

        if (str_starts_with($path, 'api/v1/orders/confirmation')) {
            return ConnectedSiteScope::ORDERS_READ;
        }

        if (str_starts_with($path, 'api/v1/catalog') || $path === 'api/developer-storefront/catalog') {
            return ConnectedSiteScope::CATALOG_READ;
        }

        if (str_contains($path, '/delivery-options')) {
            return ConnectedSiteScope::SHIPPING_QUOTE;
        }

        if ($method === 'GET' && str_starts_with($path, 'api/v1/checkout/')) {
            return ConnectedSiteScope::CHECKOUT_READ;
        }

        if (str_starts_with($path, 'api/v1/checkout')) {
            return ConnectedSiteScope::CHECKOUT_CREATE;
        }

        return ConnectedSiteScope::CATALOG_READ;
    }

    public function assertSiteBinding(ConnectedSite $site, Request $request): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $expected = $site->site_url_normalized;
        if (! is_string($expected) || $expected === '') {
            abort(response()->json([
                'message' => 'Bind this connection to its WordPress website address before using it in production.',
            ], 403));
        }

        $reported = $this->normalizeSiteUrl(trim((string) $request->header('X-Eco-Site-Url', '')));
        if ($reported === null || ! hash_equals($expected, $reported)) {
            abort(response()->json([
                'message' => 'This connection is bound to a different website address.',
            ], 403));
        }
    }

    public function normalizeSiteUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);
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

    public function recordAuthFailure(Request $request, string $reason, ?Store $store = null): void
    {
        $this->securityLogRecorder->record(
            $request,
            'connected_site.auth_failed',
            severity: \App\Models\SecurityLog::SEVERITY_WARNING,
            store: $store,
            metadata: [
                'reason' => $reason,
                'path' => $request->path(),
            ],
        );
    }

    private function assertHttpsAllowed(string $url): void
    {
        if (! app()->environment('production')) {
            return;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($scheme !== 'https') {
            throw ValidationException::withMessages([
                'website_url' => 'Use an https website address in production.',
            ]);
        }
    }

    private function assertNormalizedUrlAvailable(string $normalized, ?int $exceptSiteId = null): void
    {
        if (! $this->normalizedUrlIsAvailable($normalized, $exceptSiteId)) {
            throw ValidationException::withMessages([
                'website_url' => 'That WordPress site is already connected to another store.',
            ]);
        }
    }

    private function normalizedUrlIsAvailable(string $normalized, ?int $exceptSiteId = null): bool
    {
        $query = ConnectedSite::query()
            ->when(
                Schema::hasColumn('connected_sites', 'active_site_url_key'),
                fn ($query) => $query->where('active_site_url_key', $normalized),
                fn ($query) => $query->where('site_url_normalized', $normalized)->where('status', ConnectedSite::STATUS_ACTIVE),
            );

        if ($exceptSiteId !== null) {
            $query->whereKeyNot($exceptSiteId);
        }

        return ! $query->exists();
    }

    private function primarySiteForUpdate(Store $store): ?ConnectedSite
    {
        return ConnectedSite::query()
            ->where('store_id', $store->id)
            ->where('is_primary', true)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    private function throwConnectedSiteUrlException(QueryException $exception): never
    {
        if ($this->isActiveSiteUrlUniqueViolation($exception)) {
            throw ValidationException::withMessages([
                'website_url' => 'That WordPress site is already connected to another store.',
            ]);
        }

        throw $exception;
    }

    private function isActiveSiteUrlUniqueViolation(QueryException $exception): bool
    {
        $errorInfo = is_array($exception->errorInfo ?? null) ? $exception->errorInfo : [];
        $sqlState = strtoupper((string) ($errorInfo[0] ?? $exception->getCode()));
        $driverCode = (string) ($errorInfo[1] ?? '');
        $message = Str::lower($exception->getMessage());

        $mentionsConstraint = str_contains($message, Str::lower(self::ACTIVE_SITE_URL_UNIQUE_INDEX))
            || str_contains($message, 'connected_sites.active_site_url_key');
        $isUniqueViolation = in_array($sqlState, ['23000', '23505'], true)
            || in_array($driverCode, ['19', '1062'], true)
            || str_contains($message, 'unique constraint')
            || str_contains($message, 'duplicate entry')
            || str_contains($message, 'unique violation');

        return $mentionsConstraint && $isUniqueViolation;
    }

    public function ensureEventSigningSecret(ConnectedSite $site): string
    {
        $existing = (string) ($site->event_signing_secret ?? '');
        if ($existing !== '') {
            return $existing;
        }

        $secret = $this->newEventSigningSecret();
        $site->forceFill(['event_signing_secret' => $secret])->save();

        return $secret;
    }

    /**
     * @param  array<string, mixed>  $siteCache
     * @return array<string, mixed>
     */
    public function catalogSyncSummary(?Store $store, ?ConnectedSite $site, array $siteCache = []): array
    {
        $version = $store ? CatalogRevision::forStore($store) : null;
        $pending = 0;
        $lastDeliveredAt = null;
        $lastDeliveryStatus = null;
        $lastEventId = null;

        if ($site && Schema::hasTable('connected_site_event_deliveries')) {
            $pending = ConnectedSiteEventDelivery::query()
                ->where('connected_site_id', $site->id)
                ->where('status', ConnectedSiteEventDelivery::STATUS_PENDING)
                ->count();

            $latest = ConnectedSiteEventDelivery::query()
                ->where('connected_site_id', $site->id)
                ->orderByDesc('id')
                ->first();
            $lastDeliveryStatus = $latest?->status;
            $lastDeliveredAt = optional(
                ConnectedSiteEventDelivery::query()
                    ->where('connected_site_id', $site->id)
                    ->whereNotNull('delivered_at')
                    ->orderByDesc('delivered_at')
                    ->value('delivered_at')
            )?->toIso8601String();
        }

        if ($store && Schema::hasTable('connected_site_outbox_events')) {
            $lastEventId = ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->orderByDesc('id')
                ->value('public_id');
        }

        $reportedVersion = trim((string) ($siteCache['version'] ?? $siteCache['catalog_version'] ?? ''));

        return [
            'catalog_version' => $version,
            'last_event_id' => $lastEventId,
            'pending_deliveries' => $pending,
            'last_delivery_status' => $lastDeliveryStatus,
            'last_delivered_at' => $lastDeliveredAt,
            'site_cache_version' => $reportedVersion !== '' ? $reportedVersion : null,
            'site_last_event_id' => $siteCache['last_event_id'] ?? null,
            'site_last_rebuild_at' => $siteCache['last_rebuild_at'] ?? null,
            'site_last_reconcile_at' => $siteCache['last_reconcile_at'] ?? null,
            'website_matches_portal' => $reportedVersion === '' || $version === null
                ? null
                : hash_equals((string) $version, $reportedVersion),
        ];
    }

    private function newEventSigningSecret(): string
    {
        return 'csevtsec_'.Str::lower(Str::random(40));
    }

    private function numericVersion(string $version): string
    {
        if (preg_match('/\d+(?:\.\d+)*/', $version, $matches) === 1) {
            return $matches[0];
        }

        return '0';
    }
}
