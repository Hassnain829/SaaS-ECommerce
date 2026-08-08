<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Order;
use App\Models\OrderReturn;
use App\Models\Store;
use App\Models\User;
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

        return $outcome;
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
            'packages' => 'Enter package weight and dimensions, or set a default package under Shipping settings.',
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
