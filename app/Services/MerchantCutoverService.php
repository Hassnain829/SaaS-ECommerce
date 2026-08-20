<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\ConnectedSite;
use App\Models\ConnectedSiteCutover;
use App\Models\InventoryLevel;
use App\Models\Location;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\Store;
use App\Models\User;
use App\Services\Delivery\DeliverySetupStatusService;
use App\Services\Payments\PaymentProviderManager;
use App\Support\OrderLifecycle;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MerchantCutoverService
{
    public const ACK_BACKUP = 'backup';

    public const ACK_IMPORT_EXCEPTIONS = 'import_exceptions';

    public const ACK_TAX_OFF = 'tax_off';

    public const ACK_CACHE = 'cache';

    public const ACK_ROLLBACK = 'rollback';

    public const ACK_WOO_ARCHIVE = 'woo_archive';

    public function __construct(
        private readonly DeliverySetupStatusService $deliveryStatus,
        private readonly PaymentProviderManager $payments,
        private readonly ConnectedSiteService $connectedSites,
    ) {}

    public function currentForStore(Store $store): ConnectedSiteCutover
    {
        $site = $this->connectedSites->primarySite($store);

        $cutover = ConnectedSiteCutover::query()
            ->where('store_id', $store->id)
            ->first();

        if ($cutover === null) {
            $cutover = ConnectedSiteCutover::query()->create([
                'store_id' => $store->id,
                'connected_site_id' => $site?->id,
                'status' => ConnectedSiteCutover::STATUS_DRAFT,
            ]);
        } elseif ($site && (int) $cutover->connected_site_id !== (int) $site->id) {
            $cutover->update(['connected_site_id' => $site->id]);
            $cutover->refresh();
        }

        return $cutover;
    }

    /**
     * @return array{
     *     cutover: ConnectedSiteCutover,
     *     overall: string,
     *     can_activate: bool,
     *     blocking_labels: list<string>,
     *     stages: list<array<string, mixed>>
     * }
     */
    public function assess(Store $store): array
    {
        $store->refresh();
        $site = $this->connectedSites->primarySite($store);
        $cutover = $this->currentForStore($store);
        $gates = $this->gates($store, $site, $cutover);

        $stages = [
            $this->stage('prepare', 'Prepare and protect data', [
                $gates['backup'],
                $gates['import'],
                $gates['import_exceptions'],
            ]),
            $this->stage('store', 'Ready the store', [
                $gates['currency'],
                $gates['location'],
                $gates['inventory'],
                $gates['tax'],
                $gates['delivery'],
            ]),
            $this->stage('payments', 'Connect payments and WordPress', [
                $gates['stripe'],
                $gates['connector'],
                $gates['url_binding'],
                $gates['diagnostics'],
            ]),
            $this->stage('test', 'Test the complete sale', [
                $gates['test_product'],
                $gates['smoke_order'],
                $gates['confirmation'],
            ]),
            $this->stage('switch', 'Switch the website', [
                $gates['conflicts'],
                $gates['pages'],
                $gates['redirect'],
                $gates['cache'],
            ]),
            $this->stage('golive', 'Go live safely', [
                $gates['rollback'],
                $gates['woo_archive'],
            ]),
        ];

        $blocking = [];
        foreach ($gates as $gate) {
            if (($gate['critical'] ?? false) && ($gate['status'] ?? '') === 'blocked') {
                $blocking[] = (string) $gate['label'];
            }
        }

        $requiredAcksMissing = false;
        foreach ($gates as $gate) {
            if (($gate['status'] ?? '') === 'warning' && ($gate['blocks_activation'] ?? false)) {
                $requiredAcksMissing = true;
                $blocking[] = (string) $gate['label'];
            }
        }

        $canActivate = $blocking === [] && ! $requiredAcksMissing && ! $cutover->isActivated();
        $overall = $cutover->isActivated()
            ? ConnectedSiteCutover::STATUS_ACTIVATED
            : ($cutover->status === ConnectedSiteCutover::STATUS_ROLLED_BACK
                ? ConnectedSiteCutover::STATUS_ROLLED_BACK
                : ($canActivate ? ConnectedSiteCutover::STATUS_READY : ConnectedSiteCutover::STATUS_BLOCKED));

        if ($cutover->status !== ConnectedSiteCutover::STATUS_ACTIVATED
            && $cutover->status !== ConnectedSiteCutover::STATUS_ROLLED_BACK
            && $cutover->status !== $overall) {
            $cutover->update([
                'status' => $overall,
                'last_verified_at' => now(),
            ]);
            $cutover->refresh();
        } else {
            $cutover->update(['last_verified_at' => now()]);
            $cutover->refresh();
        }

        return [
            'cutover' => $cutover,
            'overall' => $overall,
            'can_activate' => $canActivate,
            'blocking_labels' => array_values(array_unique($blocking)),
            'stages' => $stages,
            'gates' => $gates,
        ];
    }

    public function acknowledge(Store $store, User $user, string $key): ConnectedSiteCutover
    {
        $cutover = $this->currentForStore($store);
        $map = [
            self::ACK_BACKUP => ['backup_acknowledged_at', 'backup_acknowledged_by'],
            self::ACK_IMPORT_EXCEPTIONS => ['import_exceptions_acknowledged_at', 'import_exceptions_acknowledged_by'],
            self::ACK_TAX_OFF => ['tax_off_acknowledged_at', 'tax_off_acknowledged_by'],
            self::ACK_CACHE => ['external_cache_acknowledged_at', 'external_cache_acknowledged_by'],
            self::ACK_ROLLBACK => ['rollback_acknowledged_at', 'rollback_acknowledged_by'],
            self::ACK_WOO_ARCHIVE => ['woo_archive_acknowledged_at', 'woo_archive_acknowledged_by'],
        ];
        if (! isset($map[$key])) {
            throw ValidationException::withMessages([
                'acknowledgement' => 'That checklist item is not recognized.',
            ]);
        }

        [$at, $by] = $map[$key];
        $cutover->update([
            $at => now(),
            $by => $user->id,
            'started_by' => $cutover->started_by ?: $user->id,
        ]);

        return $cutover->fresh();
    }

    public function activate(Store $store, User $user): ConnectedSiteCutover
    {
        return DB::transaction(function () use ($store, $user): ConnectedSiteCutover {
            $assessment = $this->assess($store);
            $cutover = $assessment['cutover'];

            if ($cutover->isActivated()) {
                return $cutover;
            }

            if (! $assessment['can_activate']) {
                throw ValidationException::withMessages([
                    'cutover' => 'This website is not ready to go live. '.$this->blockingSentence($assessment['blocking_labels']),
                ]);
            }

            $smoke = $assessment['gates']['smoke_order'] ?? [];
            $cutover->update([
                'status' => ConnectedSiteCutover::STATUS_ACTIVATED,
                'activated_by' => $user->id,
                'activation_requested_at' => now(),
                'activated_at' => now(),
                'smoke_order_id' => $smoke['order_id'] ?? $cutover->smoke_order_id,
                'smoke_checkout_id' => $smoke['checkout_id'] ?? $cutover->smoke_checkout_id,
                'verification_snapshot' => $this->snapshot($assessment),
            ]);

            return $cutover->fresh();
        });
    }

    public function rollback(Store $store, User $user): ConnectedSiteCutover
    {
        $cutover = $this->currentForStore($store);
        $cutover->update([
            'status' => ConnectedSiteCutover::STATUS_ROLLED_BACK,
            'rolled_back_by' => $user->id,
            'rolled_back_at' => now(),
        ]);

        return $cutover->fresh();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function gates(Store $store, ?ConnectedSite $site, ConnectedSiteCutover $cutover): array
    {
        $import = ProductImport::query()
            ->where('store_id', $store->id)
            ->latest('id')
            ->first();
        $failedRows = (int) (($import?->result_summary['failed'] ?? 0));
        $unsupportedNoted = (bool) ($import?->preview_summary['woocommerce_summary']['unsupported_rows'] ?? false)
            || $failedRows > 0;
        $importCompleted = $import !== null && $import->status === ProductImport::STATUS_COMPLETED && $failedRows === 0;
        $publishedCount = Product::query()->where('store_id', $store->id)->where('status', true)->count();

        $defaultLocation = $store->locations()
            ->where('is_active', true)
            ->orderByDesc('is_default')
            ->first();
        $locationReady = $defaultLocation instanceof Location
            && $defaultLocation->fulfills_online_orders
            && filled($defaultLocation->address_line1)
            && filled($defaultLocation->city)
            && filled($defaultLocation->country_code);

        $hasSellableStock = InventoryLevel::query()
            ->whereHas('inventoryItem', function ($query) use ($store): void {
                $query->whereHas('variant', function ($variantQuery) use ($store): void {
                    $variantQuery->whereHas('product', function ($productQuery) use ($store): void {
                        $productQuery->where('store_id', $store->id)->where('status', true);
                    });
                });
            })
            ->where('available', '>', 0)
            ->exists();

        $tax = $store->taxSetting;
        $taxEnabled = (bool) ($tax?->enabled);
        $delivery = $this->deliveryStatus->assess(
            $store,
            $store->locations()->get(),
            $store->shippingZones()->get(),
            $store->shippingMethods()->get(),
            $store->carrierAccounts()->get(),
            $tax
        );

        $stripeAccount = $this->payments->accountForCheckout($store);
        $health = is_array($site?->last_health) ? $site->last_health : [];
        $healthAt = $site?->last_health_at;
        $healthFresh = $healthAt !== null && $healthAt->gte(now()->subDay());
        $urlMatch = $health['url_match'] ?? null;
        $conflicts = is_array($health['conflicts'] ?? null) ? $health['conflicts'] : [];
        $blockingConflicts = array_values(array_filter(
            $conflicts,
            static fn ($row): bool => is_array($row) && ($row['severity'] ?? 'block') !== 'warning'
        ));
        $productionReady = array_key_exists('production_ready', $health) ? (bool) $health['production_ready'] : null;

        $paidOrder = Order::query()
            ->where('store_id', $store->id)
            ->where('payment_status', OrderLifecycle::PAYMENT_PAID)
            ->latest('id')
            ->first();
        $checkout = $paidOrder
            ? Checkout::query()->where('store_id', $store->id)->where('converted_order_id', $paidOrder->id)->latest('id')->first()
            : null;

        $currency = strtoupper(trim((string) $store->currency));

        return [
            'backup' => $this->gate(
                'WordPress backup',
                $cutover->backup_acknowledged_at ? 'completed' : 'warning',
                true,
                'This portal cannot see your WordPress files. Confirm you have a recoverable backup before switching shoppers.',
                blocksActivation: $cutover->backup_acknowledged_at === null,
                ack: self::ACK_BACKUP,
                ackLabel: 'I have a WordPress files and database backup',
            ),
            'import' => $this->gate(
                'Catalog ready',
                $importCompleted || $publishedCount > 0 ? 'completed' : ($import?->status === ProductImport::STATUS_FAILED || $failedRows > 0 ? 'blocked' : 'warning'),
                true,
                $importCompleted
                    ? 'The latest catalog import finished without failed rows.'
                    : ($publishedCount > 0
                        ? 'No WooCommerce import is required if you already added products in this portal. Product import does not migrate orders, customers, or payments.'
                        : 'Import a WooCommerce catalog or add a product here before shoppers visit the website.'),
                actionLabel: $publishedCount > 0 ? null : 'Import products',
                actionHref: $publishedCount > 0 ? null : route('products.import.create'),
            ),
            'import_exceptions' => $this->gate(
                'Import exceptions',
                $failedRows > 0
                    ? 'blocked'
                    : (($unsupportedNoted && $cutover->import_exceptions_acknowledged_at === null) ? 'warning' : 'completed'),
                true,
                $failedRows > 0
                    ? 'Some import rows failed. Open the import report and fix or retry them before going live.'
                    : 'Unsupported WooCommerce types stay in the import report. They are never treated as a successful catalog row.',
                actionLabel: $import ? 'Open import report' : null,
                actionHref: $import ? route('products.import.report', ['productImportId' => $import->id]) : null,
                blocksActivation: $failedRows > 0 || ($unsupportedNoted && $cutover->import_exceptions_acknowledged_at === null && $failedRows === 0),
                ack: ($unsupportedNoted && $failedRows === 0) ? self::ACK_IMPORT_EXCEPTIONS : null,
                ackLabel: 'I reviewed leftover WooCommerce rows and accept they were not imported',
            ),
            'currency' => $this->gate(
                'Store currency',
                preg_match('/^[A-Z]{3}$/', $currency) === 1 ? 'completed' : 'blocked',
                true,
                preg_match('/^[A-Z]{3}$/', $currency) === 1
                    ? 'Website checkout uses '.$currency.' from this store.'
                    : 'Set a three-letter currency in General settings.',
                actionLabel: 'Open General settings',
                actionHref: route('generalSettings'),
            ),
            'location' => $this->gate(
                'Fulfillment location',
                $locationReady ? 'completed' : 'blocked',
                true,
                $locationReady
                    ? 'An active location can fulfill website orders.'
                    : 'Add an active ship-from location with a street, city, and country, and turn on website fulfillment.',
                actionLabel: 'Open delivery setup',
                actionHref: route('settings.delivery.setup.ship-from'),
            ),
            'inventory' => $this->gate(
                'Inventory',
                $hasSellableStock ? 'completed' : ($publishedCount > 0 ? 'warning' : 'blocked'),
                true,
                $hasSellableStock
                    ? 'At least one published product has available stock.'
                    : ($publishedCount > 0
                        ? 'Published products currently show 0 available. Shoppers will see out of stock until you add quantity.'
                        : 'Publish a product with available stock before going live.'),
                actionLabel: 'Open products',
                actionHref: route('products'),
                blocksActivation: $publishedCount < 1,
            ),
            'tax' => $this->gate(
                'Tax',
                $taxEnabled ? 'completed' : ($cutover->tax_off_acknowledged_at ? 'completed' : 'warning'),
                true,
                $taxEnabled
                    ? 'Tax is on for this store.'
                    : 'Tax is off. Confirm that is intentional. This is not tax advice.',
                actionLabel: 'Open tax settings',
                actionHref: route('settings.taxes.index'),
                blocksActivation: ! $taxEnabled && $cutover->tax_off_acknowledged_at === null,
                ack: $taxEnabled ? null : self::ACK_TAX_OFF,
                ackLabel: 'Tax is intentionally off for this store',
            ),
            'delivery' => $this->gate(
                'Delivery method',
                ! empty($delivery['is_ready']) ? 'completed' : 'blocked',
                true,
                ! empty($delivery['is_ready'])
                    ? 'At least one checkout-enabled delivery method is ready.'
                    : 'Finish delivery setup so checkout can offer a shipping option.',
                actionLabel: 'Open delivery setup',
                actionHref: route('settings.delivery.setup'),
            ),
            'stripe' => $this->gate(
                'Stripe',
                $stripeAccount ? 'completed' : 'blocked',
                true,
                $stripeAccount
                    ? 'This store’s Stripe account can accept payment.'
                    : 'Connect Stripe for this store in Payments. A disconnected account blocks checkout and never falls back to another store or WordPress.',
                actionLabel: 'Open Payments',
                actionHref: route('settings.payments.index'),
            ),
            'connector' => $this->gate(
                'WordPress connection',
                $site?->isActive() && $store->hasDeveloperStorefrontToken() ? 'completed' : 'blocked',
                true,
                $site?->isActive()
                    ? 'An active connection key is bound to this store.'
                    : 'Create a connection key after saving this store’s exact WordPress address.',
                actionLabel: null,
                actionHref: null,
            ),
            'url_binding' => $this->gate(
                'Website address match',
                $site && $urlMatch === true ? 'completed' : 'blocked',
                true,
                $site && $urlMatch === true
                    ? 'WordPress reported the same address saved for this store.'
                    : 'Save the exact WordPress address and run Test connection in WordPress so this portal can confirm the match.',
            ),
            'diagnostics' => $this->gate(
                'Site diagnostics',
                $healthFresh && $site?->last_seen_at ? 'completed' : 'blocked',
                true,
                $healthFresh
                    ? 'WordPress contacted this portal recently.'
                    : 'Open WordPress Settings → Eco Portal and run Test connection so this checklist can use a fresh health check.',
            ),
            'test_product' => $this->gate(
                'Test product visible',
                $publishedCount > 0 && $site?->last_seen_at ? 'completed' : 'blocked',
                true,
                $publishedCount > 0
                    ? 'This store has published products for the WordPress shop.'
                    : 'Publish a product before asking shoppers to buy.',
                actionLabel: 'Add a product',
                actionHref: route('products.create'),
            ),
            'smoke_order' => $this->gate(
                'Test order',
                $paidOrder ? 'completed' : 'blocked',
                true,
                $paidOrder
                    ? 'A paid order exists for this store. Open it to confirm stock moved.'
                    : 'Place one test order from WordPress using this store’s Stripe test mode. Browser payment screens are not proof of payment.',
                actionLabel: $paidOrder ? 'Open test order' : 'Open Orders',
                actionHref: $paidOrder ? route('orderViewDetails', $paidOrder) : route('orders'),
                extra: [
                    'order_id' => $paidOrder?->id,
                    'checkout_id' => $checkout?->id,
                ],
            ),
            'confirmation' => $this->gate(
                'Order confirmation',
                $paidOrder ? 'completed' : 'blocked',
                true,
                $paidOrder
                    ? 'Shoppers can look up the SaaS confirmation on the WordPress order-status page.'
                    : 'Complete the test purchase so confirmation and tracking can be checked.',
            ),
            'conflicts' => $this->gate(
                'WordPress conflicts',
                $blockingConflicts === [] && $productionReady === true ? 'completed' : ($productionReady === null ? 'blocked' : 'blocked'),
                true,
                $blockingConflicts === [] && $productionReady === true
                    ? 'WordPress reported no blocking WooCommerce, payment, or shipping conflicts.'
                    : ($productionReady === null
                        ? 'Run Test connection in WordPress. This portal will not mark the shop live from a missing conflict report.'
                        : 'WordPress still reports a commerce conflict. Turn those plugins or pages off yourself. This portal never deactivates WordPress plugins.'),
            ),
            'pages' => $this->gate(
                'Shop pages',
                $productionReady === true ? 'completed' : 'warning',
                false,
                'Confirm Portal Shop, Cart, Checkout, and order status pages are assigned in WordPress Settings → Eco Portal. This portal does not change WordPress pages for you.',
            ),
            'redirect' => $this->gate(
                'Old product addresses',
                'warning',
                false,
                'If you imported WooCommerce products, apply the old-to-new address map from the import report. This does not turn WooCommerce checkout back on.',
                actionLabel: $import ? 'Open import report' : null,
                actionHref: $import ? route('products.import.report', ['productImportId' => $import->id]) : null,
            ),
            'cache' => $this->gate(
                'Website cache',
                $cutover->external_cache_acknowledged_at ? 'completed' : 'warning',
                true,
                'Clear WordPress, host, and CDN caches if you use them. This portal cannot prove an external cache purge.',
                blocksActivation: $cutover->external_cache_acknowledged_at === null,
                ack: self::ACK_CACHE,
                ackLabel: 'I cleared or skipped website caches that apply to this shop',
            ),
            'rollback' => $this->gate(
                'Rollback path',
                $cutover->rollback_acknowledged_at ? 'completed' : 'warning',
                true,
                'If something goes wrong, keep the WordPress backup, reconnect the previous pages, and leave WooCommerce data untouched. This button never deletes WordPress or WooCommerce.',
                blocksActivation: $cutover->rollback_acknowledged_at === null,
                ack: self::ACK_ROLLBACK,
                ackLabel: 'I understand how to roll back using my WordPress backup',
            ),
            'woo_archive' => $this->gate(
                'WooCommerce archive',
                $cutover->woo_archive_acknowledged_at ? 'completed' : 'warning',
                true,
                'Keep the WooCommerce backup or read-only archive during the rollback period. Do not delete it from this checklist.',
                blocksActivation: $cutover->woo_archive_acknowledged_at === null,
                ack: self::ACK_WOO_ARCHIVE,
                ackLabel: 'I will keep the WooCommerce backup until the rollback period ends',
            ),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $gates
     * @return array<string, mixed>
     */
    private function stage(string $key, string $title, array $gates): array
    {
        $statuses = array_map(static fn (array $gate): string => (string) $gate['status'], $gates);
        $status = in_array('blocked', $statuses, true)
            ? 'blocked'
            : (in_array('warning', $statuses, true) ? 'warning' : 'completed');

        return [
            'key' => $key,
            'title' => $title,
            'status' => $status,
            'gates' => $gates,
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function gate(
        string $label,
        string $status,
        bool $critical,
        string $message,
        ?string $actionLabel = null,
        ?string $actionHref = null,
        bool $blocksActivation = false,
        ?string $ack = null,
        ?string $ackLabel = null,
        array $extra = [],
    ): array {
        return array_merge([
            'label' => $label,
            'status' => $status,
            'critical' => $critical,
            'message' => $message,
            'action_label' => $actionLabel,
            'action_href' => $actionHref,
            'blocks_activation' => $blocksActivation || ($critical && $status === 'blocked'),
            'ack' => $ack,
            'ack_label' => $ackLabel,
        ], $extra);
    }

    /**
     * @param  list<string>  $labels
     */
    private function blockingSentence(array $labels): string
    {
        if ($labels === []) {
            return 'Finish the remaining checklist items.';
        }

        return 'Still needed: '.implode(', ', array_slice($labels, 0, 4)).(count($labels) > 4 ? '…' : '.');
    }

    /**
     * @param  array<string, mixed>  $assessment
     * @return array<string, mixed>
     */
    private function snapshot(array $assessment): array
    {
        $gates = [];
        foreach ($assessment['gates'] ?? [] as $key => $gate) {
            $gates[$key] = [
                'label' => $gate['label'] ?? $key,
                'status' => $gate['status'] ?? null,
                'order_id' => $gate['order_id'] ?? null,
            ];
        }

        return [
            'overall' => $assessment['overall'] ?? null,
            'gates' => $gates,
        ];
    }
}
