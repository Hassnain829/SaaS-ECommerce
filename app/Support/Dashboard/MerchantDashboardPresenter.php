<?php

namespace App\Support\Dashboard;

use App\Models\CarrierAccount;
use App\Models\Customer;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Shipment;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Operations\FedExOperationGuard;
use App\Services\Currency\ReportingMoneyConverter;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Payments\StripeConfig;
use App\Support\MoneyDisplay;
use App\Support\OrderLifecycle;
use App\Support\PlatformPaymentMode;
use App\Support\ReturnLifecycle;
use App\Support\StorePermission;
use Illuminate\Support\Carbon;

final class MerchantDashboardPresenter
{
    public const RANGES = ['today', '7', '30'];

    public const DEFAULT_RANGE = '30';

    /**
     * @return array<string, mixed>
     */
    public static function forStore(?Store $store, string $range, ?User $user): array
    {
        $range = self::normalizeRange($range);

        if (! $store instanceof Store) {
            return [
                'has_store' => false,
                'range' => $range,
            ];
        }

        return (new self($store, $range, $user))->toArray();
    }

    public static function normalizeRange(string $range): string
    {
        return in_array($range, self::RANGES, true) ? $range : self::DEFAULT_RANGE;
    }

    public function __construct(
        private readonly Store $store,
        private readonly string $range,
        private readonly ?User $user,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $store = $this->store;
        $storeId = (int) $store->id;
        $timezone = $this->storeTimezone();
        $now = Carbon::now($timezone);
        $windows = $this->periodWindows($now);
        $currency = strtoupper((string) ($store->currency ?: 'USD'));
        $reporting = app(ReportingMoneyConverter::class);

        $permissions = $this->permissions();
        $zeroTotals = ['revenue' => 0.0, 'orders' => 0];
        $currentTotals = $permissions['orders_view']
            ? $this->revenueTotals($storeId, $windows['current_start_utc'], $windows['current_end_utc'], $currency, $reporting)
            : $zeroTotals;
        $previousTotals = $permissions['orders_view']
            ? $this->revenueTotals($storeId, $windows['previous_start_utc'], $windows['previous_end_utc'], $currency, $reporting)
            : $zeroTotals;

        $newCustomers = $permissions['customers_view']
            ? Customer::query()
                ->where('store_id', $storeId)
                ->whereBetween('created_at', [$windows['current_start_utc'], $windows['current_end_utc']])
                ->count()
            : 0;
        $previousCustomers = $permissions['customers_view']
            ? Customer::query()
                ->where('store_id', $storeId)
                ->whereBetween('created_at', [$windows['previous_start_utc'], $windows['previous_end_utc']])
                ->count()
            : 0;

        $currentAov = $currentTotals['orders'] > 0
            ? $currentTotals['revenue'] / $currentTotals['orders']
            : 0.0;
        $previousAov = $previousTotals['orders'] > 0
            ? $previousTotals['revenue'] / $previousTotals['orders']
            : 0.0;

        $setup = $this->setupProgress($store, $permissions);
        $setupComplete = $setup['complete'];
        $attention = $this->attention($storeId, $permissions);
        $inventoryWatch = $permissions['catalog_view'] ? $this->inventoryWatch($storeId, $permissions) : [];
        $welcome = $this->welcomeCopy($now);

        $metrics = [];
        if ($permissions['orders_view']) {
            $metrics['revenue'] = [
                'label' => 'Revenue',
                'value' => $currentTotals['revenue'],
                'display' => MoneyDisplay::format($currentTotals['revenue'], $currency),
                'change' => $this->percentChange($currentTotals['revenue'], $previousTotals['revenue']),
            ];
            $metrics['orders'] = [
                'label' => 'Orders',
                'value' => $currentTotals['orders'],
                'display' => number_format($currentTotals['orders']),
                'change' => $this->percentChange((float) $currentTotals['orders'], (float) $previousTotals['orders']),
            ];
            $metrics['average_order'] = [
                'label' => 'Average order',
                'value' => $currentAov,
                'display' => MoneyDisplay::format($currentAov, $currency),
                'change' => $this->percentChange($currentAov, $previousAov),
            ];
        }
        if ($permissions['customers_view']) {
            $metrics['new_customers'] = [
                'label' => 'New customers',
                'value' => $newCustomers,
                'display' => number_format($newCustomers),
                'change' => $this->percentChange((float) $newCustomers, (float) $previousCustomers),
            ];
        }

        return [
            'has_store' => true,
            'store' => $store,
            'range' => $this->range,
            'timezone' => $timezone,
            'currency' => $currency,
            'greeting' => $welcome['heading'],
            'greeting_lead' => $welcome['lead'],
            'range_description' => $permissions['orders_view'] || $permissions['customers_view']
                ? $this->comparisonCopy(
                    $windows,
                    $currentTotals,
                    $previousTotals,
                    $newCustomers,
                    $previousCustomers,
                )
                : 'Your store at a glance.',
            'setup_progress' => $setup,
            'setup_complete' => $setupComplete,
            'permissions' => $permissions,
            'metrics' => $metrics,
            'chart' => $permissions['orders_view']
                ? $this->chart($storeId, $timezone, $currency, $reporting, $windows)
                : [
                    'labels' => [],
                    'current' => [],
                    'previous' => [],
                    'current_formatted' => [],
                    'empty' => true,
                ],
            'attention' => $attention,
            'order_flow' => $this->orderFlow($storeId, $permissions, $attention['fulfillment']['count']),
            'recent_orders' => $this->recentOrders($storeId, $timezone, $currency, $permissions),
            'inventory_watch' => $inventoryWatch,
            'systems' => $this->systems($store, $permissions),
        ];
    }

