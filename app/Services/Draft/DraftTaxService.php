<?php

namespace App\Services\Draft;

use App\Data\Tax\MatchedTaxRate;
use App\Data\Tax\TaxAddressInput;
use App\Data\Tax\TaxCalculationRequest;
use App\Data\Tax\TaxCalculationResult;
use App\Data\Tax\TaxLineItemInput;
use App\Models\DraftOrder;
use App\Models\DraftTaxLine;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Services\Tax\TaxCalculator;
use App\Support\Money\CurrencyPrecision;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DraftTaxService
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
    ) {}

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

            foreach ($draft->items as $item) {
                $lineKey = $this->lineKey((int) $item->id);
                $isTaxable = $this->isDraftItemTaxable($item);
                $lineInputs[] = new TaxLineItemInput(
                    lineKey: $lineKey,
                    quantity: (int) $item->quantity,
                    unitPrice: (string) $item->unit_price,
                    isTaxable: $isTaxable,
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
            'country_code' => $matchedRate->countryCode,
            'region_code' => $matchedRate->regionCode,
            'rate_percent' => $matchedRate->ratePercent,
            'priority' => $matchedRate->priority,
        ];
    }
}
