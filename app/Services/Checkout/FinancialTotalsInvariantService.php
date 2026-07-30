<?php

namespace App\Services\Checkout;

use App\Exceptions\CheckoutTotalsMismatchException;
use App\Models\Checkout;
use App\Models\CheckoutTaxLine;
use App\Models\Order;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;

class FinancialTotalsInvariantService
{
    public function assertCheckoutConsistent(Checkout $checkout): void
    {
        $checkout->loadMissing(['items', 'taxLines']);

        $currency = strtoupper((string) $checkout->currency_code);
        $pricesIncludeTax = (bool) data_get($checkout->metadata, 'tax_snapshot.prices_include_tax', false);

        $itemsSubtotal = $this->zero($currency);
        $itemsDiscount = $this->zero($currency);
        $itemsTax = $this->zero($currency);

        foreach ($checkout->items as $item) {
            $subtotal = $this->money($item->subtotal, $currency);
            $discount = $this->money($item->discount_amount, $currency);
            $tax = $this->money($item->tax_amount, $currency);
            $total = $this->money($item->total, $currency);

            $itemsSubtotal = CurrencyPrecision::roundMajor(bcadd($itemsSubtotal, $subtotal, 6), $currency);
            $itemsDiscount = CurrencyPrecision::roundMajor(bcadd($itemsDiscount, $discount, 6), $currency);
            $itemsTax = CurrencyPrecision::roundMajor(bcadd($itemsTax, $tax, 6), $currency);

            $expectedItemTotal = $pricesIncludeTax
                ? CurrencyPrecision::roundMajor(bcsub($subtotal, $discount, 6), $currency)
                : CurrencyPrecision::roundMajor(bcadd(bcsub($subtotal, $discount, 6), $tax, 6), $currency);

            if (bccomp($expectedItemTotal, '0', 6) < 0) {
                $expectedItemTotal = $this->zero($currency);
            }

            if (! $this->sameAmount($expectedItemTotal, $total, $currency)) {
                $this->fail($checkout, [
                    'reason' => 'item_total_mismatch',
                    'checkout_item_id' => $item->id,
                    'expected_item_total' => $expectedItemTotal,
                    'actual_item_total' => $total,
                ]);
            }
        }

        if (! $this->sameAmount($itemsSubtotal, (string) $checkout->subtotal, $currency)) {
            $this->fail($checkout, [
                'reason' => 'subtotal_sum_mismatch',
                'expected' => $itemsSubtotal,
                'actual' => (string) $checkout->subtotal,
            ]);
        }

        if (! $this->sameAmount($itemsDiscount, (string) $checkout->discount_total, $currency)) {
            $this->fail($checkout, [
                'reason' => 'discount_sum_mismatch',
                'expected' => $itemsDiscount,
                'actual' => (string) $checkout->discount_total,
            ]);
        }

        $taxLinesTotal = $this->zero($currency);
        foreach ($checkout->taxLines as $taxLine) {
            $taxLinesTotal = CurrencyPrecision::roundMajor(
                bcadd($taxLinesTotal, $this->money($taxLine->tax_amount, $currency), 6),
                $currency,
            );
        }

        if (! $this->sameAmount($taxLinesTotal, (string) $checkout->tax_total, $currency)) {
            $this->fail($checkout, [
                'reason' => 'tax_lines_sum_mismatch',
                'expected' => $taxLinesTotal,
                'actual' => (string) $checkout->tax_total,
            ]);
        }

        $shipping = $this->money($checkout->shipping_total, $currency);
        $shippingTax = $this->zero($currency);
        foreach ($checkout->taxLines->where('applies_to', CheckoutTaxLine::APPLIES_TO_SHIPPING) as $taxLine) {
            $shippingTax = CurrencyPrecision::roundMajor(
                bcadd($shippingTax, $this->money($taxLine->tax_amount, $currency), 6),
                $currency,
            );
        }

        $subtotal = $this->money($checkout->subtotal, $currency);
        $discount = $this->money($checkout->discount_total, $currency);
        $taxTotal = $this->money($checkout->tax_total, $currency);

        $expectedGrand = $pricesIncludeTax
            ? CurrencyPrecision::roundMajor(
                bcadd(bcsub(bcadd($subtotal, $shipping, 6), $discount, 6), $shippingTax, 6),
                $currency,
            )
            : CurrencyPrecision::roundMajor(
                bcadd(bcadd(bcsub($subtotal, $discount, 6), $shipping, 6), $taxTotal, 6),
                $currency,
            );

        if (bccomp($expectedGrand, '0', 6) < 0) {
            $expectedGrand = $this->zero($currency);
        }

        if (! $this->sameAmount($expectedGrand, (string) $checkout->grand_total, $currency)) {
            $this->fail($checkout, [
                'reason' => 'grand_total_mismatch',
                'expected' => $expectedGrand,
                'actual' => (string) $checkout->grand_total,
                'prices_include_tax' => $pricesIncludeTax,
            ]);
        }
    }

