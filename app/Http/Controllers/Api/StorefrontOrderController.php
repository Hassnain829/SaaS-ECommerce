<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Models\Store;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StorefrontOrderController extends Controller
{
    public function confirmation(Request $request, string $token): JsonResponse
    {
        /** @var Store|null $store */
        $store = $request->attributes->get('developerStorefrontStore');
        abort_unless($store, 401);

        $token = trim($token);
        abort_unless($token !== '' && str_starts_with($token, 'ordconf_'), 404);

        $hash = hash('sha256', $token);
        $order = Order::query()
            ->with(['items', 'shipments' => fn ($query) => $query->where('direction', Shipment::DIRECTION_OUTBOUND)])
            ->where('store_id', $store->id)
            ->where('meta->storefront->confirmation_token_hash', $hash)
            ->first();

        abort_unless($order, 404);

        return response()->json($this->serialize($order));
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Order $order): array
    {
        return [
            'order' => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'fulfillment_status' => $order->fulfillment_status,
                'currency_code' => $order->currency_code,
                'subtotal' => number_format((float) $order->subtotal, 2, '.', ''),
                'shipping' => number_format((float) $order->shipping, 2, '.', ''),
                'tax' => number_format((float) $order->tax, 2, '.', ''),
                'discount' => number_format((float) $order->discount, 2, '.', ''),
                'total' => number_format((float) ($order->grand_total ?: $order->total), 2, '.', ''),
                'placed_at' => optional($order->placed_at)?->toIso8601String(),
                'items' => $order->items->map(fn ($item): array => [
                    'product_name' => $item->product_name,
                    'variant_label' => $item->variant_label,
                    'quantity' => (int) $item->quantity,
                    'unit_price' => number_format((float) $item->unit_price, 2, '.', ''),
                    'total' => number_format((float) $item->total, 2, '.', ''),
                ])->values()->all(),
                'shipments' => $order->shipments->map(fn (Shipment $shipment): array => [
                    'status' => $shipment->status,
                    'carrier_service' => $shipment->carrier_service,
                    'tracking_number' => $shipment->tracking_number,
                    'tracking_url' => $shipment->tracking_url,
                    'shipped_at' => optional($shipment->shipped_at)?->toIso8601String(),
                    'delivered_at' => optional($shipment->delivered_at)?->toIso8601String(),
                ])->values()->all(),
            ],
        ];
    }
}
