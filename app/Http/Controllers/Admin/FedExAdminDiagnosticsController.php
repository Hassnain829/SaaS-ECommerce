<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExTradeDocument;
use App\Models\IdempotencyKey;
use App\Models\Shipment;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Admin-only FedEx operations diagnostics — no credentials or raw payloads.
 */
class FedExAdminDiagnosticsController extends Controller
{
    public function index(Request $request): View
    {
        $failedConnections = CarrierAccount::query()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->whereIn('connection_status', [
                CarrierAccount::CONNECTION_FAILED,
                'blocked_by_fedex',
            ])
            ->orderByDesc('updated_at')
            ->limit(25)
            ->get(['id', 'store_id', 'display_name', 'environment', 'connection_status', 'last_error_message', 'updated_at']);

        $failedEvents = CarrierApiEvent::query()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->where('status', '!=', 'succeeded')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'store_id', 'carrier_account_id', 'action', 'status', 'error_code', 'error_message', 'response_summary', 'created_at']);

        $uncertainShipOps = IdempotencyKey::query()
            ->where('request_path', 'like', '/ship/v1/%')
            ->where(function ($q): void {
                $q->where('response_body->state', 'uncertain')
                    ->orWhere('response_body->state', 'processing');
            })
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'store_id', 'key', 'response_code', 'response_body', 'resource_id', 'updated_at']);

        $recentShipments = Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereHas('carrierAccount', fn ($q) => $q->where('provider', CarrierAccount::PROVIDER_FEDEX))
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'store_id', 'order_id', 'shipment_number', 'tracking_number', 'status', 'carrier_account_id', 'created_at']);

        $etdDocs = FedExTradeDocument::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'store_id', 'order_id', 'status', 'fedex_document_id', 'origin_country_code', 'destination_country_code', 'uploaded_at', 'created_at']);

        return view('admin.fedex_diagnostics', [
            'failedConnections' => $failedConnections,
            'failedEvents' => $failedEvents,
            'uncertainShipOps' => $uncertainShipOps,
            'recentShipments' => $recentShipments,
            'etdDocs' => $etdDocs,
            'flags' => [
                'production' => (bool) config('carriers.fedex.integrator_production_enabled'),
                'checkout_rates' => (bool) config('carriers.fedex.checkout_rates_enabled'),
                'ship_labels' => (bool) config('carriers.fedex.ops_ship_labels_enabled'),
                'tracking' => (bool) config('carriers.fedex.ops_tracking_enabled'),
            ],
        ]);
    }
}
