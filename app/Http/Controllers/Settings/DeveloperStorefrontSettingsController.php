<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\ConnectedSiteService;
use App\Services\SecurityLogRecorder;
use App\Support\ConnectedSiteScope;
use FilesystemIterator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

class DeveloperStorefrontSettingsController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $store->refresh();
        $connectedSites = app(ConnectedSiteService::class);
        $connectedSite = $connectedSites->primarySite($store);
        $health = is_array($connectedSite?->last_health) ? $connectedSite->last_health : [];
        $catalogCache = is_array($health['catalog_cache'] ?? null) ? $health['catalog_cache'] : [];
        $catalogSync = $connectedSites->catalogSyncSummary($store, $connectedSite, $catalogCache);
        $scopeLabels = $connectedSite
            ? array_map(
                static fn (string $scope): string => ConnectedSiteScope::label($scope),
                $connectedSite->grantedScopes()
            )
            : [];

        $connectionState = $store->websiteConnectionState();
        $tokenConfigured = $store->hasDeveloperStorefrontToken();
        $lastSeenAt = $connectedSite?->last_seen_at ?? $store->developer_storefront_last_seen_at;

        $currentStep = match (true) {
            $connectionState === Store::WEBSITE_DISCONNECTED => 2,
            ! $tokenConfigured => 1,
            $lastSeenAt === null => 3,
            default => 4,
        };

        return view('user_view.developer_storefront', [
            'selectedStore' => $store,
            'tokenConfigured' => $tokenConfigured,
            'tokenCreatedAt' => $store->developer_storefront_token_created_at,
            'plainToken' => $request->session()->pull('developer_storefront_plain_token'),
            'websiteUrl' => $connectedSite?->site_url ?: $store->connectedWebsiteUrl(),
            'lastSeenAt' => $lastSeenAt,
            'connectionState' => $connectionState,
            'currentStep' => $currentStep,
            'publishedProductCount' => Product::query()
                ->where('store_id', $store->id)
                ->where('status', true)
                ->count(),
            'connectedSite' => $connectedSite,
            'connectedSiteHealth' => $health,
            'catalogSync' => $catalogSync,
            'connectedSiteScopeLabels' => $scopeLabels,
            'portalAddress' => rtrim((string) config('app.url'), '/'),
            'stripeConfig' => [
                'mode' => (string) config('payments.stripe.mode', 'test'),
                'publishable_key' => filled(config('payments.stripe.key')),
                'secret_key' => filled(config('payments.stripe.secret')),
                'webhook_secret' => filled(config('payments.stripe.webhook_secret')),
            ],
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $issued = app(ConnectedSiteService::class)->issuePrimaryCredential($store);

        app(SecurityLogRecorder::class)->record(
            $request,
            'api_key_created',
            store: $store,
            metadata: [
                'source' => 'connected_site',
                'public_id' => $issued['site']->public_id,
                'rotated' => $issued['rotated'],
            ]
        );

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', $issued['rotated']
                ? 'Copy this new key now. The previous key stops working immediately.'
                : 'Copy this key now. It will not be shown again.')
            ->with('developer_storefront_plain_token', $issued['plain']);
    }

    public function revoke(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $site = app(ConnectedSiteService::class)->revokePrimary($store);

        app(SecurityLogRecorder::class)->record(
            $request,
            'api_key_revoked',
            store: $store,
            metadata: [
                'source' => 'connected_site',
                'public_id' => $site?->public_id,
            ]
        );

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', 'The connection key was removed. Your website will stop loading this store’s products until you create a new key.');
    }

    public function updateWebsiteUrl(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $validated = $request->validate([
            'website_url' => ['nullable', 'string', 'max:2048'],
        ]);

        $url = trim((string) ($validated['website_url'] ?? ''));
        if ($url !== '' && filter_var($url, FILTER_VALIDATE_URL) === false) {
            return redirect()
                ->route('developer-storefront.settings')
                ->withErrors(['website_url' => 'Enter a valid website address, including http:// or https://.']);
        }

        try {
            app(ConnectedSiteService::class)->bindWebsiteUrl($store, $url);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return redirect()
                ->route('developer-storefront.settings')
                ->withErrors($exception->errors());
        }

        return redirect()
            ->route('developer-storefront.settings')
            ->with('success', $url === ''
                ? 'Website address cleared.'
                : 'Website address saved.');
    }

    public function downloadPlugin(): BinaryFileResponse|RedirectResponse
    {
        $source = base_path('dev-test-wordpress/wp-content/plugins/eco-portal-connector');

        if (! is_dir($source)) {
            return redirect()
                ->route('developer-storefront.settings')
                ->withErrors(['plugin' => 'The WordPress plugin is not available on this server.']);
        }

        if (! class_exists(ZipArchive::class)) {
            return redirect()
                ->route('developer-storefront.settings')
                ->withErrors(['plugin' => 'Plugin download is unavailable because ZIP support is missing.']);
        }

        $tmp = tempnam(sys_get_temp_dir(), 'eco-wp-');
        if ($tmp === false) {
            return redirect()
                ->route('developer-storefront.settings')
                ->withErrors(['plugin' => 'Could not prepare the plugin download.']);
        }

        $zipPath = $tmp.'.zip';
        @unlink($tmp);

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return redirect()
                ->route('developer-storefront.settings')
                ->withErrors(['plugin' => 'Could not prepare the plugin download.']);
        }

        $this->addDirectoryToZip($zip, $source, 'eco-portal-connector');
        $zip->close();

        return response()
            ->download($zipPath, 'eco-portal-connector.zip', [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="eco-portal-connector.zip"',
            ])
            ->deleteFileAfterSend(true);
    }

    private function addDirectoryToZip(ZipArchive $zip, string $directory, string $prefix): void
    {
        $directory = rtrim($directory, DIRECTORY_SEPARATOR);
        $zip->addEmptyDir($prefix);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $file) {
            $relative = $prefix.'/'.str_replace('\\', '/', substr($file->getPathname(), strlen($directory) + 1));
            if ($file->isDir()) {
                $zip->addEmptyDir($relative);
            } else {
                $zip->addFile($file->getPathname(), $relative);
            }
        }
    }
}
