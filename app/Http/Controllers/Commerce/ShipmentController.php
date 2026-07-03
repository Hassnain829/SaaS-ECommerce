<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Shipment;
use App\Services\Fulfillment\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ShipmentController extends Controller
{
    public function store(Request $request, Order $order, ShipmentService $shipmentService): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $order->store_id === (int) $store->id, 404);

        $validated = $request->validate([
            'origin_location_id' => ['nullable', 'integer'],
            'carrier_account_id' => ['nullable', 'integer'],
            'shipping_method_id' => ['nullable', 'integer'],
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'carrier_service' => ['nullable', 'string', 'max:120'],
            'package_count' => ['nullable', 'integer', 'min:1', 'max:100'],
            'package_weight' => ['nullable', 'numeric', 'min:0'],
            'shipping_cost' => ['nullable', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array'],
            'items.*' => ['nullable'],
        ]);

        $shipmentService->createShipment($order, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Shipment created.')
            ->with('success_title', 'Fulfillment updated');
    }

    public function updateTracking(Request $request, Shipment $shipment, ShipmentService $shipmentService): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);

        $validated = $request->validate([
            'tracking_number' => ['nullable', 'string', 'max:120'],
            'tracking_url' => ['nullable', 'url', 'max:500'],
            'carrier_service' => ['nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);

        $shipmentService->updateTracking($shipment, $validated, $request->user(), $request);

        return back()
            ->with('success', 'Tracking details updated.')
            ->with('success_title', 'Fulfillment updated');
    }

    public function markShipped(Request $request, Shipment $shipment, ShipmentService $shipmentService): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $shipmentService->markShipped($shipment, $request->user(), $request);

        return back()
            ->with('success', 'Shipment marked as shipped.')
            ->with('success_title', 'Fulfillment updated');
    }

    public function markDelivered(Request $request, Shipment $shipment, ShipmentService $shipmentService): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $shipmentService->markDelivered($shipment, $request->user(), $request);

        return back()
            ->with('success', 'Shipment marked as delivered.')
            ->with('success_title', 'Fulfillment updated');
    }

    public function markFailed(Request $request, Shipment $shipment, ShipmentService $shipmentService): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $shipmentService->markFailed($shipment, $request->user(), $request);

        return back()
            ->with('success', 'Shipment marked as failed.')
            ->with('success_title', 'Fulfillment updated');
    }

    public function cancel(Request $request, Shipment $shipment, ShipmentService $shipmentService): RedirectResponse
    {
        $this->authorizeShipment($request, $shipment);
        $shipmentService->cancelShipment($shipment, $request->user(), $request);

        return back()
            ->with('success', 'Shipment cancelled.')
            ->with('success_title', 'Fulfillment updated');
    }

    private function authorizeShipment(Request $request, Shipment $shipment): void
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $shipment->store_id === (int) $store->id, 404);
    }
}
