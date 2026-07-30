<?php

namespace App\Services\Draft;

use App\Data\Tax\MatchedTaxRate;
use App\Data\Tax\TaxAddressInput;
use App\Data\Tax\TaxCalculationRequest;
use App\Data\Tax\TaxCalculationResult;
use App\Data\Tax\TaxLineItemInput;
use App\Models\DraftOrder;
use App\Models\DraftTaxLine;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Services\Checkout\CheckoutTotalsService;
use App\Services\Tax\TaxCalculator;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;
use App\Support\Tax\TaxDisplayPresenter;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DraftTaxService
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function previewFromFormPayload(Store $store, array $payload): array
    {
        $settings = TaxSetting::query()
            ->where('store_id', $store->id)
            ->first();

        if (! $settings || ! $settings->enabled) {
            return [
                'ready' => false,
                'guidance' => 'Enable platform tax in Tax settings to preview automatic tax.',
            ];
        }

        $destination = new TaxAddressInput(
            TaxRate::normalizeCountryCode($payload['shipping_country'] ?? ''),
            TaxRate::normalizeRegionCode($payload['shipping_state'] ?? ''),
        );

        if ($destination->countryCode === '') {
            return [
                'ready' => false,
                'guidance' => 'Enter a two-letter shipping country code (for example US) to preview automatic tax.',
            ];
        }

        $currencyCode = (string) ($store->currency ?: 'USD');
        $lineInputs = [];
        $variantIds = collect($payload['items'] ?? [])
            ->pluck('product_variant_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        $variants = ProductVariant::query()
            ->where('store_id', $store->id)
            ->whereIn('id', $variantIds)
            ->with('product')
            ->get()
            ->keyBy('id');

        foreach ($payload['items'] ?? [] as $index => $row) {
            $variantId = (int) ($row['product_variant_id'] ?? 0);
            if ($variantId <= 0) {
                continue;
            }

            $variant = $variants->get($variantId);
            $quantity = max(1, (int) ($row['quantity'] ?? 1));
            $unitPrice = CurrencyPrecision::roundMajor(
                DecimalString::normalizeNonNegative((string) ($row['unit_price'] ?? $variant?->price ?? 0)),
                $currencyCode,
            );
            $lineDiscount = CurrencyPrecision::roundMajor(
                DecimalString::normalizeNonNegative((string) ($row['discount_amount'] ?? 0)),
                $currencyCode,
            );

            $lineInputs[] = new TaxLineItemInput(
                lineKey: 'preview:'.$index,
                quantity: $quantity,
                unitPrice: $unitPrice,
                isTaxable: (bool) ($variant?->product?->is_taxable ?? true),
                discountAmount: $lineDiscount,
            );
        }

        if ($lineInputs === []) {
            return [
                'ready' => false,
                'guidance' => 'Add at least one product line to preview automatic tax.',
            ];
        }

        $result = $this->taxCalculator->calculate(new TaxCalculationRequest(
            store: $store,
            settings: $settings,
            currencyCode: $currencyCode,
            items: $lineInputs,
            shippingAmount: CurrencyPrecision::roundMajor(
                DecimalString::normalizeNonNegative((string) ($payload['shipping_total'] ?? 0)),
                $currencyCode,
            ),
            destination: $destination,
        ));

        $snapshot = [
            'destination' => [
                'country_code' => $destination->countryCode,
                'region_code' => $destination->regionCode,
            ],
            'matched_rate' => $result->matchedRate ? [
                'name' => $result->matchedRate->name,
                'rate_percent' => $result->matchedRate->ratePercent,
                'country_code' => $result->matchedRate->countryCode,
                'region_code' => $result->matchedRate->regionCode,
            ] : null,
            'tax_calculation_skipped' => $result->taxCalculationSkipped,
            'skip_reason' => $result->skipReason,
            'prices_include_tax' => (bool) $settings->prices_include_tax,
        ];

        $discount = CurrencyPrecision::roundMajor(
            DecimalString::normalizeNonNegative((string) ($payload['discount_total'] ?? 0)),
            $currencyCode,
        );
        $estimatedTotal = $this->grandTotal(
            subtotal: $result->itemsSubtotal,
            shippingTotal: CurrencyPrecision::roundMajor(
                DecimalString::normalizeNonNegative((string) ($payload['shipping_total'] ?? 0)),
                $currencyCode,
            ),
            itemsTax: $result->itemsTax,
            shippingTax: $result->shippingTax,
            discountTotal: $discount,
            pricesIncludeTax: (bool) $settings->prices_include_tax,
            currencyCode: $currencyCode,
        );

        return [
            'ready' => true,
            'total_tax' => $result->totalTax,
            'items_tax' => $result->itemsTax,
            'shipping_tax' => $result->shippingTax,
            'subtotal' => $result->itemsSubtotal,
            'estimated_total' => $estimatedTotal,
            'prices_include_tax' => (bool) $settings->prices_include_tax,
            'destination_label' => TaxDisplayPresenter::destinationLabel($snapshot),
            'matched_rate_label' => TaxDisplayPresenter::matchedRateLabel($snapshot),
            'guidance' => TaxDisplayPresenter::calculationGuidance([
                'source' => TaxDisplayPresenter::SOURCE_PLATFORM_CALCULATED,
                'total_tax' => $result->totalTax,
                'snapshot' => $snapshot,
            ]),
        ];
    }

    public function calculate(DraftOrder $draft, Store $store): DraftOrder
    {
        if ((int) $draft->store_id !== (int) $store->id) {
            abort(404);
        }

        if ($draft->status !== DraftOrder::STATUS_DRAFT) {
            throw ValidationException::withMessages([
                'draft_order' => 'Only editable draft orders can have tax calculated.',
            ]);
        }

        return DB::transaction(function () use ($draft, $store): DraftOrder {
            $draft = DraftOrder::query()
                ->where('store_id', $store->id)
                ->whereKey($draft->id)
                ->lockForUpdate()
                ->firstOrFail();

            $draft->load(['items.variant.product', 'taxLines']);

            if ($draft->items->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'Add at least one product before calculating tax.',
                ]);
            }

            $settings = TaxSetting::query()
                ->where('store_id', $store->id)
                ->lockForUpdate()
                ->first();

            if (! $settings) {
                throw ValidationException::withMessages([
                    'tax' => 'Tax settings are not available for this store yet.',
                ]);
            }

            if (! $settings->enabled) {
                throw ValidationException::withMessages([
                    'tax' => 'Tax calculation is disabled in store settings.',
                ]);
            }

            $destination = $this->destinationFromDraft($draft);
            if ($destination->countryCode === '') {
                throw ValidationException::withMessages([
                    'shipping_country' => 'Add a shipping country before calculating tax.',
                ]);
            }

            $currencyCode = (string) ($draft->currency ?: $store->currency ?: 'USD');
            $lineInputs = [];
            $taxableByLineKey = [];
            $itemDiscounts = data_get($draft->metadata, 'coupon_snapshot.item_discounts', []);
            if (! is_array($itemDiscounts)) {
                $itemDiscounts = [];
            }

            foreach ($draft->items as $item) {
                $lineKey = $this->lineKey((int) $item->id);
                $variantLineKey = CheckoutTotalsService::lineKeyForVariant((int) $item->product_variant_id);
                $isTaxable = $this->isDraftItemTaxable($item);
                $lineDiscount = CurrencyPrecision::roundMajor(
                    DecimalString::normalizeNonNegative((string) ($itemDiscounts[$variantLineKey] ?? '0')),
                    $currencyCode,
                );
                $lineInputs[] = new TaxLineItemInput(
                    lineKey: $lineKey,
                    quantity: (int) $item->quantity,
                    unitPrice: (string) $item->unit_price,
                    isTaxable: $isTaxable,
                    discountAmount: $lineDiscount,
                );
                $taxableByLineKey[$lineKey] = $isTaxable;
            }

            $calculatedAt = Carbon::now('UTC');
            $result = $this->taxCalculator->calculate(new TaxCalculationRequest(
                store: $store,
                settings: $settings,
                currencyCode: $currencyCode,
                items: $lineInputs,
                shippingAmount: (string) $draft->shipping_total,
                destination: $destination,
            ));

            $allocationsByLineKey = collect($result->itemAllocations)->keyBy('lineKey');

            foreach ($draft->items as $item) {
                $lineKey = $this->lineKey((int) $item->id);
                $allocation = $allocationsByLineKey->get($lineKey);
                $metadata = is_array($item->metadata) ? $item->metadata : [];
                $metadata['tax'] = [
                    'is_taxable' => $taxableByLineKey[$lineKey] ?? false,
                    'prices_include_tax' => (bool) $settings->prices_include_tax,
                    'settings_version' => (int) $settings->settings_version,
                ];

                $item->forceFill([
                    'tax_amount' => $allocation?->taxAmount ?? $this->zero($currencyCode),
                    'metadata' => $metadata,
                ])->save();
            }

            DraftTaxLine::query()
                ->where('store_id', $store->id)
                ->where('draft_order_id', $draft->id)
                ->delete();

            foreach ($result->taxLines as $line) {
                DraftTaxLine::query()->create([
                    'store_id' => $store->id,
                    'draft_order_id' => $draft->id,
                    'tax_rate_id' => $line->taxRateId,
                    'jurisdiction_country_code' => $line->jurisdictionCountryCode,
                    'jurisdiction_region_code' => $line->jurisdictionRegionCode,
                    'rate_percent' => $line->ratePercent,
                    'taxable_amount' => $line->taxableAmount,
                    'tax_amount' => $line->taxAmount,
                    'applies_to' => $line->appliesTo,
                    'settings_version' => $line->settingsVersion,
                    'calculated_at' => $calculatedAt,
                ]);
            }

            $metadata = is_array($draft->metadata) ? $draft->metadata : [];
            $metadata['tax_source'] = DraftOrder::TAX_SOURCE_CALCULATED;
            $metadata['tax_snapshot'] = $this->taxSnapshot($settings, $destination, $result, $calculatedAt);

            $draft->forceFill([
                'subtotal' => $result->itemsSubtotal,
                'tax_total' => $result->totalTax,
                'total' => $this->grandTotal(
                    subtotal: $result->itemsSubtotal,
                    shippingTotal: (string) $draft->shipping_total,
                    itemsTax: $result->itemsTax,
                    shippingTax: $result->shippingTax,
                    discountTotal: (string) $draft->discount_total,
                    pricesIncludeTax: (bool) $settings->prices_include_tax,
                    currencyCode: $currencyCode,
                ),
                'metadata' => $metadata,
            ])->save();

            return $draft->fresh(['customer', 'items.variant.product', 'taxLines']);
        });
    }

    private function lineKey(int $draftOrderItemId): string
    {
        return 'draft_item:'.$draftOrderItemId;
    }

    private function isDraftItemTaxable($item): bool
    {
        $metadataTaxable = data_get($item->metadata, 'tax.is_taxable');
        if (is_bool($metadataTaxable)) {
            return $metadataTaxable;
        }

        return (bool) ($item->variant?->product?->is_taxable ?? true);
    }

    private function destinationFromDraft(DraftOrder $draft): TaxAddressInput
    {
        $address = $draft->shippingAddress();
        $countryCode = trim((string) ($address['country_code'] ?? ''));

        if (preg_match('/^[A-Za-z]{2}$/', $countryCode) === 1) {
            $countryCode = TaxRate::normalizeCountryCode($countryCode);
        } else {
            $country = trim((string) ($address['country'] ?? ''));
            $countryCode = preg_match('/^[A-Za-z]{2}$/', $country) === 1
                ? TaxRate::normalizeCountryCode($country)
                : '';
        }

        $region = trim((string) ($address['province_code'] ?? ''));
        if ($region === '') {
            $region = trim((string) ($address['state'] ?? ''));
        }

        return new TaxAddressInput($countryCode, $region);
    }

    private function grandTotal(
        string $subtotal,
        string $shippingTotal,
        string $itemsTax,
        string $shippingTax,
        string $discountTotal,
        bool $pricesIncludeTax,
        string $currencyCode,
    ): string {
        if ($pricesIncludeTax) {
            $raw = bcadd(
                bcsub(bcadd($subtotal, $shippingTotal, 6), $discountTotal, 6),
                $shippingTax,
                6,
            );
        } else {
            $raw = bcsub(
                bcadd(
                    bcadd($subtotal, $shippingTotal, 6),
                    bcadd($itemsTax, $shippingTax, 6),
                    6,
                ),
                $discountTotal,
                6,
            );
        }

        $rounded = CurrencyPrecision::roundMajor($raw, $currencyCode);
        $zero = $this->zero($currencyCode);

        return bccomp($rounded, $zero, 6) < 0 ? $zero : $rounded;
    }

    private function zero(string $currencyCode): string
    {
        return CurrencyPrecision::roundMajor('0', $currencyCode);
    }

    /**
     * @return array<string, mixed>
     */
    private function taxSnapshot(
        TaxSetting $settings,
        TaxAddressInput $destination,
        TaxCalculationResult $result,
        CarbonInterface $calculatedAt,
    ): array {
        return [
            'enabled' => (bool) $settings->enabled,
            'prices_include_tax' => (bool) $settings->prices_include_tax,
            'shipping_taxable' => (bool) $settings->shipping_taxable,
            'settings_version' => (int) $settings->settings_version,
            'destination' => [
                'country_code' => $destination->countryCode,
                'region_code' => $destination->regionCode,
            ],
            'matched_rate' => $this->matchedRateSnapshot($result->matchedRate),
            'tax_calculation_skipped' => $result->taxCalculationSkipped,
            'skip_reason' => $result->skipReason,
            'calculated_at' => $calculatedAt->toIso8601String(),
        ];
    }

    /**
     * @return array<string, int|string|null>|null
     */
    private function matchedRateSnapshot(?MatchedTaxRate $matchedRate): ?array
    {
        if ($matchedRate === null) {
            return null;
        }

        return [
            'tax_rate_id' => $matchedRate->taxRateId,
            'name' => $matchedRate->name,
            'country_code' => $matchedRate->countryCode,
            'region_code' => $matchedRate->regionCode,
            'rate_percent' => $matchedRate->ratePercent,
            'priority' => $matchedRate->priority,
        ];
    }
}
