<?php

namespace App\Http\Controllers\Commerce;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Support\OrderLifecycle;
use App\Support\StorePermission;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ShipmentsIndexController extends Controller
{
    public function index(Request $request): View
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);
        abort_unless(
            $request->user()?->hasStorePermission($store, StorePermission::ORDERS_VIEW),
            403
        );

        $status = (string) $request->query('status', 'all');
        if ($status !== 'all' && ! in_array($status, OrderLifecycle::shipmentStatuses(), true)) {
            $status = 'all';
        }

        $provider = strtolower((string) $request->query('provider', 'all'));
        if (! in_array($provider, ['all', 'fedex', 'manual', 'usps'], true)) {
            $provider = 'all';
        }

        $search = trim((string) $request->query('q', ''));

        $query = $store->shipments()
            ->with(['order.addresses', 'carrierAccount.carrier', 'packages', 'orderReturn'])
            ->orderByDesc('created_at');

        if ($status !== 'all') {
            $query->where('status', $status);
        }

        if ($provider === 'fedex') {
            $query->where(function ($inner): void {
                $inner->whereNotNull('metadata->fedex')
                    ->orWhereHas('carrierAccount', function ($account): void {
                        $account->where('provider', CarrierAccount::PROVIDER_FEDEX)
                            ->where(function ($modelA): void {
                                $modelA->where('connection_model', CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER)
                                    ->orWhere('fedex_integrator_account', true);
                            });
                    });
            });
        } elseif ($provider === 'usps') {
            $query->whereHas('carrierAccount', function ($account): void {
                $account->where('provider', CarrierAccount::PROVIDER_USPS);
            });
        } elseif ($provider === 'manual') {
            $query->whereNull('metadata->fedex')
                ->where(function ($inner): void {
                    $inner->whereDoesntHave('carrierAccount')
                        ->orWhereHas('carrierAccount', function ($account): void {
                            $account->where('provider', CarrierAccount::PROVIDER_MANUAL)
                                ->orWhere(function ($notFedExIntegrator): void {
                                    $notFedExIntegrator
                                        ->where('provider', '!=', CarrierAccount::PROVIDER_FEDEX)
                                        ->where('provider', '!=', CarrierAccount::PROVIDER_USPS);
                                });
                        });
                });
        }

        if ($search !== '') {
            $query->where(function ($inner) use ($search): void {
                $inner->where('shipment_number', 'like', '%'.$search.'%')
                    ->orWhere('tracking_number', 'like', '%'.$search.'%')
                    ->orWhereHas('order', function ($orderQuery) use ($search): void {
                        $orderQuery->where('order_number', 'like', '%'.$search.'%')
                            ->orWhere('external_order_number', 'like', '%'.$search.'%');
                    });
            });
        }

        $shipments = $query
            ->paginate(20)
            ->withQueryString();

        // Prepare public tracking tokens in the controller — never mutate from Blade.
        foreach ($shipments as $shipment) {
            if ($shipment->isFedExManagedShipment() && filled($shipment->tracking_number)) {
                $shipment->ensureFedExPublicTrackingToken();
            }
        }

        return view('user_view.shipments.index', [
            'shipments' => $shipments,
            'selectedStore' => $store,
            'currentStatus' => $status,
            'currentProvider' => $provider,
            'search' => $search,
            'shipmentStatuses' => OrderLifecycle::shipmentStatuses(),
            'canManageOrders' => $request->user()?->canManageOrders($store) ?? false,
        ]);
    }
}