    /**
     * @return array{current_start: Carbon, current_end: Carbon, previous_start: Carbon, previous_end: Carbon, current_start_utc: Carbon, current_end_utc: Carbon, previous_start_utc: Carbon, previous_end_utc: Carbon, compare_label: string}
     */
    private function periodWindows(Carbon $now): array
    {
        $currentEnd = $now->copy();

        if ($this->range === 'today') {
            $currentStart = $now->copy()->startOfDay();
            $previousStart = $currentStart->copy()->subDay();
            $previousEnd = $currentEnd->copy()->subDay();
            $compareLabel = 'Compared with yesterday';
        } elseif ($this->range === '7') {
            $currentStart = $now->copy()->startOfDay()->subDays(6);
            $span = $currentStart->diffInSeconds($currentEnd);
            $previousEnd = $currentStart->copy()->subSecond();
            $previousStart = $previousEnd->copy()->subSeconds($span);
            $compareLabel = 'Compared with the previous 7 days';
        } else {
            $currentStart = $now->copy()->startOfDay()->subDays(29);
            $span = $currentStart->diffInSeconds($currentEnd);
            $previousEnd = $currentStart->copy()->subSecond();
            $previousStart = $previousEnd->copy()->subSeconds($span);
            $compareLabel = 'Compared with the previous 30 days';
        }

        return [
            'current_start' => $currentStart,
            'current_end' => $currentEnd,
            'previous_start' => $previousStart,
            'previous_end' => $previousEnd,
            'current_start_utc' => $currentStart->copy()->utc(),
            'current_end_utc' => $currentEnd->copy()->utc(),
            'previous_start_utc' => $previousStart->copy()->utc(),
            'previous_end_utc' => $previousEnd->copy()->utc(),
            'compare_label' => $compareLabel,
        ];
    }

    /**
     * @return array{revenue: float, orders: int}
     */
    private function revenueTotals(
        int $storeId,
        Carbon $startUtc,
        Carbon $endUtc,
        string $storeCurrency,
        ReportingMoneyConverter $reporting,
    ): array {
        $rows = $this->revenueQuery($storeId, $startUtc, $endUtc)
            ->selectRaw('currency_code, COUNT(*) as order_count, SUM(grand_total) as revenue')
            ->groupBy('currency_code')
            ->get();

        $revenue = 0.0;
        $orders = 0;
        foreach ($rows as $row) {
            $orders += (int) $row->order_count;
            $revenue += $reporting->convert(
                $row->revenue,
                (string) ($row->currency_code ?: 'USD'),
                $storeCurrency
            );
        }

        return [
            'revenue' => $revenue,
            'orders' => $orders,
        ];
    }

    private function revenueQuery(int $storeId, Carbon $startUtc, Carbon $endUtc)
    {
        return Order::query()
            ->where('store_id', $storeId)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->where(function ($query) use ($startUtc, $endUtc): void {
                $query->where(function ($inner) use ($startUtc, $endUtc): void {
                    $inner->whereNotNull('placed_at')->whereBetween('placed_at', [$startUtc, $endUtc]);
                })->orWhere(function ($inner) use ($startUtc, $endUtc): void {
                    $inner->whereNull('placed_at')->whereBetween('created_at', [$startUtc, $endUtc]);
                });
            });
    }

