<?php

namespace App\Support\Draft;

use App\Models\DraftOrder;

final class DraftOrderFormState
{
    /**
     * True when re-rendered old() tax-driving input differs from persisted draft values.
     */
    public static function taxDrivingInputDirty(DraftOrder $draft, array $shippingAddress): bool
    {
        if (! self::hasOldTaxDrivingInput()) {
            return false;
        }

        if (old('discount_total') !== null && self::moneyDiffers(old('discount_total'), $draft->discount_total)) {
            return true;
        }

        if (old('shipping_total') !== null && self::moneyDiffers(old('shipping_total'), $draft->shipping_total)) {
            return true;
        }

        if (old('shipping_country') !== null && self::stringDiffers(old('shipping_country'), $shippingAddress['country'] ?? '')) {
            return true;
        }

        if (old('shipping_state') !== null && self::stringDiffers(old('shipping_state'), $shippingAddress['state'] ?? '')) {
            return true;
        }

        if (old('items') !== null && is_array(old('items'))) {
            return self::itemsDiffer(old('items'), $draft);
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    public static function lineTotalAmount(array $row): string
    {
        if (isset($row['line_total']) && is_numeric($row['line_total'])) {
            return number_format((float) $row['line_total'], 2, '.', '');
        }

        $variantId = (int) ($row['product_variant_id'] ?? 0);
        if ($variantId <= 0) {
            return '0.00';
        }

        $quantity = max(1, (int) ($row['quantity'] ?? 1));
        $unitPrice = (string) ($row['unit_price'] ?? '0');

        if (trim($unitPrice) === '') {
            return '0.00';
        }

        return bcmul(number_format((float) $unitPrice, 2, '.', ''), (string) $quantity, 2);
    }

    private static function hasOldTaxDrivingInput(): bool
    {
        return old('items') !== null
            || old('discount_total') !== null
            || old('shipping_total') !== null
            || old('shipping_country') !== null
            || old('shipping_state') !== null;
    }

    /**
     * @param  list<array<string, mixed>>  $oldItems
     */
    private static function itemsDiffer(array $oldItems, DraftOrder $draft): bool
    {
        $draft->loadMissing('items');

        $oldFingerprint = json_encode(self::normalizedItemFingerprint($oldItems));
        $persistedFingerprint = json_encode(self::normalizedItemFingerprint(
            $draft->items->map(fn ($item) => [
                'product_variant_id' => $item->product_variant_id,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
            ])->all()
        ));

        return $oldFingerprint !== $persistedFingerprint;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array{product_variant_id: int, quantity: int, unit_price: string}>
     */
    private static function normalizedItemFingerprint(array $items): array
    {
        $merged = [];

        foreach ($items as $row) {
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            if ($variantId <= 0) {
                continue;
            }

            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $unitPrice = number_format((float) ($row['unit_price'] ?? 0), 2, '.', '');

            if (isset($merged[$variantId])) {
                $merged[$variantId]['quantity'] += $quantity;

                continue;
            }

            $merged[$variantId] = [
                'product_variant_id' => $variantId,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
            ];
        }

        ksort($merged);

        return array_values($merged);
    }

    private static function moneyDiffers(mixed $left, mixed $right): bool
    {
        return number_format((float) $left, 2, '.', '') !== number_format((float) $right, 2, '.', '');
    }

    private static function stringDiffers(mixed $left, mixed $right): bool
    {
        return strtoupper(trim((string) $left)) !== strtoupper(trim((string) $right));
    }
}
