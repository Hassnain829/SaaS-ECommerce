<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Store;
use App\Services\ConnectedSiteService;
use App\Services\SecurityLogRecorder;
use FilesystemIterator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
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

        return view('user_view.developer_storefront', array_merge($this->snapshot($store), [
            'selectedStore' => $store,
            'plainToken' => $request->session()->pull('developer_storefront_plain_token'),
            'portalAddress' => rtrim((string) config('app.url'), '/'),
            'catalogApiUrl' => rtrim((string) config('app.url'), '/').'/api/developer-storefront',
        ]));
    }

    /**
     * Live connection status for the connect page, polled by the browser.
     */
    public function status(Request $request): JsonResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return response()->json(['message' => 'No active store was found.'], 404);
        }

        $store->refresh();
        $snapshot = $this->snapshot($store);

        return response()->json([
            'state' => $snapshot['connectionState'],
            'label' => $snapshot['stateLabel'],
            'detail' => $snapshot['stateDetail'],
            'website_url' => $snapshot['websiteUrl'],
            'last_seen_human' => $snapshot['lastSeenAt']?->diffForHumans(),
            'catalog_status' => $snapshot['catalogStatus'],
            'published_products' => $snapshot['publishedProductCount'],
            'site_issues' => count($snapshot['siteIssues']),
            'steps_done' => [
                1 => $snapshot['step1Done'],
                2 => $snapshot['step2Done'],
                3 => $snapshot['step3Done'],
            ],
            'checked_at' => now()->format('H:i:s'),
        ]);
    }

    /**
     * Everything the connect page and its status poll need about one store.
     */
    private function snapshot(Store $store): array
    {
        $connectedSites = app(ConnectedSiteService::class);
        $connectedSite = $connectedSites->primarySite($store);
        $health = \is_array($connectedSite?->last_health) ? $connectedSite->last_health : [];
        $catalogCache = \is_array($health['catalog_cache'] ?? null) ? $health['catalog_cache'] : [];
        $catalogSync = $connectedSites->catalogSyncSummary($store, $connectedSite, $catalogCache);

        $connectionState = $store->websiteConnectionState();
        $tokenConfigured = $store->hasDeveloperStorefrontToken();
        $websiteUrl = $connectedSite?->site_url ?: $store->connectedWebsiteUrl();
        $lastSeenAt = $connectedSite?->last_seen_at ?? $store->developer_storefront_last_seen_at;

        $siteIssues = [];
        foreach (\is_array($health['conflicts'] ?? null) ? $health['conflicts'] : [] as $conflict) {
            if (! \is_array($conflict)) {
                continue;
            }

            $siteIssues[] = [
                'title' => (string) ($conflict['title'] ?? 'Website conflict'),
                'instruction' => (string) ($conflict['instruction'] ?? ''),
            ];
        }

        $websiteMatches = $catalogSync['website_matches_portal'] ?? null;
        $pendingDeliveries = (int) ($catalogSync['pending_deliveries'] ?? 0);
        $catalogStatus = match (true) {
            $websiteMatches === false => 'Product list is refreshing',
            $pendingDeliveries > 0 => $pendingDeliveries.' product '.($pendingDeliveries === 1 ? 'update is' : 'updates are').' on the way',
            $websiteMatches === true => 'Products up to date',
            default => null,
        };

        return [
            'connectedSite' => $connectedSite,
            'connectionState' => $connectionState,
            'stateLabel' => match ($connectionState) {
                Store::WEBSITE_CONNECTED => 'Connected',
                Store::WEBSITE_WAITING => 'Waiting for your website',
                Store::WEBSITE_DISCONNECTED => 'Disconnected',
                default => 'Not connected yet',
            },
            'stateDetail' => match ($connectionState) {
                Store::WEBSITE_CONNECTED => 'Your website is loading products from this store.',
                Store::WEBSITE_WAITING => 'Paste the key on your website, then run its connection test.',
                Store::WEBSITE_DISCONNECTED => 'The connection key was removed. Create a new one to reconnect.',
                default => 'Finish the three steps to put your products on your website.',
            },
            'tokenConfigured' => $tokenConfigured,
            'websiteUrl' => $websiteUrl,
            'lastSeenAt' => $lastSeenAt,
            'catalogStatus' => $catalogStatus,
            'siteIssues' => $siteIssues,
            'publishedProductCount' => Product::query()
                ->where('store_id', $store->id)
                ->where('status', true)
                ->count(),
            'step1Done' => filled($websiteUrl),
            'step2Done' => $tokenConfigured,
            'step3Done' => $tokenConfigured && $lastSeenAt !== null,
        ];
    }

    public function generate(Request $request): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');

        if (! $store) {
            return redirect()
                ->route('store-management')
                ->withErrors(['store' => 'No active store was found.']);
        }

        $connectedSites = app(ConnectedSiteService::class);
        $connectedSite = $connectedSites->primarySite($store);
        $websiteUrl = $connectedSite?->site_url ?: $store->connectedWebsiteUrl();

        if (! filled($websiteUrl)) {
            return redirect()
                ->route('developer-storefront.settings')
                ->withErrors([
                    'website_url' => 'Save this store\'s exact WordPress website address before creating a connection key.',
                ]);
        }

        $issued = $connectedSites->issuePrimaryCredential($store);

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
        } catch (ValidationException $exception) {
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
