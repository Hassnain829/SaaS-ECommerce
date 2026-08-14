<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ConnectedSiteOutboxEvent;
use App\Services\ConnectedSiteService;
use App\Support\CatalogRevision;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConnectedSiteCatalogEventsController extends Controller
{
    public function config(Request $request, ConnectedSiteService $connectedSites): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $site = $request->attributes->get('connectedSite');
        abort_unless($site, 404);

        $secret = $connectedSites->ensureEventSigningSecret($site);

        return response()->json([
            'signing_secret' => $secret,
            'catalog_version' => CatalogRevision::forStore($store),
            'poll_path' => '/api/v1/catalog/events',
            'receive_path' => '/wp-json/eco-portal/v1/events',
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $limit = max(1, min(50, (int) $request->query('limit', 25)));
        $after = trim((string) $request->query('after', ''));

        $query = ConnectedSiteOutboxEvent::query()
            ->where('store_id', $store->id)
            ->orderBy('id');

        if ($after !== '') {
            $afterId = ConnectedSiteOutboxEvent::query()
                ->where('store_id', $store->id)
                ->where('public_id', $after)
                ->value('id');
            if ($afterId) {
                $query->where('id', '>', $afterId);
            }
        }

        $events = $query->limit($limit)->get();
        $last = $events->last();

        return response()->json([
            'data' => $events->map(static function (ConnectedSiteOutboxEvent $event): array {
                $payload = is_array($event->payload) ? $event->payload : [];

                return [
                    'id' => $event->public_id,
                    'type' => $event->type,
                    'occurred_at' => optional($event->occurred_at)?->toIso8601String(),
                    'catalog_version' => $event->catalog_version,
                    'resource' => [
                        'product_id' => $payload['product_id'] ?? null,
                        'variant_id' => $payload['variant_id'] ?? null,
                        'category_id' => $payload['category_id'] ?? null,
                        'published' => $payload['published'] ?? null,
                    ],
                ];
            })->values(),
            'meta' => [
                'catalog_version' => CatalogRevision::forStore($store),
                'next_after' => $last?->public_id,
            ],
        ]);
    }
}
