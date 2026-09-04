<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CarrierAccount;
use App\Models\CarrierApiEvent;
use App\Models\FedExTradeDocument;
use App\Models\IdempotencyKey;
use App\Models\Shipment;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Admin-only FedEx operations console — no credentials or raw payloads.
 */
class FedExAdminDiagnosticsController extends Controller
{
    private const TABS = [
        'overview',
        'connections',
        'api-events',
        'shipments',
        'trade-documents',
    ];

    public function index(Request $request): View
    {
        $tab = $this->resolveTab($request->query('tab', 'overview'));

        return view('admin.fedex.index', array_merge(
            ['tab' => $tab, 'tabs' => self::TABS],
            $this->buildPageData($request, $tab),
        ));
    }

    public function diagnosticsRedirect(): RedirectResponse
    {
        return redirect()->route('admin.fedex.index', request()->query());
    }

    private function resolveTab(?string $tab): string
    {
        return in_array($tab, self::TABS, true) ? $tab : 'overview';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPageData(Request $request, string $tab): array
    {
        $flags = [
            'production' => (bool) config('carriers.fedex.integrator_production_enabled'),
            'checkout_rates' => (bool) config('carriers.fedex.checkout_rates_enabled'),
            'ship_labels' => (bool) config('carriers.fedex.ops_ship_labels_enabled'),
            'tracking' => (bool) config('carriers.fedex.ops_tracking_enabled'),
        ];

        return match ($tab) {
            'connections' => [
                'flags' => $flags,
                'connections' => $this->fedExConnections(),
            ],
            'api-events' => [
                'flags' => $flags,
                'apiEvents' => $this->fedExApiEvents($request),
                'eventFilters' => $this->apiEventFilterOptions(),
                'activeEventFilters' => [
                    'status' => (string) $request->query('status', ''),
                    'action' => (string) $request->query('action', ''),
                ],
            ],
            'shipments' => [
                'flags' => $flags,
                'uncertainShipOps' => $this->uncertainShipOperations(),
                'recentShipments' => $this->recentFedExShipments(),
            ],
            'trade-documents' => [
                'flags' => $flags,
                'etdDocs' => $this->tradeDocuments(),
            ],
            default => [
                'flags' => $flags,
                'accountCounts' => $this->fedExAccountCounts(),
                'recentSuccessEvents' => $this->recentSuccessEvents(),
                'failedConnections' => $this->failedConnections(),
            ],
        };
    }

    /**
     * @return array<string, int>
     */
    private function fedExAccountCounts(): array
    {
        $counts = CarrierAccount::query()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->selectRaw('connection_status, COUNT(*) as total')
            ->groupBy('connection_status')
            ->pluck('total', 'connection_status');

        return [
            'total' => (int) $counts->sum(),
            'connected' => (int) ($counts[CarrierAccount::CONNECTION_CONNECTED] ?? 0),
            'failed' => (int) (($counts[CarrierAccount::CONNECTION_FAILED] ?? 0) + ($counts[CarrierAccount::CONNECTION_BLOCKED_BY_FEDEX] ?? 0)),
            'pending_validation' => (int) ($counts[CarrierAccount::CONNECTION_PENDING_VALIDATION] ?? 0),
            'setup_required' => (int) ($counts[CarrierAccount::CONNECTION_SETUP_REQUIRED] ?? 0),
        ];
    }

    /**
     * @return Collection<int, CarrierApiEvent>
     */
    private function recentSuccessEvents()
    {
        return CarrierApiEvent::query()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->where('status', CarrierApiEvent::STATUS_SUCCEEDED)
            ->orderByDesc('id')
            ->limit(25)
            ->get([
                'id', 'store_id', 'carrier_account_id', 'action', 'status',
                'fedex_transaction_id', 'duration_ms', 'created_at',
            ]);
    }

    /**
     * @return Collection<int, CarrierAccount>
     */
    private function failedConnections()
    {
        return CarrierAccount::query()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->whereIn('connection_status', [
                CarrierAccount::CONNECTION_FAILED,
                CarrierAccount::CONNECTION_BLOCKED_BY_FEDEX,
            ])
            ->orderByDesc('updated_at')
            ->limit(10)
            ->get(['id', 'store_id', 'display_name', 'environment', 'connection_status', 'last_error_message', 'updated_at']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fedExConnections(): array
    {
        return CarrierAccount::query()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->orderByDesc('updated_at')
            ->limit(50)
            ->get([
                'id', 'store_id', 'display_name', 'environment', 'connection_status',
                'connection_mode', 'connection_owner', 'provider_account_last4',
                'provider_account_number', 'last_error_message', 'last_verified_at', 'updated_at',
            ])
            ->map(fn (CarrierAccount $account): array => [
                'id' => $account->id,
                'store_id' => $account->store_id,
                'display_name' => $account->display_name,
                'environment' => $account->environment,
                'connection_status' => $account->connection_status,
                'connection_mode' => $account->connection_mode,
                'connection_owner' => $account->connection_owner,
                'masked_account' => $account->maskedAccountNumber(),
                'last_error_message' => Str::limit((string) $account->last_error_message, 120),
                'last_verified_at' => $account->last_verified_at,
                'updated_at' => $account->updated_at,
            ])
            ->all();
    }

    /**
     * @return LengthAwarePaginator<CarrierApiEvent>
     */
    private function fedExApiEvents(Request $request)
    {
        $query = CarrierApiEvent::query()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->orderByDesc('id');

        if ($status = trim((string) $request->query('status', ''))) {
            $query->where('status', $status);
        }

        if ($action = trim((string) $request->query('action', ''))) {
            $query->where('action', $action);
        }

        return $query->paginate(40, [
            'id', 'store_id', 'carrier_account_id', 'action', 'status', 'scenario_key',
            'test_case_key', 'http_status', 'error_code', 'error_message',
            'fedex_transaction_id', 'duration_ms', 'response_summary', 'created_at',
        ])->withQueryString();
    }

    /**
     * @return array{statuses: list<string>, actions: list<string>}
     */
    private function apiEventFilterOptions(): array
    {
        $base = CarrierApiEvent::query()->where('provider', CarrierAccount::PROVIDER_FEDEX);

        return [
            'statuses' => (clone $base)
                ->distinct()
                ->orderBy('status')
                ->pluck('status')
                ->filter()
                ->values()
                ->all(),
            'actions' => (clone $base)
                ->distinct()
                ->orderBy('action')
                ->pluck('action')
                ->filter()
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, IdempotencyKey>
     */
    private function uncertainShipOperations()
    {
        return IdempotencyKey::query()
            ->where('request_path', 'like', '/ship/v1/%')
            ->where(function ($q): void {
                $q->where('response_body->state', 'uncertain')
                    ->orWhere('response_body->state', 'processing');
            })
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'store_id', 'key', 'response_code', 'response_body', 'resource_id', 'updated_at']);
    }

    /**
     * @return Collection<int, Shipment>
     */
    private function recentFedExShipments()
    {
        return Shipment::query()
            ->whereNotNull('tracking_number')
            ->whereHas('carrierAccount', fn ($q) => $q->where('provider', CarrierAccount::PROVIDER_FEDEX))
            ->orderByDesc('id')
            ->limit(25)
            ->get(['id', 'store_id', 'order_id', 'shipment_number', 'tracking_number', 'status', 'carrier_account_id', 'created_at']);
    }

    /**
     * @return Collection<int, FedExTradeDocument>
     */
    private function tradeDocuments()
    {
        return FedExTradeDocument::query()
            ->orderByDesc('id')
            ->limit(40)
            ->get([
                'id', 'store_id', 'order_id', 'shipment_id', 'document_type', 'status',
                'fedex_document_id', 'origin_country_code', 'destination_country_code',
                'uploaded_at', 'created_at',
            ]);
    }
}
