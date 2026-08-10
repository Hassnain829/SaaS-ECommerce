<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\FedEx\Support\FedExCheckoutServiceCatalog;
use App\Services\Carriers\FedEx\Support\FedExHandoffTypeResolver;
use App\Services\Delivery\StoreShippingPreferences;
use App\Support\ReturnLifecycle;
use Illuminate\Validation\ValidationException;

/**
 * Creates a FedEx return label via the production ship purchase path,
 * bound to an approved OrderReturn.
 */
final class FedExReturnLabelService
{
    public function __construct(
        private readonly FedExShipmentPurchaseService $purchaseService,
        private readonly StoreShippingPreferences $shippingPreferences,
        private readonly FedExServiceAvailabilityService $serviceAvailability,
        private readonly FedExHandoffTypeResolver $handoffTypeResolver,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function createReturnLabel(
        Store $store,
        Order $order,
        CarrierAccount $account,
        Location $origin,
        array $input,
        ?User $actor = null,
    ): array {
        $orderReturn = $this->resolveApprovedReturn($store, $order, $input);

        $input['order_return_id'] = $orderReturn->id;
        $input['return_shipment'] = true;
        $input['items'] = $this->resolveReturnItems($orderReturn, $input);
        $input['packages'] = $this->resolvePackages($store, $input);
        $input['pickup_type'] = $this->handoffTypeResolver->resolve(
            $store,
            isset($input['pickup_type']) ? (string) $input['pickup_type'] : null,
        );
        $input['service_type'] = $this->resolveReturnServiceType(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            requestedServiceType: isset($input['service_type']) ? (string) $input['service_type'] : null,
            pickupType: (string) $input['pickup_type'],
        );

        $outcome = $this->purchaseService->purchase(
            store: $store,
            order: $order,
            account: $account,
            origin: $origin,
            input: $input,
            actor: $actor,
        );

        if (
            ($outcome['state'] ?? null) === FedExShipmentPurchaseService::STATE_SUCCEEDED
            && ($outcome['shipment'] ?? null)
            && filled($outcome['shipment']->tracking_number)
        ) {
            $orderReturn->forceFill([
                'tracking_reference' => (string) $outcome['shipment']->tracking_number,
            ])->save();
        }

        if (($outcome['state'] ?? null) === FedExShipmentPurchaseService::STATE_SUCCEEDED) {
            $outcome['resolved_service_type'] = $input['service_type'];
            $outcome['resolved_service_name'] = FedExCheckoutServiceCatalog::nameFor((string) $input['service_type']);
        }

        return $outcome;
    }

    /**
     * Prefer merchant selection when available; otherwise platform default.
     * Validates against Service Availability in the actual return direction:
     * customer (origin) → merchant warehouse (destination).
     */
    private function resolveReturnServiceType(
        Store $store,
        Order $order,
        CarrierAccount $account,
        Location $origin,
        ?string $requestedServiceType,
        ?string $pickupType = null,
    ): string {
        $requested = strtoupper(trim((string) $requestedServiceType));
        $default = FedExCheckoutServiceCatalog::defaultReturnServiceCode();
        $candidate = $requested !== '' ? $requested : $default;

        $shipping = $order->addresses->firstWhere('type', 'shipping') ?? $order->addresses->first();
        if (! $shipping) {
            return $candidate;
        }

        // Return Ship swaps parties: customer ships to merchant warehouse.
        $customerOrigin = [
            'country_code' => $shipping->country_code ?? 'US',
            'postal_code' => $shipping->postal_code,
            'state' => $shipping->province_code ?: $shipping->state,
            'city' => $shipping->city,
        ];
        $merchantDestination = [
            'country_code' => $origin->country_code ?? 'US',
            'postal_code' => $origin->postal_code,
            'state' => $origin->state,
            'city' => $origin->city,
        ];

        try {
            $outcome = $this->serviceAvailability->checkAvailability(
                store: $store,
                account: $account,
                originLocation: $origin,
                destinationInput: $merchantDestination,
                packagingType: 'YOUR_PACKAGING',
                enforceProductionGuard: true,
                pickupType: $pickupType,
                originAddressOverride: $customerOrigin,
            );
        } catch (\Throwable) {
            return $candidate;
        }

        if (! ($outcome['result']->success ?? false)) {
            return $candidate;
        }

        $available = array_map('strtoupper', $outcome['service_types'] ?? []);
        if ($available === []) {
            return $candidate;
        }

        if (in_array($candidate, $available, true)) {
            return $candidate;
        }

        if (in_array($default, $available, true)) {
            return $default;
        }

        foreach (FedExCheckoutServiceCatalog::codes() as $code) {
            if (in_array($code, $available, true)) {
                return $code;
            }
        }

        throw ValidationException::withMessages([
            'service_type' => 'FedEx does not currently offer a supported return service for this route. Try again later or contact support.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function resolveApprovedReturn(Store $store, Order $order, array $input): OrderReturn
    {
        $orderReturnId = (int) ($input['order_return_id'] ?? 0);
        if ($orderReturnId <= 0) {
            throw ValidationException::withMessages([
                'order_return_id' => 'Select an approved return before creating a FedEx return label.',
            ]);
        }

        $orderReturn = OrderReturn::query()
            ->where('store_id', $store->id)
            ->where('order_id', $order->id)
            ->whereKey($orderReturnId)
            ->with('items')
            ->first();

        if (! $orderReturn) {
            throw ValidationException::withMessages([
                'order_return_id' => 'That return was not found on this order.',
            ]);
        }

        if ($orderReturn->status !== ReturnLifecycle::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'order_return_id' => 'Create a FedEx return label only after the return is approved.',
            ]);
        }

        return $orderReturn;
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{order_item_id: int, quantity: int, selected: bool}>
     */
    private function resolveReturnItems(OrderReturn $orderReturn, array $input): array
    {
        $approved = [];
        foreach ($orderReturn->items as $returnItem) {
            $qty = (int) $returnItem->approved_quantity;
            if ($qty > 0) {
                $approved[(int) $returnItem->order_item_id] = $qty;
            }
        }

        if ($approved === []) {
            throw ValidationException::withMessages([
                'items' => 'This return has no approved items to include on a label.',
            ]);
        }

        $rawItems = $input['items'] ?? null;
        if (! is_array($rawItems) || $rawItems === []) {
            return collect($approved)
                ->map(static fn (int $quantity, int $orderItemId): array => [
                    'order_item_id' => $orderItemId,
                    'quantity' => $quantity,
                    'selected' => true,
                ])
                ->values()
                ->all();
        }

        $lines = [];
        foreach ($rawItems as $row) {
            if (! is_array($row)) {
                continue;
            }

            if (array_key_exists('selected', $row) && ! filter_var($row['selected'], FILTER_VALIDATE_BOOL)) {
                continue;
            }

            $orderItemId = (int) ($row['order_item_id'] ?? 0);
            $quantity = (int) ($row['quantity'] ?? 0);
            if ($orderItemId <= 0 || $quantity <= 0) {
                continue;
            }

            if (! array_key_exists($orderItemId, $approved)) {
                throw ValidationException::withMessages([
                    'items' => 'Only items on the approved return can be included on the return label.',
                ]);
            }

            if ($quantity > $approved[$orderItemId]) {
                throw ValidationException::withMessages([
                    'items' => 'Return label quantity cannot exceed the approved return quantity for an item.',
                ]);
            }

            $lines[$orderItemId] = ($lines[$orderItemId] ?? 0) + $quantity;
        }

        if ($lines === []) {
            throw ValidationException::withMessages([
                'items' => 'Choose at least one approved return item for the label.',
            ]);
        }

        return collect($lines)
            ->map(static fn (int $quantity, int $orderItemId): array => [
                'order_item_id' => $orderItemId,
                'quantity' => $quantity,
                'selected' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * Prefer explicit package dims from the request; otherwise use the store default package preset.
     *
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    private function resolvePackages(Store $store, array $input): array
    {
        $packages = $input['packages'] ?? null;
        if (is_array($packages) && $packages !== []) {
            $first = array_is_list($packages) ? ($packages[0] ?? null) : $packages;
            if (is_array($first) && $this->packageHasCompleteDims($first)) {
                return array_is_list($packages) ? array_values($packages) : [$packages];
            }
        }

        $weight = $input['weight'] ?? null;
        $length = $input['length'] ?? null;
        $width = $input['width'] ?? null;
        $height = $input['height'] ?? null;
        $weightUnit = strtoupper((string) ($input['weight_unit'] ?? 'LB'));
        $dimensionUnit = strtoupper((string) ($input['dimension_unit'] ?? 'IN'));

        $explicit = [
            'weight' => $weight,
            'length' => $length,
            'width' => $width,
            'height' => $height,
            'weight_unit' => $weightUnit,
            'dimension_unit' => $dimensionUnit,
        ];

        if ($this->packageHasCompleteDims($explicit)) {
            return [[
                'weight' => (float) $weight,
                'length' => (float) $length,
                'width' => (float) $width,
                'height' => (float) $height,
                'weight_unit' => $weightUnit !== '' ? $weightUnit : 'LB',
                'dimension_unit' => $dimensionUnit !== '' ? $dimensionUnit : 'IN',
            ]];
        }

        $preset = $this->shippingPreferences->defaultPackagePreset($store);
        if ($preset) {
            $merged = [
                'weight' => is_numeric($weight) && (float) $weight > 0
                    ? (float) $weight
                    : (is_numeric($preset->weight_value) ? (float) $preset->weight_value : null),
                'length' => is_numeric($length) && (float) $length > 0
                    ? (float) $length
                    : (is_numeric($preset->length) ? (float) $preset->length : null),
                'width' => is_numeric($width) && (float) $width > 0
                    ? (float) $width
                    : (is_numeric($preset->width) ? (float) $preset->width : null),
                'height' => is_numeric($height) && (float) $height > 0
                    ? (float) $height
                    : (is_numeric($preset->height) ? (float) $preset->height : null),
                'weight_unit' => strtoupper((string) ($preset->weight_unit ?: $weightUnit ?: 'LB')),
                'dimension_unit' => strtoupper((string) ($preset->dimension_unit ?: $dimensionUnit ?: 'IN')),
            ];

            if ($this->packageHasCompleteDims($merged)) {
                return [$merged];
            }
        }

        throw ValidationException::withMessages([
            'packages' => 'Choose a package before requesting FedEx rates, or set a default package under Shipping settings.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $package
     */
    private function packageHasCompleteDims(array $package): bool
    {
        foreach (['weight', 'length', 'width', 'height'] as $field) {
            $value = $package[$field] ?? null;
            if (! is_numeric($value) || (float) $value <= 0) {
                return false;
            }
        }

        return true;
    }
}
