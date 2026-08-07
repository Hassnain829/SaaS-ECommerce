<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\Shipment;
use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Customer-facing FedEx tracking page (token + tracking number; no merchant credentials).
 */
class FedExPublicTrackingController extends Controller
{
    public function show(Request $request, string $storeSlug, string $token): View
    {
        $store = Store::query()->where('slug', $storeSlug)->firstOrFail();

        $shipment = Shipment::query()
            ->where('store_id', $store->id)
            ->where('metadata->fedex->public_tracking_token', $token)
            ->firstOrFail();

        $tracking = (array) data_get($shipment->metadata, 'fedex.tracking', []);

        return view('storefront.fedex_tracking', [
            'store' => $store,
            'shipment' => $shipment,
            'trackingNumber' => $shipment->tracking_number,
            'statusText' => $tracking['status_text'] ?? $shipment->status,
            'timeline' => (array) ($tracking['timeline'] ?? []),
            'estimatedDelivery' => $tracking['estimated_delivery'] ?? null,
            'deliveredAt' => $tracking['delivered_at'] ?? $shipment->delivered_at,
            'exception' => $tracking['exception'] ?? null,
        ]);
    }
}
