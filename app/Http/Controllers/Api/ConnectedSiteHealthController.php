<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ConnectedSiteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectedSiteHealthController extends Controller
{
    public function show(Request $request, ConnectedSiteService $connectedSites): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $site = $request->attributes->get('connectedSite');
        $diagnostics = $this->diagnosticsFromRequest($request);
        $payload = $connectedSites->healthPayload($store, $site, $request, $diagnostics);

        if ($site) {
            $connectedSites->recordHealth($site, [
                'ok' => $payload['ok'],
                'store_name' => $payload['store']['name'] ?? null,
                'url_match' => $payload['site']['url_match'] ?? null,
                'plugin_version' => $payload['plugin']['reported_version'] ?? null,
                'stripe' => $payload['readiness']['stripe'] ?? false,
                'location' => $payload['readiness']['location'] ?? false,
                'catalog' => $payload['readiness']['catalog'] ?? false,
                'production_ready' => $payload['readiness']['production'] ?? null,
                'conflicts' => $payload['site_diagnostics']['conflicts'] ?? [],
                'catalog_cache' => $payload['site_diagnostics']['catalog_cache'] ?? [],
                'catalog_sync' => $payload['catalog_sync'] ?? [],
                'messages' => $payload['messages'],
            ]);
        }

        $store->stampDeveloperStorefrontLastSeen();

        return response()->json($payload);
    }

    /**
     * @return array{production_ready:?bool,conflicts:list<array<string, string>>,catalog_cache:array<string, mixed>}
     */
    private function diagnosticsFromRequest(Request $request): array
    {
        if (
            ! $request->isMethod('POST')
            || (
                ! $request->exists('production_ready')
                && ! $request->exists('conflicts')
                && ! $request->exists('catalog_cache')
            )
        ) {
            return ['production_ready' => null, 'conflicts' => [], 'catalog_cache' => []];
        }

        $validated = $request->validate([
            'production_ready' => ['nullable', 'boolean'],
            'conflicts' => ['nullable', 'array', 'max:30'],
            'conflicts.*.code' => ['required_with:conflicts', 'string', 'max:80'],
            'conflicts.*.severity' => ['nullable', 'string', 'in:block,warning'],
            'conflicts.*.title' => ['required_with:conflicts', 'string', 'max:200'],
            'conflicts.*.instruction' => ['required_with:conflicts', 'string', 'max:500'],
            'catalog_cache' => ['nullable', 'array'],
            'catalog_cache.version' => ['nullable', 'string', 'max:80'],
            'catalog_cache.last_event_id' => ['nullable', 'string', 'max:40'],
            'catalog_cache.last_rebuild_at' => ['nullable', 'string', 'max:40'],
            'catalog_cache.last_reconcile_at' => ['nullable', 'string', 'max:40'],
        ]);

        $conflicts = [];
        foreach ($validated['conflicts'] ?? [] as $conflict) {
            if (! is_array($conflict)) {
                continue;
            }
            $conflicts[] = [
                'code' => (string) ($conflict['code'] ?? ''),
                'severity' => (string) ($conflict['severity'] ?? 'block'),
                'title' => (string) ($conflict['title'] ?? ''),
                'instruction' => (string) ($conflict['instruction'] ?? ''),
            ];
        }

        $cache = is_array($validated['catalog_cache'] ?? null) ? $validated['catalog_cache'] : [];

        return [
            'production_ready' => array_key_exists('production_ready', $validated)
                ? (bool) $validated['production_ready']
                : ($conflicts === [] ? null : false),
            'conflicts' => $conflicts,
            'catalog_cache' => [
                'version' => isset($cache['version']) ? (string) $cache['version'] : null,
                'last_event_id' => isset($cache['last_event_id']) ? (string) $cache['last_event_id'] : null,
                'last_rebuild_at' => isset($cache['last_rebuild_at']) ? (string) $cache['last_rebuild_at'] : null,
                'last_reconcile_at' => isset($cache['last_reconcile_at']) ? (string) $cache['last_reconcile_at'] : null,
            ],
        ];
    }
}
