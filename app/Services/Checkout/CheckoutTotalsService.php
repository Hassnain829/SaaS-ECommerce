<?php

namespace App\Services\Checkout;

use App\Data\Checkout\CheckoutItemTotals;
use App\Data\Checkout\CheckoutTotalsResult;
use App\Data\Tax\MatchedTaxRate;
use App\Data\Tax\TaxAddressInput;
use App\Data\Tax\TaxCalculationRequest;
use App\Data\Tax\TaxCalculationResult;
use App\Data\Tax\TaxLineItemInput;
use App\Models\Checkout;
use App\Models\CheckoutTaxLine;
use App\Models\ProductVariant;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Services\Tax\TaxCalculator;
use App\Support\Money\CurrencyPrecision;
use App\Support\Money\DecimalString;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class CheckoutTotalsService
{
    public function __construct(
        private readonly TaxCalculator $taxCalculator,
    ) {}

    public static function lineKeyForVariant(int $variantId): string
    {
        return 'variant:'.$variantId;
    }

    /**
     * @param  list<array{variant: ProductVariant, quantity: int}>  $preparedItems
     */
    public function itemsSubtotal(string $currencyCode, array $preparedItems): string
    {
        $total = CurrencyPrecision::roundMajor('0', $currencyCode);

        foreach ($preparedItems as $item) {
            $variant = $item['variant'];
            $quantity = (int) $item['quantity'];
            $unitPrice = $this->authoritativeUnitPrice($variant);
            $lineSubtotal = CurrencyPrecision::roundMajor(
                bcmul($unitPrice, (string) $quantity, 6),
                $currencyCode,
            );
            $total = CurrencyPrecision::roundMajor(
                bcadd($total, $lineSubtotal, 6),
                $currencyCode,
            );
        }

        return $total;
    }

    /**
     * @param  list<array{variant: ProductVariant, quantity: int}>  $preparedItems
     * @param  array<string, mixed>  $shippingAddress
     */
    public function calculate(
        Store $store,
        TaxSetting $settings,
        string $currencyCode,
        array $preparedItems,
        string|int|float $shippingTotal,
        array $shippingAddress,
        ?CarbonInterface $calculatedAt = null,
    ): CheckoutTotalsResult {
        if ((int) $settings->store_id !== (int) $store->id) {
            throw new InvalidArgumentException('Tax settings do not belong to the provided store.');
        }

        $currencyCode = trim($currencyCode);
        $shippingTotal = DecimalString::normalizeNonNegative(
            $shippingTotal,
            'Shipping amount must be a non-negative decimal amount.',
        );
        $shippingTotal = CurrencyPrecision::roundMajor($shippingTotal, $currencyCode);
        $calculatedAt ??= Carbon::now('UTC');

        $taxLineItems = [];
        $taxableByLineKey = [];

        foreach ($preparedItems as $item) {
            /** @var ProductVariant $variant */
            $variant = $item['variant'];
            $product = $variant->product;

            if (! $product) {
                throw ValidationException::withMessages([
                    'items' => 'A checkout item is missing product catalog data.',
                ]);
            }

            if ((int) $product->store_id !== (int) $store->id) {
                throw ValidationException::withMessages([
                    'items' => 'Choose product variants that belong to this store.',
                ]);
            }

            $lineKey = self::lineKeyForVariant((int) $variant->id);
            $taxLineItems[] = new TaxLineItemInput(
                lineKey: $lineKey,
                quantity: (int) $item['quantity'],
                unitPrice: $this->authoritativeUnitPrice($variant),
                isTaxable: (bool) $product->is_taxable,
            );
            $taxableByLineKey[$lineKey] = (bool) $product->is_taxable;
        }

        return $this->calculateFromLineInputs(
            store: $store,
            settings: $settings,
            currencyCode: $currencyCode,
            taxLineItems: $taxLineItems,
            taxableByLineKey: $taxableByLineKey,
            shippingTotal: $shippingTotal,
            shippingAddress: $shippingAddress,
            calculatedAt: $calculatedAt,
        );
    }

    /**
     * Recalculate an existing checkout from persisted financial snapshots only.
     *
     * @param  array<string, mixed>  $shippingAddress
     */
    public function calculateForCheckout(
        Checkout $checkout,
        TaxSetting $settings,
        string|int|float $shippingTotal,
        array $shippingAddress,
        ?CarbonInterface $calculatedAt = null,
    ): CheckoutTotalsResult {
        $checkout->loadMissing('items');

        if ((int) $settings->store_id !== (int) $checkout->store_id) {
            throw new InvalidArgumentException('Tax settings do not belong to the checkout store.');
        }

        $store = $checkout->store ?: Store::query()->findOrFail($checkout->store_id);
        $currencyCode = (string) $checkout->currency_code;
        $shippingTotal = DecimalString::normalizeNonNegative(
            $shippingTotal,
            'Shipping amount must be a non-negative decimal amount.',
        );
        $shippingTotal = CurrencyPrecision::roundMajor($shippingTotal, $currencyCode);
        $calculatedAt ??= Carbon::now('UTC');

        $taxLineItems = [];
        $taxableByLineKey = [];

        foreach ($checkout->items as $item) {
            $variantId = (int) ($item->product_variant_id ?? 0);
            if ($variantId <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'A checkout item is missing the catalog variant needed for tax calculation.',
                ]);
            }

            $quantity = (int) $item->quantity;
            if ($quantity <= 0) {
                throw ValidationException::withMessages([
                    'items' => 'A checkout item has an invalid quantity.',
                ]);
            }

            if (! is_array($item->metadata) || ! array_key_exists('tax', $item->metadata)) {
                throw ValidationException::withMessages([
                    'items' => 'A checkout item is missing its taxability snapshot.',
                ]);
            }

            $taxability = data_get($item->metadata, 'tax.is_taxable');
            if (! is_bool($taxability)) {
                throw ValidationException::withMessages([
                    'items' => 'A checkout item has an invalid taxability snapshot.',
                ]);
            }

            $lineKey = self::lineKeyForVariant($variantId);
            $taxLineItems[] = new TaxLineItemInput(
                lineKey: $lineKey,
                quantity: $quantity,
                unitPrice: DecimalString::normalizeNonNegative(
                    (string) $item->unit_price,
                    'Unit price must be a non-negative decimal amount.',
                ),
                isTaxable: $taxability,
            );
            $taxableByLineKey[$lineKey] = $taxability;
        }

        return $this->calculateFromLineInputs(
            store: $store,
            settings: $settings,
            currencyCode: $currencyCode,
            taxLineItems: $taxLineItems,
            taxableByLineKey: $taxableByLineKey,
            shippingTotal: $shippingTotal,
            shippingAddress: $shippingAddress,
            calculatedAt: $calculatedAt,
        );
    }

    /**
     * @param  list<TaxLineItemInput>  $taxLineItems
     * @param  array<string, bool>  $taxableByLineKey
     * @param  array<string, mixed>  $shippingAddress
     */
    private function calculateFromLineInputs(
        Store $store,
        TaxSetting $settings,
        string $currencyCode,
        array $taxLineItems,
        array $taxableByLineKey,
        string $shippingTotal,
        array $shippingAddress,
        CarbonInterface $calculatedAt,
    ): CheckoutTotalsResult {
        $destination = $this->normalizeDestinationFromAddress($shippingAddress);

        $taxResult = $this->taxCalculator->calculate(new TaxCalculationRequest(
            store: $store,
            settings: $settings,
            currencyCode: $currencyCode,
            items: $taxLineItems,
            shippingAmount: $shippingTotal,
            destination: $destination,
        ));

        $discountTotal = CurrencyPrecision::roundMajor('0', $currencyCode);
        $pricesIncludeTax = (bool) $settings->prices_include_tax;
        $grandTotal = $this->grandTotal(
            $taxResult->itemsSubtotal,
            $shippingTotal,
            $taxResult->itemsTax,
            $taxResult->shippingTax,
            $discountTotal,
            $pricesIncludeTax,
            $currencyCode,
        );

        $itemTotals = [];

        foreach ($taxResult->itemAllocations as $allocation) {
            $lineKey = $allocation->lineKey;
            $isTaxable = $taxableByLineKey[$lineKey] ?? false;
            $itemTotals[$lineKey] = new CheckoutItemTotals(
                lineKey: $lineKey,
                subtotal: $allocation->lineSubtotal,
                discountAmount: $discountTotal,
                taxAmount: $allocation->taxAmount,
                total: $pricesIncludeTax
                    ? $allocation->lineSubtotal
                    : CurrencyPrecision::roundMajor(
                        bcadd($allocation->lineSubtotal, $allocation->taxAmount, 6),
                        $currencyCode,
                    ),
                isTaxable: $isTaxable,
            );
        }

        return new CheckoutTotalsResult(
            storeId: (int) $store->id,
            subtotal: $taxResult->itemsSubtotal,
            discountTotal: $discountTotal,
            shippingTotal: $shippingTotal,
            itemsTax: $taxResult->itemsTax,
            shippingTax: $taxResult->shippingTax,
            taxTotal: $taxResult->totalTax,
            grandTotal: $grandTotal,
            pricesIncludeTax: $pricesIncludeTax,
            itemTotals: $itemTotals,
            taxLines: $taxResult->taxLines,
            taxSnapshot: $this->buildTaxSnapshot($settings, $destination, $taxResult, $calculatedAt),
            calculatedAt: $calculatedAt,
        );
    }

    public function replaceTaxLines(Checkout $checkout, CheckoutTotalsResult $result): void
    {
        if ((int) $checkout->store_id !== $result->storeId) {
            throw new InvalidArgumentException('Checkout totals do not belong to this checkout store.');
        }

        CheckoutTaxLine::query()
            ->where('store_id', $checkout->store_id)
            ->where('checkout_id', $checkout->id)
            ->delete();

        foreach ($result->taxLines as $line) {
            CheckoutTaxLine::query()->create([
                'store_id' => $checkout->store_id,
                'checkout_id' => $checkout->id,
                'tax_rate_id' => $line->taxRateId,
                'jurisdiction_country_code' => $line->jurisdictionCountryCode,
                'jurisdiction_region_code' => $line->jurisdictionRegionCode,
                'rate_percent' => $line->ratePercent,
                'taxable_amount' => $line->taxableAmount,
                'tax_amount' => $line->taxAmount,
                'applies_to' => $line->appliesTo,
                'settings_version' => $line->settingsVersion,
                'calculated_at' => $result->calculatedAt,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $shippingAddress
     */
    public function normalizeDestinationFromAddress(array $shippingAddress): TaxAddressInput
    {
        $countryCode = trim((string) ($shippingAddress['country_code'] ?? ''));

        if (preg_match('/^[A-Za-z]{2}$/', $countryCode) === 1) {
            $countryCode = TaxRate::normalizeCountryCode($countryCode);
        } else {
            $country = trim((string) ($shippingAddress['country'] ?? ''));

            if (preg_match('/^[A-Za-z]{2}$/', $country) === 1) {
                $countryCode = TaxRate::normalizeCountryCode($country);
            } else {
                $countryCode = '';
            }
        }

        $region = trim((string) ($shippingAddress['province_code'] ?? ''));

        if ($region === '') {
            $region = trim((string) ($shippingAddress['state'] ?? ''));
        }

        return new TaxAddressInput($countryCode, $region);
    }

    private function authoritativeUnitPrice(ProductVariant $variant): string
    {
        return DecimalString::normalizeNonNegative(
            (string) $variant->price,
            'Unit price must be a non-negative decimal amount.',
        );
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
            $grandTotal = bcadd(
                bcsub(bcadd($subtotal, $shippingTotal, 6), $discountTotal, 6),
                $shippingTax,
                6,
            );
        } else {
            $grandTotal = bcsub(
                bcadd(
                    bcadd($subtotal, $shippingTotal, 6),
                    bcadd($itemsTax, $shippingTax, 6),
                    6,
                ),
                $discountTotal,
                6,
            );
        }

        $rounded = CurrencyPrecision::roundMajor($grandTotal, $currencyCode);
        $zero = CurrencyPrecision::roundMajor('0', $currencyCode);

        if (bccomp($rounded, $zero, 6) < 0) {
            return $zero;
        }

        return $rounded;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTaxSnapshot(
        TaxSetting $settings,
        TaxAddressInput $destination,
        TaxCalculationResult $taxResult,
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
            'matched_rate' => $this->matchedRateSnapshot($taxResult->matchedRate),
            'tax_calculation_skipped' => $taxResult->taxCalculationSkipped,
            'skip_reason' => $taxResult->skipReason,
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