    public function assertOrderMatchesCheckout(Order $order, Checkout $checkout): void
    {
        $order->loadMissing(['items', 'taxLines']);
        $checkout->loadMissing(['items', 'taxLines']);

        $currency = strtoupper((string) $checkout->currency_code);

        if (strtoupper((string) $order->currency_code) !== $currency) {
            $this->fail($checkout, [
                'reason' => 'order_currency_mismatch',
                'order_id' => $order->id,
                'checkout_currency' => $currency,
                'order_currency' => (string) $order->currency_code,
            ]);
        }

        $headerPairs = [
            'subtotal' => [(string) $checkout->subtotal, (string) $order->subtotal],
            'discount' => [(string) $checkout->discount_total, (string) $order->discount],
            'shipping' => [(string) $checkout->shipping_total, (string) $order->shipping],
            'shipping_tax' => [$this->shippingTaxTotal($checkout), (string) $order->shipping_tax],
            'tax' => [(string) $checkout->tax_total, (string) $order->tax],
            'total' => [(string) $checkout->grand_total, (string) $order->total],
            'grand_total' => [(string) $checkout->grand_total, (string) $order->grand_total],
        ];

        foreach ($headerPairs as $field => [$expected, $actual]) {
            if (! $this->sameAmount($expected, $actual, $currency)) {
                $this->fail($checkout, [
                    'reason' => 'order_header_mismatch',
                    'field' => $field,
                    'order_id' => $order->id,
                    'expected' => $expected,
                    'actual' => $actual,
                ]);
            }
        }

        $checkoutItems = $checkout->items->keyBy('id');
        if ($order->items->count() !== $checkoutItems->count()) {
            $this->fail($checkout, [
                'reason' => 'order_item_count_mismatch',
                'order_id' => $order->id,
                'checkout_count' => $checkoutItems->count(),
                'order_count' => $order->items->count(),
            ]);
        }

        foreach ($order->items as $orderItem) {
            $checkoutItemId = (int) data_get($orderItem->meta, 'checkout_item_id');
            $checkoutItem = $checkoutItems->get($checkoutItemId);

            if (! $checkoutItem) {
                $this->fail($checkout, [
                    'reason' => 'order_item_missing_checkout_mapping',
                    'order_id' => $order->id,
                    'order_item_id' => $orderItem->id,
                    'checkout_item_id' => $checkoutItemId,
                ]);
            }

            $pairs = [
                'quantity' => [(string) $checkoutItem->quantity, (string) $orderItem->quantity],
                'unit_price' => [(string) $checkoutItem->unit_price, (string) $orderItem->unit_price],
                'subtotal' => [(string) $checkoutItem->subtotal, (string) $orderItem->subtotal],
                'discount_amount' => [(string) $checkoutItem->discount_amount, (string) $orderItem->discount_amount],
                'tax_amount' => [(string) $checkoutItem->tax_amount, (string) $orderItem->tax_amount],
                'total' => [(string) $checkoutItem->total, (string) $orderItem->total],
            ];

            foreach ($pairs as $field => [$expected, $actual]) {
                if ($field === 'quantity') {
                    if ((int) $expected !== (int) $actual) {
                        $this->fail($checkout, [
                            'reason' => 'order_item_field_mismatch',
                            'field' => $field,
                            'checkout_item_id' => $checkoutItemId,
                            'expected' => $expected,
                            'actual' => $actual,
                        ]);
                    }

                    continue;
                }

                if (! $this->sameAmount($expected, $actual, $currency)) {
                    $this->fail($checkout, [
                        'reason' => 'order_item_field_mismatch',
                        'field' => $field,
                        'checkout_item_id' => $checkoutItemId,
                        'expected' => $expected,
                        'actual' => $actual,
                    ]);
                }
            }
        }

        if ($order->taxLines->count() !== $checkout->taxLines->count()) {
            $this->fail($checkout, [
                'reason' => 'order_tax_line_count_mismatch',
                'order_id' => $order->id,
                'checkout_count' => $checkout->taxLines->count(),
                'order_count' => $order->taxLines->count(),
            ]);
        }

        $checkoutTax = $checkout->taxLines
            ->map(fn ($line): string => implode('|', [
                (string) $line->tax_rate_id,
                (string) $line->applies_to,
                $this->money($line->taxable_amount, $currency),
                $this->money($line->tax_amount, $currency),
            ]))
            ->sort()
            ->values()
            ->all();

        $orderTax = $order->taxLines
            ->map(fn ($line): string => implode('|', [
                (string) $line->tax_rate_id,
                (string) $line->applies_to,
                $this->money($line->taxable_amount, $currency),
                $this->money($line->tax_amount, $currency),
            ]))
            ->sort()
            ->values()
            ->all();

        if ($checkoutTax !== $orderTax) {
            $this->fail($checkout, [
                'reason' => 'order_tax_lines_mismatch',
                'order_id' => $order->id,
                'expected' => $checkoutTax,
                'actual' => $orderTax,
            ]);
        }

        $checkoutTaxSnapshot = data_get($checkout->metadata, 'tax_snapshot');
        $orderTaxSnapshot = data_get($order->meta, 'tax_snapshot');
        if ($checkoutTaxSnapshot !== $orderTaxSnapshot) {
            $this->fail($checkout, [
                'reason' => 'tax_snapshot_mismatch',
                'order_id' => $order->id,
            ]);
        }

        $checkoutCoupon = data_get($checkout->metadata, 'coupon_snapshot');
        $orderCoupon = data_get($order->meta, 'coupon_snapshot');
        if ($checkoutCoupon !== $orderCoupon) {
            $this->fail($checkout, [
                'reason' => 'coupon_snapshot_mismatch',
                'order_id' => $order->id,
            ]);
        }
    }

    private function shippingTaxTotal(Checkout $checkout): string
    {
        $currency = strtoupper((string) $checkout->currency_code);
        $total = $this->zero($currency);

        foreach ($checkout->taxLines->where('applies_to', CheckoutTaxLine::APPLIES_TO_SHIPPING) as $line) {
            $total = CurrencyPrecision::roundMajor(
                bcadd($total, $this->money($line->tax_amount, $currency), 6),
                $currency,
            );
        }

        return $total;
    }

    private function sameAmount(string $left, string $right, string $currency): bool
    {
        return CurrencyPrecision::toMinorUnits($left, $currency)
            === CurrencyPrecision::toMinorUnits($right, $currency);
    }

    private function money(mixed $value, string $currency): string
    {
        if ($value === null || trim((string) $value) === '') {
            return $this->zero($currency);
        }

        return CurrencyPrecision::roundMajor(
            DecimalString::normalizeNonNegative((string) $value),
            $currency,
        );
    }

    private function zero(string $currency): string
    {
        return CurrencyPrecision::roundMajor('0', $currency);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function fail(Checkout $checkout, array $context): never
    {
        throw new CheckoutTotalsMismatchException((int) $checkout->id, $context);
    }
}
