<?php

namespace App\Services;

use App\Models\Checkout;
use App\Models\Customer;
use App\Models\PaymentIntent;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Services\Inventory\InventoryReservationService;
use App\Services\Inventory\InventorySyncService;
use App\Services\Payments\PaymentProviderManager;
use App\Services\Shipping\DeliveryOptionService;
use App\Support\CheckoutMode;
use App\Support\ProductVariantLabel;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CheckoutService
{
    private const SOURCE = 'platform_checkout';

    public function __construct(
        private readonly InventorySyncService $syncService,
        private readonly InventoryReservationService $reservationService,
        private readonly OrderNumberGenerator $numberGenerator,
        private readonly PaymentProviderManager $paymentProviderManager,
        private readonly CheckoutEventRecorder $eventRecorder,
        private readonly DeliveryOptionService $deliveryOptionService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(Store $store, array $payload): Checkout
    {
        return DB::transaction(function () use ($store, $payload): Checkout {
            $customer = $this->upsertCustomer($store, $payload['customer']);
            $items = $this->prepareItems($store, $payload['items']);
            $provider = (string) config('payments.default_provider', 'stripe');

            if (CheckoutMode::forStore($store) !== CheckoutMode::PLATFORM) {
                throw ValidationException::withMessages([
                    'payment' => 'Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.',
                ]);
            }

            $providerAccount = $this->paymentProviderManager->accountForCheckout($store);
            $paymentMode = $this->paymentProviderManager->platformPaymentModeForStore($store);
            if (! $providerAccount) {
                throw ValidationException::withMessages([
                    'payment' => 'Platform checkout is not enabled for this store. Connect Stripe in the SaaS dashboard or use External checkout sync.',
                ]);
            }
            $currencyCode = strtoupper((string) ($payload['currency_code'] ?? $store->currency ?? 'USD'));
            $shippingAddress = $payload['shipping_address'];
            $shippingMethodId = filled($payload['shipping_method_id'] ?? null) ? (int) $payload['shipping_method_id'] : null;
            $shippingSnapshot = null;
            $shippingTotal = 0.0;
            $subtotal = $this->subtotal($items);

            if ($shippingMethodId) {
                $option = $this->deliveryOptionService->optionForMethodId(
                    $store,
                    $shippingMethodId,
                    $shippingAddress,
                    $subtotal,
                    $currencyCode,
                );

                if (! $option) {
                    throw ValidationException::withMessages([
                        'shipping_method_id' => 'Choose an available delivery method for this address.',
                    ]);
                }

                $shippingTotal = $this->money($option['amount']);
                $shippingSnapshot = $option['snapshot'];
                $shippingSnapshot['selected_at'] = now()->toISOString();
            }

            $totals = $this->totals($items, $shippingTotal);
            $billingSameAsShipping = $this->billingSameAsShipping($payload);

            $checkout = Checkout::query()->create([
                'store_id' => $store->id,
                'customer_id' => $customer->id,
                'checkout_number' => $this->numberGenerator->generateCheckout($store),
                'source_channel' => (string) ($payload['source_channel'] ?? 'dev_storefront'),
                'mode' => self::SOURCE,
                'status' => Checkout::STATUS_PAYMENT_PENDING,
                'currency_code' => $currencyCode,
                'subtotal' => $totals['subtotal'],
                'discount_total' => $totals['discount'],
                'shipping_total' => $totals['shipping'],
                'shipping_method_id' => $shippingMethodId,
                'shipping_snapshot' => $shippingSnapshot,
                'tax_total' => $totals['tax'],
                'grand_total' => $totals['grand_total'],
                'payment_provider' => $provider,
                'payment_provider_account_id' => $providerAccount->id,
                'metadata' => [
                    'billing_same_as_shipping' => $billingSameAsShipping,
                    'received_at' => now()->toISOString(),
                    'server_totals' => true,
                    'shipping' => $shippingSnapshot,
                    'payment_connection_type' => $providerAccount->connection_type,
                    'payment_provider_account_id' => $providerAccount->id,
                    'connected_account_id' => $providerAccount->provider_account_id,
                    'platform_payment_mode' => $paymentMode,
                ],
                'expires_at' => now()->addHours(2),
            ]);

            $this->createCheckoutAddress($checkout, 'shipping', $shippingAddress, $customer);
            $this->saveCustomerShippingAddress($customer, $shippingAddress);

            if ($billingSameAsShipping) {
                $this->createCheckoutAddress($checkout, 'billing', $shippingAddress, $customer);
            } elseif (is_array($payload['billing_address'] ?? null)) {
                $this->createCheckoutAddress($checkout, 'billing', $payload['billing_address'], $customer);
            }

            $reservationCount = 0;
            foreach ($items as $item) {
                /** @var ProductVariant $variant */
                $variant = $item['variant'];
                $inventoryItem = $this->syncService->ensureInventoryItemForVariant($variant);
                $reservation = $this->reservationService->reserve(
                    $inventoryItem,
                    (int) $item['quantity'],
                    'checkout',
                    (string) $checkout->id,
                    null,
                    $checkout->expires_at,
                    [
                        'source' => self::SOURCE,
                        'reference_type' => 'checkout',
                        'reference_id' => $checkout->id,
                        'reference_code' => $checkout->checkout_number,
                        'checkout_reference' => $checkout->checkout_number,
                        'validation_key' => 'items',
                        'metadata' => [
                            'checkout_number' => $checkout->checkout_number,
                            'variant_id' => $variant->id,
                        ],
                    ]
                );
                $reservationCount++;

                $product = $variant->product;
                $primaryImage = $product?->primaryImage();

                $checkout->items()->create([
                    'product_id' => $product?->id,
                    'product_variant_id' => $variant->id,
                    'product_name' => $product?->name ?? 'Catalog item',
                    'variant_label' => $item['variant_label'],
                    'sku_snapshot' => $variant->sku,
                    'product_slug_snapshot' => $product?->slug,
                    'brand_name_snapshot' => $product?->brand?->name,
                    'product_image_snapshot' => $primaryImage?->image_path,
                    'product_type_snapshot' => $product?->product_type,
                    'variant_details' => $item['variant_details'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'subtotal' => $item['subtotal'],
                    'discount_amount' => 0,
                    'tax_amount' => 0,
                    'total' => $item['subtotal'],
                    'metadata' => [
                        'reservation_id' => $reservation->id,
                    ],
                ]);
            }

            $this->eventRecorder->record(
                $checkout,
                'checkout.created',
                'Checkout created',
                'A platform checkout was created from the storefront.',
                ['source_channel' => $checkout->source_channel]
            );
            $this->eventRecorder->record(
                $checkout,
                'inventory.reserved',
                'Inventory reserved',
                'Stock was reserved while the customer completes payment.',
                ['reservation_count' => $reservationCount]
            );

            if ($shippingSnapshot) {
                $this->eventRecorder->record(
                    $checkout,
                    'shipping.method_selected',
                    'Delivery method selected',
                    'Customer selected '.$shippingSnapshot['method_name'].' for this checkout.',
                    [
                        'shipping_method_id' => $shippingMethodId,
                        'shipping_total' => $shippingTotal,
                        'currency_code' => $checkout->currency_code,
                    ]
                );
            }

            $result = $this->paymentProviderManager
                ->driver($provider)
                ->createPaymentIntent($checkout, [
                    'provider_account' => $providerAccount,
                    'mode' => $paymentMode,
                ]);

            $paymentIntent = PaymentIntent::query()->create([
                'store_id' => $store->id,
                'checkout_id' => $checkout->id,
                'payment_provider_account_id' => $providerAccount->id,
                'provider' => $result->provider,
                'mode' => $result->mode ?? $paymentMode,
                'provider_intent_id' => $result->providerIntentId,
                'provider_account_id' => $result->providerAccountId ?? $providerAccount->provider_account_id,
                'client_secret' => $result->clientSecret,
                'status' => $result->status,
                'currency_code' => $result->currencyCode,
                'amount' => $result->amount,
                'amount_minor' => $this->amountMinor($result->amount, $result->currencyCode),
                'request_payload' => [
                    'checkout_id' => $checkout->id,
                    'checkout_number' => $checkout->checkout_number,
                    'amount' => $result->amount,
                    'currency_code' => $result->currencyCode,
                    'connection_type' => $providerAccount->connection_type,
                    'provider_account_id' => $providerAccount->provider_account_id,
                ],
                'response_payload' => $result->raw,
            ]);

            $paymentIntent->attempts()->create([
                'store_id' => $store->id,
                'provider' => $result->provider,
                'status' => $result->status,
                'response_payload' => $result->raw,
            ]);

            $checkout->forceFill([
                'stripe_payment_intent_id' => $result->providerIntentId,
            ])->save();

            $this->eventRecorder->record(
                $checkout,
                'payment.intent_created',
                'Payment started',
                $providerAccount->connection_type === 'connect'
                    ? 'Stripe payment was prepared through the connected account.'
                    : 'Stripe sandbox payment was prepared for this checkout.',
                [
                    'payment_intent_id' => $result->providerIntentId,
                    'connection_type' => $providerAccount->connection_type,
                    'provider_account_id' => $providerAccount->provider_account_id,
                ]
            );

            return $checkout->load(['items', 'addresses', 'events', 'paymentIntents']);
        });
    }

    /**
     * @param  array<string, mixed>  $customerData
     */
    private function upsertCustomer(Store $store, array $customerData): Customer
    {
        $email = strtolower(trim((string) $customerData['email']));
        $fullName = trim((string) ($customerData['full_name'] ?? ''));
        $firstName = trim((string) ($customerData['first_name'] ?? ''));
        $lastName = trim((string) ($customerData['last_name'] ?? ''));

        if ($fullName === '') {
            $fullName = trim($firstName.' '.$lastName);
        }

        if ($fullName === '') {
            $fullName = $email;
        }

        if ($firstName === '' && $lastName === '') {
            [$firstName, $lastName] = $this->splitName($fullName);
        }

        $existing = Customer::query()
            ->where('store_id', $store->id)
            ->where('email', $email)
            ->first();

        if ($existing?->status === 'blocked') {
            throw ValidationException::withMessages([
                'customer.email' => 'This customer is blocked and cannot place a new platform checkout.',
            ]);
        }

        $customer = Customer::query()->updateOrCreate(
            ['store_id' => $store->id, 'email' => $email],
            [
                'first_name' => $firstName ?: null,
                'last_name' => $lastName ?: null,
                'full_name' => $fullName,
                'phone' => $customerData['phone'] ?? null,
                'source' => self::SOURCE,
                'preferred_currency' => $store->currency ?? 'USD',
                'meta' => [
                    'last_platform_checkout_at' => now()->toISOString(),
                ],
            ]
        );

        return $customer;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function prepareItems(Store $store, array $rows): array
    {
        $variantIds = collect($rows)
            ->pluck('variant_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        /** @var Collection<int, ProductVariant> $variants */
        $variants = ProductVariant::query()
            ->with(['product.brand', 'product.images', 'options.variationType'])
            ->where('store_id', $store->id)
            ->whereIn('id', $variantIds)
            ->get()
            ->keyBy('id');

        $items = [];
        foreach ($rows as $index => $row) {
            $variant = $variants->get((int) ($row['variant_id'] ?? 0));

            if (! $variant || ! $variant->product || (int) $variant->product->store_id !== (int) $store->id) {
                throw ValidationException::withMessages([
                    'items.'.($index).'.variant_id' => 'Choose a product variant that belongs to this store.',
                ]);
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $unitPrice = $this->money($variant->price);
            $variantCount = $variant->product?->variants()->count() ?? 1;
            $variantLabel = ProductVariantLabel::forVariant($variant, 0, $variantCount);

            if (isset($items[$variant->id])) {
                $items[$variant->id]['quantity'] += $quantity;
                $items[$variant->id]['subtotal'] = $this->money($items[$variant->id]['unit_price'] * $items[$variant->id]['quantity']);
                continue;
            }

            $items[$variant->id] = [
                'variant' => $variant,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $this->money($unitPrice * $quantity),
                'variant_label' => $variantLabel,
                'variant_details' => [
                    'options' => $variant->options
                        ->map(fn ($option): array => [
                            'group' => $option->variationType?->name ?? 'Option',
                            'value' => $option->value,
                        ])
                        ->values()
                        ->all(),
                ],
            ];
        }

        return array_values($items);
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return array{subtotal: float, shipping: float, tax: float, discount: float, grand_total: float}
     */
    private function totals(array $items, float $shippingTotal): array
    {
        $subtotal = $this->subtotal($items);
        $shipping = $this->money($shippingTotal);
        $tax = 0.0;
        $discount = 0.0;

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'tax' => $tax,
            'discount' => $discount,
            'grand_total' => $this->money(max(0, $subtotal + $shipping + $tax - $discount)),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function subtotal(array $items): float
    {
        return $this->money(array_sum(array_map(fn (array $item): float => (float) $item['subtotal'], $items)));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function billingSameAsShipping(array $payload): bool
    {
        $billing = $payload['billing_address'] ?? null;

        if (! is_array($billing)) {
            return true;
        }

        return (bool) ($billing['same_as_shipping'] ?? true);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function createCheckoutAddress(Checkout $checkout, string $type, array $address, Customer $customer): void
    {
        $checkout->addresses()->create([
            'type' => $type,
            'name' => $address['name'] ?? $customer->full_name,
            'email' => $customer->email,
            'company' => $address['company'] ?? null,
            'address_line1' => $address['address_line1'] ?? null,
            'address_line2' => $address['address_line2'] ?? null,
            'city' => $address['city'] ?? null,
            'state' => $address['state'] ?? null,
            'province_code' => $address['province_code'] ?? null,
            'postal_code' => $address['postal_code'] ?? null,
            'country' => $address['country'] ?? null,
            'country_code' => $address['country_code'] ?? null,
            'phone' => $address['phone'] ?? $customer->phone,
            'delivery_notes' => $address['delivery_notes'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $address
     */
    private function saveCustomerShippingAddress(Customer $customer, array $address): void
    {
        $isFirstAddress = ! $customer->addresses()->exists();

        $customer->addresses()->updateOrCreate(
            [
                'type' => 'shipping',
                'address_line1' => $address['address_line1'] ?? null,
                'city' => $address['city'] ?? null,
                'postal_code' => $address['postal_code'] ?? null,
                'country' => $address['country'] ?? null,
            ],
            [
                'name' => $address['name'] ?? $customer->full_name,
                'company' => $address['company'] ?? null,
                'address_line2' => $address['address_line2'] ?? null,
                'state' => $address['state'] ?? null,
                'province_code' => $address['province_code'] ?? null,
                'country_code' => $address['country_code'] ?? null,
                'phone' => $address['phone'] ?? $customer->phone,
                'is_default' => $isFirstAddress,
                'delivery_instructions' => $address['delivery_notes'] ?? null,
            ]
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitName(string $fullName): array
    {
        $parts = preg_split('/\s+/', trim($fullName), 2) ?: [];

        return [$parts[0] ?? '', $parts[1] ?? ''];
    }

    private function money(mixed $value): float
    {
        if ($value === null || trim((string) $value) === '') {
            return 0.0;
        }

        return round(max(0, (float) $value), 2);
    }

    private function amountMinor(float $amount, string $currency): int
    {
        $zeroDecimal = in_array(strtolower($currency), ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf'], true);

        return (int) round($amount * ($zeroDecimal ? 1 : 100));
    }
}
