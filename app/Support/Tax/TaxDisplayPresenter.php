<?php

namespace App\Support\Tax;

use App\Models\DraftOrder;
use App\Models\DraftTaxLine;
use App\Models\Order;
use App\Models\OrderTaxLine;
use Illuminate\Support\Collection;

class TaxDisplayPresenter
{
    public const SOURCE_PLATFORM_CALCULATED = 'platform_calculated';

    public const SOURCE_EXTERNAL_PRESERVED = 'external_preserved';

    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_NONE = 'none';

    public const SOURCE_LEGACY = 'legacy';

    /**
     * @return array{
     *     source: string,
     *     source_label: string,
     *     snapshot: array<string, mixed>|null,
     *     tax_lines: Collection<int, OrderTaxLine>,
     *     prices_include_tax: bool,
     *     item_tax_total: string,
     *     shipping_tax_total: string,
     *     total_tax: string,
     *     has_breakdown: bool,
     *     show_inclusive_note: bool,
     * }
     */
    public static function forOrder(Order $order): array
    {
        $order->loadMissing('taxLines');
        $snapshot = self::snapshotFromMeta($order->meta);
        $source = self::resolveOrderSource($order, $snapshot);
        $taxLines = $order->taxLines->sortBy('id')->values();
        $pricesIncludeTax = (bool) data_get($snapshot, 'prices_include_tax', false);

        $itemTaxTotal = self::sumTaxLines($taxLines, OrderTaxLine::APPLIES_TO_ITEMS);
        $shippingTaxTotal = self::sumTaxLines($taxLines, OrderTaxLine::APPLIES_TO_SHIPPING);

        if ($source === self::SOURCE_EXTERNAL_PRESERVED) {
            return [
                'source' => $source,
                'source_label' => 'Preserved from external checkout',
                'snapshot' => $snapshot,
                'tax_lines' => collect(),
                'prices_include_tax' => false,
                'item_tax_total' => '0.00',
                'shipping_tax_total' => '0.00',
                'total_tax' => number_format((float) $order->tax, 2, '.', ''),
                'has_breakdown' => false,
                'show_inclusive_note' => false,
            ];
        }

        if ($source === self::SOURCE_MANUAL) {
            return [
                'source' => $source,
                'source_label' => 'Entered manually',
                'snapshot' => $snapshot,
                'tax_lines' => collect(),
                'prices_include_tax' => false,
                'item_tax_total' => '0.00',
                'shipping_tax_total' => '0.00',
                'total_tax' => number_format((float) $order->tax, 2, '.', ''),
                'has_breakdown' => false,
                'show_inclusive_note' => false,
            ];
        }

        if ($source === self::SOURCE_NONE) {
            return [
                'source' => $source,
                'source_label' => 'No tax recorded',
                'snapshot' => $snapshot,
                'tax_lines' => collect(),
                'prices_include_tax' => false,
                'item_tax_total' => '0.00',
                'shipping_tax_total' => '0.00',
                'total_tax' => number_format((float) $order->tax, 2, '.', ''),
                'has_breakdown' => false,
                'show_inclusive_note' => false,
            ];
        }

        if ($taxLines->isEmpty() && $source === self::SOURCE_LEGACY) {
            return [
                'source' => $source,
                'source_label' => 'Tax total only',
                'snapshot' => $snapshot,
                'tax_lines' => collect(),
                'prices_include_tax' => $pricesIncludeTax,
                'item_tax_total' => '0.00',
                'shipping_tax_total' => number_format((float) $order->shipping_tax, 2, '.', ''),
                'total_tax' => number_format((float) $order->tax, 2, '.', ''),
                'has_breakdown' => false,
                'show_inclusive_note' => false,
            ];
        }

        if ($itemTaxTotal === '0.00' && $taxLines->isNotEmpty()) {
            $itemTaxTotal = number_format((float) $order->items->sum('tax_amount'), 2, '.', '');
        }

        if ($shippingTaxTotal === '0.00' && (float) $order->shipping_tax > 0) {
            $shippingTaxTotal = number_format((float) $order->shipping_tax, 2, '.', '');
        }

        return [
            'source' => self::SOURCE_PLATFORM_CALCULATED,
            'source_label' => 'Calculated by platform',
            'snapshot' => $snapshot,
            'tax_lines' => $taxLines,
            'prices_include_tax' => $pricesIncludeTax,
            'item_tax_total' => $itemTaxTotal,
            'shipping_tax_total' => $shippingTaxTotal,
            'total_tax' => number_format((float) $order->tax, 2, '.', ''),
            'has_breakdown' => $taxLines->isNotEmpty() || $snapshot !== null,
            'show_inclusive_note' => $pricesIncludeTax,
        ];
    }