    /**
     * @param  array<string, mixed>  $windows
     * @return array{labels: list<string>, current: list<float>, previous: list<float>, current_formatted: list<string>, empty: bool}
     */
    private function chart(
        int $storeId,
        string $timezone,
        string $currency,
        ReportingMoneyConverter $reporting,
        array $windows,
    ): array {
        $currentBuckets = $this->emptyBuckets($windows['current_start'], $windows['current_end'], $timezone);
        $previousBuckets = $this->emptyBuckets($windows['previous_start'], $windows['previous_end'], $timezone);
        $orders = $this->revenueQuery($storeId, $windows['previous_start_utc'], $windows['current_end_utc'])
            ->get(['placed_at', 'created_at', 'grand_total', 'currency_code']);

        $current = array_fill_keys(array_keys($currentBuckets), 0.0);
        $previous = array_fill_keys(array_keys($previousBuckets), 0.0);

        foreach ($orders as $order) {
            $occurred = $order->placed_at ?? $order->created_at;
            if (! $occurred) {
                continue;
            }
            $local = $occurred->copy()->timezone($timezone);
            $converted = $reporting->convert(
                $order->grand_total,
                (string) ($order->currency_code ?: 'USD'),
                $currency
            );

            $currentKey = $this->bucketKey($local, $windows['current_start'], $windows['current_end']);
            if ($currentKey !== null && array_key_exists($currentKey, $current)) {
                $current[$currentKey] += $converted;

                continue;
            }

            $previousKey = $this->bucketKey($local, $windows['previous_start'], $windows['previous_end']);
            if ($previousKey !== null && array_key_exists($previousKey, $previous)) {
                $previous[$previousKey] += $converted;
            }
        }

        $labels = array_values($currentBuckets);
        $currentValues = array_values($current);
        $previousValues = array_values($previous);
        if (count($previousValues) < count($currentValues)) {
            $previousValues = array_pad($previousValues, count($currentValues), 0.0);
        } elseif (count($previousValues) > count($currentValues)) {
            $previousValues = array_slice($previousValues, -count($currentValues));
        }
        $formatted = array_map(
            static fn (float $value): string => MoneyDisplay::format($value, $currency),
            $currentValues
        );

        $empty = max($currentValues ?: [0.0]) <= 0.0 && max($previousValues ?: [0.0]) <= 0.0;

        return [
            'labels' => $labels,
            'current' => $currentValues,
            'previous' => $previousValues,
            'current_formatted' => $formatted,
            'empty' => $empty,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function emptyBuckets(Carbon $currentStart, Carbon $currentEnd, string $timezone): array
    {
        $buckets = [];

        if ($this->range === 'today') {
            $cursor = $currentStart->copy()->timezone($timezone)->startOfHour();
            $last = $currentEnd->copy()->timezone($timezone)->startOfHour();
            while ($cursor->lte($last)) {
                $buckets[$cursor->format('Y-m-d H')] = strtolower($cursor->format('ga'));
                $cursor->addHour();
            }

            return $buckets !== [] ? $buckets : [$currentStart->format('Y-m-d H') => strtolower($currentStart->format('ga'))];
        }

        $cursor = $currentStart->copy()->timezone($timezone)->startOfDay();
        $last = $currentEnd->copy()->timezone($timezone)->startOfDay();
        while ($cursor->lte($last)) {
            $buckets[$cursor->format('Y-m-d')] = $cursor->format('M j');
            $cursor->addDay();
        }

        return $buckets;
    }

    private function bucketKey(Carbon $local, Carbon $windowStart, Carbon $windowEnd): ?string
    {
        if ($local->lt($windowStart) || $local->gt($windowEnd)) {
            return null;
        }

        return $this->range === 'today'
            ? $local->format('Y-m-d H')
            : $local->format('Y-m-d');
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    private function setupProgress(Store $store, array $permissions): array
    {
        $activeLocationsCount = $store->locations()->where('is_active', true)->count();
        $activeDeliveryAreasCount = $store->shippingZones()->where('is_active', true)->count();
        $checkoutDeliveryOptionsCount = $store->shippingMethods()
            ->where('is_active', true)
            ->where('enabled_for_checkout', true)
            ->count();
        $taxSetting = $store->taxSetting()->first();
        $taxRatesCount = $store->taxRates()->where('is_active', true)->count();
        $taxReady = (bool) ($taxSetting?->enabled) && $taxRatesCount > 0;
        $locationReady = $activeLocationsCount > 0;
        $deliveryReady = $activeDeliveryAreasCount > 0 && $checkoutDeliveryOptionsCount > 0;
        $paymentReady = ! empty($permissions['payments_view']) && $this->paymentSetupReady($store);
        $websiteReady = $store->websiteConnectionState() === Store::WEBSITE_CONNECTED;
        $websiteHref = ! empty($permissions['developer_api_view'])
            ? route('developer-storefront.settings')
            : null;

        $steps = [];
        if (! empty($permissions['locations_manage']) || ! empty($permissions['settings_pages_view'])) {
            $steps[] = [
                'key' => 'location',
                'title' => 'Store location',
                'short_title' => 'Location',
                'description' => 'Add an active ship-from location for inventory and fulfillment.',
                'next_description' => 'Add a location so inventory and shipments have a valid origin.',
                'ready' => $locationReady,
                'href' => route('settings.locations.index'),
                'cta' => $locationReady ? 'Manage locations' : 'Add location',
            ];
        }
        if (! empty($permissions['delivery_manage']) || ! empty($permissions['settings_pages_view'])) {
            $steps[] = [
                'key' => 'delivery',
                'title' => 'Delivery setup',
                'short_title' => 'Delivery',
                'description' => 'Create a delivery area and make one checkout option available.',
                'next_description' => 'Create a delivery area and enable an option customers can select.',
                'ready' => $deliveryReady,
                'href' => route('shippingAutomation'),
                'cta' => $deliveryReady ? 'Review delivery' : 'Set up delivery',
            ];
        }
        if (! empty($permissions['payments_view'])) {
            $steps[] = [
                'key' => 'payment',
                'title' => 'Payment setup',
                'short_title' => 'Payments',
                'description' => 'Connect Stripe so customers can pay at checkout.',
                'next_description' => 'Connect Stripe so customers can pay at checkout.',
                'ready' => $paymentReady,
                'href' => route('settings.payments.index'),
                'cta' => $paymentReady ? 'Review payments' : 'Connect payments',
            ];
        }
        if (! empty($permissions['taxes_manage']) || ! empty($permissions['settings_pages_view'])) {
            $steps[] = [
                'key' => 'tax',
                'title' => 'Checkout tax',
                'short_title' => 'Tax',
                'description' => 'Turn on tax calculation and add at least one active tax rate.',
                'next_description' => 'Configure checkout tax so order totals are calculated correctly.',
                'ready' => $taxReady,
                'href' => route('settings.taxes.index'),
                'cta' => $taxReady ? 'Review tax' : 'Configure tax',
            ];
        }
        if (! empty($permissions['developer_api_view'])) {
            $steps[] = [
                'key' => 'website',
                'title' => 'Website connection',
                'short_title' => 'Website',
                'description' => 'Connect your website so customers can browse and check out.',
                'next_description' => 'Connect your website so customers can browse and check out.',
                'ready' => $websiteReady,
                'href' => $websiteHref,
                'cta' => $websiteReady ? 'Review website' : 'Connect website',
            ];
        }

        $readyCount = collect($steps)->where('ready', true)->count();
        $totalCount = count($steps);
        $next = collect($steps)->firstWhere('ready', false);

        return [
            'location' => ['ready' => $locationReady, 'count' => $activeLocationsCount],
            'tax' => ['ready' => $taxReady, 'count' => $taxRatesCount],
            'delivery' => [
                'ready' => $deliveryReady,
                'areas_count' => $activeDeliveryAreasCount,
                'options_count' => $checkoutDeliveryOptionsCount,
            ],
            'payment' => ['ready' => $paymentReady],
            'website' => ['ready' => $websiteReady],
            'steps' => $steps,
            'ready_count' => $readyCount,
            'total_count' => $totalCount,
            'remaining_count' => $totalCount - $readyCount,
            'progress_percent' => $totalCount > 0 ? round(($readyCount / $totalCount) * 100, 1) : 0.0,
            'complete' => $readyCount === $totalCount,
            'next' => $next,
        ];
    }

    private function paymentSetupReady(Store $store): bool
    {
        return app(PaymentProviderManager::class)->isCheckoutReady($store);
    }

    /**
     * @return array<string, bool>
     */
    private function permissions(): array
    {
        $user = $this->user;
        $store = $this->store;

        return [
            'orders_view' => (bool) $user?->hasAnyStorePermission($store, ['orders.view', StorePermission::ORDERS_VIEW]),
            'orders_manage' => (bool) $user?->hasAnyStorePermission($store, ['orders.edit', StorePermission::ORDERS_MANAGE]),
            'orders_draft' => (bool) $user?->hasStorePermission($store, 'orders.draft'),
            'catalog_view' => (bool) $user?->hasAnyStorePermission($store, ['products.view', StorePermission::CATALOG_VIEW]),
            'catalog_manage' => (bool) $user?->hasAnyStorePermission($store, ['products.edit', StorePermission::CATALOG_MANAGE]),
            'customers_view' => (bool) $user?->hasAnyStorePermission($store, ['customers.view', StorePermission::CUSTOMERS_VIEW]),
            'locations_manage' => (bool) $user?->hasStorePermission($store, 'settings.locations'),
            'delivery_manage' => (bool) $user?->hasStorePermission($store, 'settings.delivery'),
            'taxes_manage' => (bool) $user?->hasStorePermission($store, 'settings.taxes'),
            'carriers_manage' => (bool) $user?->hasStorePermission($store, 'settings.carriers'),
            'settings_manage' => (bool) $user?->hasStorePermission($store, StorePermission::SETTINGS_MANAGE),
            'settings_pages_view' => (bool) $user?->hasAnyStorePermission($store, [
                StorePermission::SETTINGS_VIEW,
                StorePermission::SETTINGS_MANAGE,
            ]),
            'settings_view' => (bool) $user?->hasAnyStorePermission($store, [
                'settings.locations',
                'settings.delivery',
                'settings.taxes',
                StorePermission::SETTINGS_VIEW,
                StorePermission::SETTINGS_MANAGE,
            ]),
            'developer_api_view' => (bool) $user?->hasAnyStorePermission($store, ['website.view', StorePermission::DEVELOPER_API_VIEW]),
            'payments_view' => (bool) $user?->hasStorePermission($store, 'settings.payments'),
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    private function attention(int $storeId, array $permissions): array
    {
        $fulfillmentCount = $permissions['orders_view']
            ? Order::query()
                ->where('store_id', $storeId)
                ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
                ->where('payment_status', OrderLifecycle::PAYMENT_PAID)
                ->whereIn('fulfillment_status', [
                    OrderLifecycle::FULFILLMENT_UNFULFILLED,
                    OrderLifecycle::FULFILLMENT_PARTIAL,
                ])
                ->count()
            : 0;

        $lowStockCount = $permissions['catalog_view']
            ? Product::query()
                ->where('store_id', $storeId)
                ->whereHas('variants', function ($query): void {
                    $query->whereColumn('stock', '<=', 'stock_alert')
                        ->where('stock', '>', 0)
                        ->where('stock_alert', '>', 0);
                })
                ->count()
            : 0;

        $returnsCount = $permissions['orders_view']
            ? OrderReturn::query()
                ->where('store_id', $storeId)
                ->where('status', ReturnLifecycle::STATUS_REQUESTED)
                ->count()
            : 0;

        $canSeeLocationIssues = ! empty($permissions['locations_manage']) || ! empty($permissions['settings_pages_view']);
        $canSeeDeliveryHubIssues = ! empty($permissions['delivery_manage'])
            || ! empty($permissions['carriers_manage'])
            || ! empty($permissions['settings_pages_view']);
        $canSeeFailedShipments = $permissions['orders_view'];

        $incompleteAddressCount = 0;
        $originAttentionCount = 0;
        if ($canSeeLocationIssues) {
            $activeLocations = Location::query()
                ->where('store_id', $storeId)
                ->where('is_active', true)
                ->get(['id', 'address_line1', 'city', 'country_code']);

            $incompleteAddressCount = $activeLocations->filter(function (Location $location): bool {
                return ! filled($location->address_line1)
                    || ! filled($location->city)
                    || ! filled($location->country_code);
            })->count();
        }
        if ($canSeeDeliveryHubIssues) {
            $originAttentionCount = CarrierAccount::query()
                ->where('store_id', $storeId)
                ->where('origin_validation_status', CarrierAccount::ORIGIN_VALIDATION_NEEDS_ATTENTION)
                ->count();
        }

        $failedShipmentCount = $canSeeFailedShipments
            ? Shipment::query()
                ->where('store_id', $storeId)
                ->where('status', Shipment::STATUS_FAILED)
                ->count()
            : 0;

        $deliveryIssueCount = $incompleteAddressCount + $failedShipmentCount + $originAttentionCount;
        $deliveryHref = null;
        $deliveryLabel = 'Delivery setup needs attention';
        if ($incompleteAddressCount > 0 && $canSeeLocationIssues) {
            $deliveryHref = route('settings.locations.index');
            $deliveryLabel = 'Address needs attention';
        } elseif ($failedShipmentCount > 0 && $canSeeFailedShipments) {
            $deliveryHref = route('shipments.index', ['status' => OrderLifecycle::SHIPMENT_FAILED]);
            $deliveryLabel = 'Shipment needs attention';
        } elseif ($canSeeDeliveryHubIssues) {
            $deliveryHref = route('shippingAutomation');
        }

        $ordersHref = route('orders');
        $productsLowHref = route('products', ['stock' => 'low']);

        return [
            'fulfillment' => [
                'count' => $fulfillmentCount,
                'href' => $permissions['orders_view'] ? $ordersHref : null,
            ],
            'low_stock' => [
                'count' => $lowStockCount,
                'href' => $permissions['catalog_view'] ? $productsLowHref : null,
            ],
            'returns' => [
                'count' => $returnsCount,
                'href' => $permissions['orders_view'] ? $ordersHref : null,
            ],
            'delivery' => [
                'count' => $deliveryIssueCount,
                'label' => $deliveryLabel,
                'href' => $deliveryHref,
            ],
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    private function orderFlow(int $storeId, array $permissions, int $readyToShip): array
    {
        $ordersView = $permissions['orders_view'];
        if (! $ordersView) {
            return [
                'rows' => [],
                'max' => 1,
                'ship_now' => 0,
                'ship_now_href' => null,
            ];
        }

        $paid = Order::query()
            ->where('store_id', $storeId)
            ->whereNotIn('status', [Order::STATUS_CANCELLED, Order::STATUS_REFUNDED])
            ->where('payment_status', OrderLifecycle::PAYMENT_PAID)
            ->count();

        $processing = Order::query()
            ->where('store_id', $storeId)
            ->where('status', Order::STATUS_PROCESSING)
            ->count();

        $inTransit = Order::query()
            ->where('store_id', $storeId)
            ->whereHas('shipments', function ($query): void {
                $query->where('status', Shipment::STATUS_IN_TRANSIT);
            })
            ->count();

        $ordersView = $permissions['orders_view'];

        $rows = [
            [
                'key' => 'paid',
                'label' => 'Paid',
                'count' => $paid,
                'href' => $ordersView ? route('orders') : null,
            ],
            [
                'key' => 'processing',
                'label' => 'Processing',
                'count' => $processing,
                'href' => $ordersView ? route('orders', ['status' => Order::STATUS_PROCESSING]) : null,
            ],
            [
                'key' => 'ready_to_ship',
                'label' => 'Ready to ship',
                'count' => $readyToShip,
                'href' => $ordersView ? route('orders') : null,
            ],
            [
                'key' => 'in_transit',
                'label' => 'In transit',
                'count' => $inTransit,
                'href' => $ordersView ? route('shipments.index', ['status' => OrderLifecycle::SHIPMENT_IN_TRANSIT]) : null,
            ],
        ];

        $max = max(array_map(static fn (array $row): int => $row['count'], $rows) ?: [0]);

        return [
            'rows' => $rows,
            'max' => max(1, $max),
            'ship_now' => $readyToShip,
            'ship_now_href' => $ordersView ? route('orders') : null,
        ];
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<array<string, mixed>>
     */
    private function recentOrders(int $storeId, string $timezone, string $storeCurrency, array $permissions): array
    {
        if (! $permissions['orders_view']) {
            return [];
        }

        $orders = Order::query()
            ->where('store_id', $storeId)
            ->orderByDesc('placed_at')
            ->orderByDesc('created_at')
            ->limit(4)
            ->get([
                'id',
                'order_number',
                'order_source',
                'payment_status',
                'fulfillment_status',
                'grand_total',
                'currency_code',
                'placed_at',
                'created_at',
            ]);

        return $orders->map(function (Order $order) use ($timezone, $storeCurrency, $permissions): array {
            $occurred = $order->placed_at ?? $order->created_at;
            $currency = (string) ($order->currency_code ?: $storeCurrency);

            return [
                'id' => $order->id,
                'number' => $order->order_number ?: ('#'.$order->id),
                'source' => $this->sourceLabel($order->order_source),
                'payment' => OrderLifecycle::paymentStatusLabel($order->payment_status),
                'payment_tone' => $this->statusTone($order->payment_status, 'payment'),
                'fulfillment' => OrderLifecycle::fulfillmentStatusLabel($order->fulfillment_status),
                'fulfillment_tone' => $this->statusTone($order->fulfillment_status, 'fulfillment'),
                'total' => MoneyDisplay::format($order->grand_total, $currency),
                'time' => $occurred ? $occurred->copy()->timezone($timezone)->diffForHumans() : '—',
                'href' => $permissions['orders_view'] ? route('orderViewDetails', $order) : null,
            ];
        })->all();
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return list<array<string, mixed>>
     */
    private function inventoryWatch(int $storeId, array $permissions): array
    {
        $variants = ProductVariant::query()
            ->where('store_id', $storeId)
            ->where('stock', '>', 0)
            ->where('stock_alert', '>', 0)
            ->whereColumn('stock', '<=', 'stock_alert')
            ->whereHas('product', fn ($query) => $query->where('store_id', $storeId))
            ->with([
                'product:id,store_id,name',
                'linkedCatalogImage:id,image_path,status',
                'options.variationType:id,name',
            ])
            ->orderBy('stock')
            ->orderBy('id')
            ->limit(5)
            ->get(['id', 'product_id', 'sku', 'stock', 'stock_alert', 'product_image_id']);

        return $variants->map(function (ProductVariant $variant) use ($permissions): array {
            $product = $variant->product;
            $stock = (int) $variant->stock;
            $alert = max(1, (int) $variant->stock_alert);
            $image = $variant->linkedCatalogImage;
            $imageUrl = $image && $image->isReady()
                ? asset('storage/'.$image->image_path)
                : null;
            $href = null;
            if ($product && $permissions['catalog_manage']) {
                $href = route('products.edit', $product);
            } elseif ($product && $permissions['catalog_view']) {
                $href = route('products.show', $product);
            }

            return [
                'name' => $this->variantDisplayName($variant),
                'sku' => (string) ($variant->sku ?: '—'),
                'stock' => $stock,
                'alert' => $alert,
                'percent' => (int) max(8, min(100, round(($stock / $alert) * 100))),
                'critical' => $stock <= 1,
                'image_url' => $imageUrl,
                'href' => $href,
            ];
        })->all();
    }

    private function variantDisplayName(ProductVariant $variant): string
    {
        $productName = (string) ($variant->product?->name ?: 'Product');
        $options = $variant->options
            ->map(fn ($option): string => trim((string) $option->value))
            ->filter()
            ->values();

        if ($options->isEmpty()) {
            return $productName;
        }

        return $productName.' / '.$options->implode(' / ');
    }

    /**
     * @param  array<string, bool>  $permissions
     * @return array<string, mixed>
     */
    private function systems(Store $store, array $permissions): array
    {
        $websiteState = $store->websiteConnectionState();
        $websiteConnected = $websiteState === Store::WEBSITE_CONNECTED;
        $websiteLabel = match ($websiteState) {
            Store::WEBSITE_CONNECTED => 'Website connected',
            Store::WEBSITE_WAITING => 'Website waiting',
            Store::WEBSITE_DISCONNECTED => 'Website disconnected',
            default => 'Website not connected',
        };

        $items = [];
        if ($permissions['developer_api_view']) {
            $items[] = [
                'key' => 'website',
                'label' => $websiteLabel,
                'ready' => $websiteConnected,
                'href' => route('developer-storefront.settings'),
            ];
        }

        if ($permissions['payments_view']) {
            $payments = app(PaymentProviderManager::class);
            $stripeConfig = app(StripeConfig::class);
            $liveReady = $payments->activeConnectedAccountForStore($store, PlatformPaymentMode::LIVE) !== null;
            $testReady = $payments->activeConnectedAccountForStore($store, PlatformPaymentMode::TEST) !== null;
            $liveConfigured = $stripeConfig->isConnectModeConfigured(PlatformPaymentMode::LIVE);
            $testConfigured = $stripeConfig->isConnectModeConfigured(PlatformPaymentMode::TEST);
            $paymentsUnavailable = ! $liveConfigured && ! $testConfigured;

            if ($paymentsUnavailable) {
                $stripeLabel = 'Payments unavailable';
                $stripeReady = false;
            } elseif ($liveReady) {
                $stripeLabel = 'Stripe live';
                $stripeReady = true;
            } elseif ($testReady) {
                $stripeLabel = 'Stripe test';
                $stripeReady = true;
            } else {
                $stripeLabel = 'Payments not connected';
                $stripeReady = false;
            }

            $items[] = [
                'key' => 'stripe',
                'label' => $stripeLabel,
                'ready' => $stripeReady,
                'href' => route('settings.payments.index'),
            ];
        }

        if (! empty($permissions['delivery_manage']) || ! empty($permissions['carriers_manage']) || ! empty($permissions['settings_pages_view'])) {
            $fedExAccount = app(FedExOperationGuard::class)->resolveActiveModelAAccount($store);
            $fedExReady = $fedExAccount !== null;
            $items[] = [
                'key' => 'fedex',
                'label' => $fedExReady ? 'FedEx ready' : 'FedEx not connected',
                'ready' => $fedExReady,
                'href' => route('shippingAutomation'),
            ];
        }

        if (! empty($permissions['locations_manage']) || ! empty($permissions['settings_pages_view'])) {
            $activeLocations = $store->locations()->where('is_active', true)->count();
            $items[] = [
                'key' => 'locations',
                'label' => $activeLocations === 1 ? '1 active location' : $activeLocations.' active locations',
                'ready' => $activeLocations > 0,
                'href' => route('settings.locations.index'),
            ];
        }

        return [
            'items' => $items,
            'manage_href' => ! empty($permissions['settings_manage']) ? route('generalSettings') : null,
        ];
    }

    /**
     * @param  array{compare_label: string, previous_start: Carbon, previous_end: Carbon}  $windows
     * @param  array{revenue: float, orders: int}  $currentTotals
     * @param  array{revenue: float, orders: int}  $previousTotals
     */
    private function comparisonCopy(
        array $windows,
        array $currentTotals,
        array $previousTotals,
        int $newCustomers,
        int $previousCustomers,
    ): string {
        $previousHadActivity = $previousTotals['orders'] > 0 || $previousCustomers > 0;
        $currentHadActivity = $currentTotals['orders'] > 0 || $newCustomers > 0;

        if ($previousHadActivity || ! $currentHadActivity) {
            return $windows['compare_label'];
        }

        if ($this->range === 'today') {
            return 'Yesterday at this time had no sales';
        }

        $from = $windows['previous_start']->format('M j');
        $to = $windows['previous_end']->format('M j');

        return 'No sales in '.$from.' – '.$to.' to compare';
    }

    /**
     * @return array{direction: string, label: string}
     */
    private function percentChange(float $current, float $previous): array
    {
        // No earlier window to divide by. Do not invent a %, "New", or "vs $0".
        if (abs($previous) < 0.00001) {
            return ['direction' => 'flat', 'label' => ''];
        }

        $percent = round((($current - $previous) / abs($previous)) * 100, 1);
        if ($percent > 0) {
            return ['direction' => 'up', 'label' => '↑ '.number_format(abs($percent), 1).'%'];
        }
        if ($percent < 0) {
            return ['direction' => 'down', 'label' => '↓ '.number_format(abs($percent), 1).'%'];
        }

        return ['direction' => 'flat', 'label' => '→ 0.0%'];
    }

    /**
     * Store-local time of day. Night hours are not called morning.
     *
     * @return array{heading: string, lead: string}
     */
    private function welcomeCopy(Carbon $now): array
    {
        $hour = (int) $now->format('G');

        if ($hour >= 5 && $hour < 12) {
            return [
                'heading' => 'Good morning',
                'lead' => 'Here’s what needs your attention Today!',
            ];
        }

        if ($hour >= 12 && $hour < 17) {
            return [
                'heading' => 'Good afternoon',
                'lead' => 'Here’s what needs your attention !',
            ];
        }

        if ($hour >= 17 && $hour < 22) {
            return [
                'heading' => 'Good evening',
                'lead' => 'Here’s what needs your attention!',
            ];
        }

        return [
            'heading' => 'Hi, '.trim((string) ($this->user?->name ?: 'there')).'!',
            'lead' => 'Here’s what needs your attention right now!',
        ];
    }

    private function storeTimezone(): string
    {
        $timezone = trim((string) ($this->store->timezone ?: 'UTC'));

        try {
            Carbon::now($timezone);

            return $timezone !== '' ? $timezone : 'UTC';
        } catch (\Throwable) {
            return 'UTC';
        }
    }

    private function sourceLabel(?string $source): string
    {
        return match ($source) {
            'external_checkout' => 'External checkout',
            'platform_checkout' => 'Website',
            'developer_storefront' => 'Website',
            'manual' => 'Manual',
            default => $source ? str($source)->replace('_', ' ')->title()->toString() : 'Manual',
        };
    }

    private function statusTone(?string $status, string $kind): string
    {
        if ($kind === 'payment') {
            return match ($status) {
                OrderLifecycle::PAYMENT_PAID, OrderLifecycle::PAYMENT_AUTHORIZED => 'green',
                OrderLifecycle::PAYMENT_PENDING => 'amber',
                default => 'gray',
            };
        }

        return match ($status) {
            OrderLifecycle::FULFILLMENT_FULFILLED => 'green',
            OrderLifecycle::FULFILLMENT_UNFULFILLED, OrderLifecycle::FULFILLMENT_PARTIAL => 'amber',
            OrderLifecycle::SHIPMENT_IN_TRANSIT => 'blue',
            default => 'gray',
        };
    }
}