    /**
     * @return array{
     *     source: string,
     *     source_label: string,
     *     snapshot: array<string, mixed>|null,
     *     tax_lines: Collection<int, DraftTaxLine>,
     *     prices_include_tax: bool,
     *     item_tax_total: string,
     *     shipping_tax_total: string,
     *     total_tax: string,
     *     has_breakdown: bool,
     *     show_inclusive_note: bool,
     * }
     */
    public static function forDraft(DraftOrder $draft): array
    {
        $draft->loadMissing('taxLines');
        $snapshot = self::snapshotFromDraftMetadata($draft->metadata);
        $source = $draft->taxSource() === DraftOrder::TAX_SOURCE_CALCULATED
            ? self::SOURCE_PLATFORM_CALCULATED
            : self::SOURCE_MANUAL;
        $taxLines = $draft->taxLines->sortBy('id')->values();
        $pricesIncludeTax = (bool) data_get($snapshot, 'prices_include_tax', false);

        if ($source === self::SOURCE_MANUAL) {
            return [
                'source' => $source,
                'source_label' => 'Manual tax',
                'snapshot' => $snapshot,
                'tax_lines' => collect(),
                'prices_include_tax' => false,
                'item_tax_total' => '0.00',
                'shipping_tax_total' => '0.00',
                'total_tax' => number_format((float) $draft->tax_total, 2, '.', ''),
                'has_breakdown' => false,
                'show_inclusive_note' => false,
            ];
        }

        $itemTaxTotal = self::sumTaxLines($taxLines, DraftTaxLine::APPLIES_TO_ITEMS);
        $shippingTaxTotal = self::sumTaxLines($taxLines, DraftTaxLine::APPLIES_TO_SHIPPING);

        if ($itemTaxTotal === '0.00' && $draft->relationLoaded('items')) {
            $itemTaxTotal = number_format((float) $draft->items->sum('tax_amount'), 2, '.', '');
        }

        return [
            'source' => self::SOURCE_PLATFORM_CALCULATED,
            'source_label' => 'Calculated from store settings',
            'snapshot' => $snapshot,
            'tax_lines' => $taxLines,
            'prices_include_tax' => $pricesIncludeTax,
            'item_tax_total' => $itemTaxTotal,
            'shipping_tax_total' => $shippingTaxTotal,
            'total_tax' => number_format((float) $draft->tax_total, 2, '.', ''),
            'has_breakdown' => $taxLines->isNotEmpty() || $snapshot !== null,
            'show_inclusive_note' => $pricesIncludeTax,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $meta
     * @return array<string, mixed>|null
     */
    public static function snapshotFromMeta(?array $meta): ?array
    {
        $snapshot = data_get($meta, 'tax_snapshot');

        return is_array($snapshot) && $snapshot !== [] ? $snapshot : null;
    }

    /**
     * @param  array<string, mixed>|null  $metadata
     * @return array<string, mixed>|null
     */
    public static function snapshotFromDraftMetadata(?array $metadata): ?array
    {
        $snapshot = data_get($metadata, 'tax_snapshot');

        return is_array($snapshot) && $snapshot !== [] ? $snapshot : null;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public static function destinationLabel(?array $snapshot): ?string
    {
        if ($snapshot === null) {
            return null;
        }

        $country = strtoupper((string) data_get($snapshot, 'destination.country_code', ''));
        $region = strtoupper((string) data_get($snapshot, 'destination.region_code', ''));

        if ($country === '') {
            return null;
        }

        return $region !== '' ? "{$country} / {$region}" : $country;
    }

    /**
     * @param  array<string, mixed>|null  $snapshot
     */
    public static function matchedRateLabel(?array $snapshot): ?string
    {
        $rate = data_get($snapshot, 'matched_rate');
        if (! is_array($rate)) {
            return null;
        }

        $name = trim((string) ($rate['name'] ?? ''));
        $percent = $rate['rate_percent'] ?? null;

        if ($name === '' && $percent === null) {
            return null;
        }

        if ($name !== '' && $percent !== null) {
            return "{$name} ({$percent}%)";
        }

        return $name !== '' ? $name : "{$percent}%";
    }

    public static function priceModeLabel(bool $pricesIncludeTax): string
    {
        return $pricesIncludeTax ? 'Tax included in prices' : 'Tax added to prices';
    }

    /**
     * @param  Collection<int, OrderTaxLine|DraftTaxLine>  $lines
     */
    public static function lineAppliesLabel(string $appliesTo): string
    {
        return match ($appliesTo) {
            OrderTaxLine::APPLIES_TO_ITEMS, DraftTaxLine::APPLIES_TO_ITEMS => 'Items',
            OrderTaxLine::APPLIES_TO_SHIPPING, DraftTaxLine::APPLIES_TO_SHIPPING => 'Shipping',
            default => ucfirst(str_replace('_', ' ', $appliesTo)),
        };
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private static function resolveOrderSource(Order $order, ?array $snapshot): string
    {
        if ($snapshot !== null || $order->taxLines->isNotEmpty()) {
            return self::SOURCE_PLATFORM_CALCULATED;
        }

        if ($order->order_source === 'external_checkout') {
            return self::SOURCE_EXTERNAL_PRESERVED;
        }

        if ($order->order_source === 'manual') {
            return self::SOURCE_MANUAL;
        }

        if ((float) $order->tax > 0) {
            return self::SOURCE_LEGACY;
        }

        return self::SOURCE_NONE;
    }

    /**
     * @param  Collection<int, OrderTaxLine|DraftTaxLine>  $lines
     */
    private static function sumTaxLines(Collection $lines, string $appliesTo): string
    {
        $total = $lines
            ->where('applies_to', $appliesTo)
            ->reduce(fn (string $carry, $line): string => bcadd($carry, (string) $line->tax_amount, 2), '0');

        return bcadd($total, '0', 2);
    }
}
