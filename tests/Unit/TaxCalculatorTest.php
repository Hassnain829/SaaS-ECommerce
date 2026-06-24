<?php

namespace Tests\Unit;

use App\Data\Tax\MatchedTaxRate;
use App\Data\Tax\TaxAddressInput;
use App\Data\Tax\TaxCalculationRequest;
use App\Data\Tax\TaxCalculationResult;
use App\Data\Tax\TaxLineItemInput;
use App\Data\Tax\TaxLineOutput;
use App\Models\CheckoutTaxLine;
use App\Models\OrderTaxLine;
use App\Models\Role;
use App\Models\SecurityLog;
use App\Models\Store;
use App\Models\TaxRate;
use App\Models\TaxSetting;
use App\Models\User;
use App\Services\Tax\TaxCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

class TaxCalculatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_rejects_settings_belonging_to_another_store(): void
    {
        [$storeA] = $this->taxFixture();
        [, $settingsB] = $this->taxFixture(storeName: 'Other Store', ownerEmail: 'other@example.com');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Tax settings do not belong to the provided store.');

        $this->request($storeA, $settingsB, items: [
            new TaxLineItemInput('line-1', 1, '10.00'),
        ]);
    }

    public function test_rejects_empty_currency(): void
    {
        [$store, $settings] = $this->taxFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code is required.');

        new TaxCalculationRequest(
            $store,
            $settings,
            '',
            [new TaxLineItemInput('line-1', 1, '10.00')],
            '0.00',
            new TaxAddressInput('US', 'CA'),
        );
    }

    public function test_rejects_negative_shipping(): void
    {
        [$store, $settings] = $this->taxFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Shipping amount must be a non-negative decimal amount.');

        $this->request($store, $settings, shipping: '-1.00');
    }

    public function test_rejects_scientific_notation_shipping_amount(): void
    {
        [$store, $settings] = $this->taxFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a valid decimal number.');

        $this->request($store, $settings, shipping: '1E-2');
    }

    public function test_rejects_zero_quantity(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Quantity must be greater than zero.');

        new TaxLineItemInput('line-1', 0, '10.00');
    }

    public function test_rejects_negative_unit_price(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unit price must be a non-negative decimal amount.');

        new TaxLineItemInput('line-1', 1, '-1.00');
    }

    public function test_rejects_duplicate_line_keys(): void
    {
        [$store, $settings] = $this->taxFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate line keys are not allowed.');

        $this->request($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '10.00'),
            new TaxLineItemInput('line-1', 2, '5.00'),
        ]);
    }

    public function test_never_uses_another_stores_tax_rate(): void
    {
        [$storeA, $settingsA] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'Store A CA',
            'rate_percent' => '10.0000',
        ]]);
        [$storeB, $settingsB] = $this->taxFixture(
            storeName: 'Store B',
            ownerEmail: 'store-b@example.com',
            rates: [[
                'country_code' => 'US',
                'region_code' => 'CA',
                'name' => 'Store B CA',
                'rate_percent' => '20.0000',
            ]],
        );

        $result = $this->calculate($storeB, $settingsB, destination: new TaxAddressInput('US', 'CA'));

        $this->assertNotNull($result->matchedRate);
        $this->assertSame('20.0000', $result->matchedRate->ratePercent);
        $this->assertSame(1, TaxRate::query()->forStore($storeA->id)->count());
        $this->assertSame(1, TaxRate::query()->forStore($storeB->id)->count());
    }

    public function test_tax_disabled_returns_zero_tax_and_all_allocations(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['enabled' => false], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '20.00'),
        ], destination: new TaxAddressInput('US', 'CA'));

        $this->assertFalse($result->taxCalculationSkipped);
        $this->assertNull($result->skipReason);
        $this->assertNull($result->matchedRate);
        $this->assertSame('0.00', $result->itemsTax);
        $this->assertSame('0.00', $result->totalTax);
        $this->assertCount(1, $result->itemAllocations);
        $this->assertSame('20.00', $result->itemAllocations[0]->lineSubtotal);
        $this->assertSame('0.00', $result->itemAllocations[0]->taxAmount);
    }

    public function test_enabled_tax_with_missing_country_marks_calculation_skipped(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => '',
            'name' => 'US Country',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, destination: new TaxAddressInput('', ''));

        $this->assertTrue($result->taxCalculationSkipped);
        $this->assertSame(TaxCalculationResult::SKIP_REASON_MISSING_COUNTRY, $result->skipReason);
        $this->assertNull($result->matchedRate);
        $this->assertSame('0.00', $result->totalTax);
        $this->assertSame([], $result->taxLines);
    }

    public function test_missing_region_uses_country_wide_rate(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [
            [
                'country_code' => 'US',
                'region_code' => '',
                'name' => 'US Country',
                'rate_percent' => '8.2500',
            ],
            [
                'country_code' => 'US',
                'region_code' => 'NY',
                'name' => 'NY Regional',
                'rate_percent' => '12.0000',
            ],
        ]);

        $result = $this->calculate($store, $settings, destination: new TaxAddressInput('US', ''));

        $this->assertNotNull($result->matchedRate);
        $this->assertSame('', $result->matchedRate->regionCode);
        $this->assertSame('8.2500', $result->matchedRate->ratePercent);
    }

    public function test_no_matching_rate_returns_zero_without_skipped_state(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Only',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, destination: new TaxAddressInput('GB', ''));

        $this->assertFalse($result->taxCalculationSkipped);
        $this->assertNull($result->skipReason);
        $this->assertNull($result->matchedRate);
        $this->assertSame('0.00', $result->totalTax);
        $this->assertSame([], $result->taxLines);
    }

    public function test_inactive_rate_does_not_apply(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'Inactive CA',
            'rate_percent' => '10.0000',
            'is_active' => false,
        ]]);

        $result = $this->calculate($store, $settings, destination: new TaxAddressInput('US', 'CA'));

        $this->assertNull($result->matchedRate);
        $this->assertSame('0.00', $result->totalTax);
    }

    public function test_exact_regional_rate_wins_over_country_wide(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [
            [
                'country_code' => 'US',
                'region_code' => '',
                'name' => 'US Country',
                'rate_percent' => '5.0000',
            ],
            [
                'country_code' => 'US',
                'region_code' => 'CA',
                'name' => 'CA Regional',
                'rate_percent' => '9.5000',
            ],
        ]);

        $result = $this->calculate($store, $settings, destination: new TaxAddressInput('US', 'CA'));

        $this->assertSame('CA', $result->matchedRate?->regionCode);
        $this->assertSame('9.5000', $result->matchedRate?->ratePercent);
    }

    public function test_inactive_regional_rate_falls_back_to_active_country_wide(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [
            [
                'country_code' => 'US',
                'region_code' => '',
                'name' => 'US Country',
                'rate_percent' => '7.5000',
            ],
            [
                'country_code' => 'US',
                'region_code' => 'CA',
                'name' => 'Inactive CA',
                'rate_percent' => '12.0000',
                'is_active' => false,
            ],
        ]);

        $result = $this->calculate($store, $settings, destination: new TaxAddressInput('US', 'CA'));

        $this->assertSame('', $result->matchedRate?->regionCode);
        $this->assertSame('7.5000', $result->matchedRate?->ratePercent);
    }

    public function test_country_and_region_normalization_is_case_insensitive(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Regional',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, destination: new TaxAddressInput('us', ' ca '));

        $this->assertNotNull($result->matchedRate);
        $this->assertSame('1.00', $result->itemsTax);
    }

    public function test_exclusive_one_taxable_item(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['prices_include_tax' => false], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '20.00', true),
        ]);

        $this->assertSame('20.00', $result->itemsSubtotal);
        $this->assertSame('20.00', $result->taxableItemsSubtotal);
        $this->assertSame('2.00', $result->itemsTax);
        $this->assertSame('2.00', $result->totalTax);
        $this->assertSame('20.00', $result->itemAllocations[0]->lineSubtotal);
        $this->assertSame('2.00', $result->itemAllocations[0]->taxAmount);
    }

    public function test_exclusive_one_non_taxable_item(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '20.00', false),
        ]);

        $this->assertSame('20.00', $result->itemsSubtotal);
        $this->assertSame('0.00', $result->taxableItemsSubtotal);
        $this->assertSame('0.00', $result->itemsTax);
        $this->assertSame('0.00', $result->itemAllocations[0]->taxAmount);
    }

    public function test_exclusive_mixed_taxable_and_non_taxable_cart(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('taxable', 1, '20.00', true),
            new TaxLineItemInput('exempt', 1, '15.00', false),
        ]);

        $this->assertSame('35.00', $result->itemsSubtotal);
        $this->assertSame('20.00', $result->taxableItemsSubtotal);
        $this->assertSame('2.00', $result->itemsTax);
    }

    public function test_exclusive_multiple_quantities(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 3, '10.00', true),
        ]);

        $this->assertSame('30.00', $result->itemsSubtotal);
        $this->assertSame('3.00', $result->itemsTax);
    }

    public function test_exclusive_line_level_half_up_rounding(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '10.05', true),
        ]);

        $this->assertSame('10.05', $result->itemAllocations[0]->lineSubtotal);
        $this->assertSame('1.01', $result->itemAllocations[0]->taxAmount);
        $this->assertSame('1.01', $result->itemsTax);
    }

    public function test_exclusive_zero_percent_active_rate_snapshot(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Zero',
            'rate_percent' => '0.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '20.00', true),
        ]);

        $this->assertNotNull($result->matchedRate);
        $this->assertSame('0.0000', $result->matchedRate->ratePercent);
        $this->assertSame('0.00', $result->itemsTax);
        $this->assertCount(1, $result->taxLines);
        $this->assertSame(TaxLineOutput::APPLIES_TO_ITEMS, $result->taxLines[0]->appliesTo);
        $this->assertSame('20.00', $result->taxLines[0]->taxableAmount);
        $this->assertSame('0.00', $result->taxLines[0]->taxAmount);
    }

    public function test_inclusive_canonical_example(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['prices_include_tax' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '22.00', true),
        ]);

        $allocation = $result->itemAllocations[0];
        $this->assertSame('22.00', $allocation->lineSubtotal);
        $this->assertSame('2.00', $allocation->taxAmount);
        $this->assertSame('20.00', $allocation->taxableAmount);
        $this->assertSame('22.00', $result->itemsSubtotal);
        $this->assertSame('2.00', $result->itemsTax);
    }

    public function test_inclusive_non_taxable_item_remains_gross_with_zero_tax(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['prices_include_tax' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '22.00', false),
        ]);

        $this->assertSame('22.00', $result->itemAllocations[0]->lineSubtotal);
        $this->assertSame('0.00', $result->itemAllocations[0]->taxAmount);
        $this->assertSame('0.00', $result->itemAllocations[0]->taxableAmount);
    }

    public function test_inclusive_mixed_cart(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['prices_include_tax' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('taxable', 1, '22.00', true),
            new TaxLineItemInput('exempt', 1, '11.00', false),
        ]);

        $this->assertSame('33.00', $result->itemsSubtotal);
        $this->assertSame('2.00', $result->itemsTax);
        $this->assertSame('11.00', $result->itemAllocations[1]->lineSubtotal);
    }

    public function test_inclusive_multiple_quantities_preserve_gross_subtotal(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['prices_include_tax' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 2, '11.00', true),
        ]);

        $this->assertSame('22.00', $result->itemAllocations[0]->lineSubtotal);
        $this->assertSame('2.00', $result->itemAllocations[0]->taxAmount);
        $this->assertSame('22.00', $result->itemsSubtotal);
    }

    public function test_inclusive_extracted_item_tax_is_not_added_to_items_subtotal(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['prices_include_tax' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '22.00', true),
        ]);

        $this->assertSame('22.00', $result->itemsSubtotal);
        $this->assertSame('2.00', $result->itemsTax);
        $this->assertNotSame(
            bcadd($result->itemsSubtotal, $result->itemsTax, 2),
            $result->itemsSubtotal,
        );
    }

    public function test_taxable_shipping_creates_separate_shipping_line(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['shipping_taxable' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '20.00', true),
        ], shipping: '5.00');

        $this->assertSame('0.50', $result->shippingTax);
        $this->assertCount(2, $result->taxLines);
        $this->assertSame(TaxLineOutput::APPLIES_TO_SHIPPING, $result->taxLines[1]->appliesTo);
        $this->assertSame('5.00', $result->taxLines[1]->taxableAmount);
        $this->assertSame('0.50', $result->taxLines[1]->taxAmount);
    }

    public function test_non_taxable_shipping_produces_no_shipping_line(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['shipping_taxable' => false], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, shipping: '5.00');

        $this->assertSame('0.00', $result->shippingTax);
        $this->assertCount(1, $result->taxLines);
        $this->assertSame(TaxLineOutput::APPLIES_TO_ITEMS, $result->taxLines[0]->appliesTo);
    }

    public function test_zero_shipping_produces_no_shipping_line(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['shipping_taxable' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, shipping: '0.00');

        $this->assertSame('0.00', $result->shippingTax);
        $this->assertTrue(collect($result->taxLines)->every(
            fn (TaxLineOutput $line): bool => $line->appliesTo !== TaxLineOutput::APPLIES_TO_SHIPPING,
        ));
    }

    public function test_zero_percent_rate_with_taxable_usd_shipping_produces_shipping_snapshot_line(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['shipping_taxable' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Zero',
            'rate_percent' => '0.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '10.00', false),
        ], shipping: '5.00');

        $this->assertNotNull($result->matchedRate);
        $this->assertSame('0.0000', $result->matchedRate->ratePercent);
        $this->assertSame('0.00', $result->shippingTax);

        $shippingLines = array_values(array_filter(
            $result->taxLines,
            fn (TaxLineOutput $line): bool => $line->appliesTo === TaxLineOutput::APPLIES_TO_SHIPPING,
        ));

        $this->assertCount(1, $shippingLines);
        $this->assertSame('5.00', $shippingLines[0]->taxableAmount);
        $this->assertSame('0.00', $shippingLines[0]->taxAmount);
        $this->assertSame($settings->settings_version, $shippingLines[0]->settingsVersion);
    }

    public function test_zero_percent_rate_with_taxable_jpy_shipping_produces_shipping_snapshot_line(): void
    {
        [$store, $settings] = $this->taxFixture(
            currency: 'JPY',
            settings: ['shipping_taxable' => true],
            rates: [[
                'country_code' => 'JP',
                'region_code' => '',
                'name' => 'JP Zero',
                'rate_percent' => '0.0000',
            ]],
        );

        $result = $this->calculate(
            $store,
            $settings,
            currency: 'JPY',
            items: [new TaxLineItemInput('line-1', 1, '1000', false)],
            shipping: '500',
            destination: new TaxAddressInput('JP', ''),
        );

        $this->assertSame('0', $result->shippingTax);

        $shippingLines = array_values(array_filter(
            $result->taxLines,
            fn (TaxLineOutput $line): bool => $line->appliesTo === TaxLineOutput::APPLIES_TO_SHIPPING,
        ));

        $this->assertCount(1, $shippingLines);
        $this->assertSame('500', $shippingLines[0]->taxableAmount);
        $this->assertSame('0', $shippingLines[0]->taxAmount);
    }

    public function test_line_item_input_normalizes_whitespace_in_line_key_and_unit_price(): void
    {
        $item = new TaxLineItemInput('  line-1  ', 1, '  10.50  ');

        $this->assertSame('line-1', $item->lineKey);
        $this->assertSame('10.50', $item->unitPrice);
    }

    public function test_duplicate_line_keys_reject_whitespace_normalized_keys(): void
    {
        [$store, $settings] = $this->taxFixture();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate line keys are not allowed.');

        $this->request($store, $settings, items: [
            new TaxLineItemInput(' line-1 ', 1, '10.00'),
            new TaxLineItemInput('line-1', 1, '5.00'),
        ]);
    }

    public function test_scientific_notation_unit_price_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a valid decimal number.');

        new TaxLineItemInput('line-1', 1, '1e3');
    }

    public function test_calculator_uses_canonical_trimmed_unit_price(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 2, ' 10.50 ', true),
        ]);

        $this->assertSame('21.00', $result->itemAllocations[0]->lineSubtotal);
        $this->assertSame('2.10', $result->itemAllocations[0]->taxAmount);
    }

    public function test_inclusive_product_prices_still_treat_shipping_as_exclusive(): void
    {
        [$store, $settings] = $this->taxFixture(settings: [
            'prices_include_tax' => true,
            'shipping_taxable' => true,
        ], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('line-1', 1, '22.00', true),
        ], shipping: '10.00');

        $this->assertSame('2.00', $result->itemsTax);
        $this->assertSame('1.00', $result->shippingTax);
        $this->assertSame('3.00', $result->totalTax);
    }

    public function test_usd_outputs_two_decimal_strings(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, currency: 'USD', items: [
            new TaxLineItemInput('line-1', 1, '10.00', true),
        ]);

        $this->assertSame('10.00', $result->itemsSubtotal);
        $this->assertSame('1.00', $result->itemsTax);
        $this->assertSame('0.00', $result->shippingTax);
    }

    public function test_jpy_outputs_zero_decimal_strings(): void
    {
        [$store, $settings] = $this->taxFixture(
            currency: 'JPY',
            rates: [[
                'country_code' => 'JP',
                'region_code' => '',
                'name' => 'JP Country',
                'rate_percent' => '10.0000',
            ]],
        );

        $result = $this->calculate(
            $store,
            $settings,
            currency: 'jpy',
            items: [new TaxLineItemInput('line-1', 1, '1000', true)],
            destination: new TaxAddressInput('JP', ''),
        );

        $this->assertSame('1000', $result->itemsSubtotal);
        $this->assertSame('100', $result->itemsTax);
        $this->assertSame('0', $result->shippingTax);
    }

    public function test_jpy_exclusive_tax_fixture(): void
    {
        [$store, $settings] = $this->taxFixture(
            currency: 'JPY',
            rates: [[
                'country_code' => 'JP',
                'region_code' => '',
                'name' => 'JP Country',
                'rate_percent' => '10.0000',
            ]],
        );

        $result = $this->calculate(
            $store,
            $settings,
            currency: 'JPY',
            items: [new TaxLineItemInput('line-1', 2, '500', true)],
            destination: new TaxAddressInput('JP', ''),
        );

        $this->assertSame('1000', $result->itemsSubtotal);
        $this->assertSame('100', $result->itemsTax);
    }

    public function test_currency_case_normalization_in_calculator_outputs(): void
    {
        [$store, $settings] = $this->taxFixture(currency: 'usd', rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, currency: 'Usd');

        $this->assertSame('1.00', $result->itemsTax);
    }

    public function test_allocation_returned_for_every_input_item(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('a', 1, '10.00', true),
            new TaxLineItemInput('b', 1, '5.00', false),
            new TaxLineItemInput('c', 2, '3.00', true),
        ]);

        $this->assertCount(3, $result->itemAllocations);
        $this->assertSame(['a', 'b', 'c'], array_map(
            fn ($allocation) => $allocation->lineKey,
            $result->itemAllocations,
        ));
    }

    public function test_one_aggregated_item_tax_line(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, items: [
            new TaxLineItemInput('a', 1, '10.00', true),
            new TaxLineItemInput('b', 1, '5.00', true),
        ]);

        $itemLines = array_values(array_filter(
            $result->taxLines,
            fn (TaxLineOutput $line): bool => $line->appliesTo === TaxLineOutput::APPLIES_TO_ITEMS,
        ));

        $this->assertCount(1, $itemLines);
        $this->assertSame('1.50', $itemLines[0]->taxAmount);
    }

    public function test_at_most_one_shipping_line(): void
    {
        [$store, $settings] = $this->taxFixture(settings: ['shipping_taxable' => true], rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings, shipping: '8.00');

        $shippingLines = array_values(array_filter(
            $result->taxLines,
            fn (TaxLineOutput $line): bool => $line->appliesTo === TaxLineOutput::APPLIES_TO_SHIPPING,
        ));

        $this->assertCount(1, $shippingLines);
    }

    public function test_settings_version_preserved(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $settings->update(['settings_version' => 9]);
        $settings = $settings->fresh();

        $result = $this->calculate($store, $settings);

        $this->assertSame(9, $result->settingsVersion);
        $this->assertSame(9, $result->taxLines[0]->settingsVersion);
    }

    public function test_matched_rate_snapshot_is_immutable_data_not_eloquent_model(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $result = $this->calculate($store, $settings);

        $this->assertInstanceOf(MatchedTaxRate::class, $result->matchedRate);
        $this->assertNotInstanceOf(TaxRate::class, $result->matchedRate);
    }

    public function test_calculator_performs_no_tax_line_database_writes(): void
    {
        [$store, $settings] = $this->taxFixture(rates: [[
            'country_code' => 'US',
            'region_code' => 'CA',
            'name' => 'CA Sales',
            'rate_percent' => '10.0000',
        ]]);

        $settingsVersionBefore = $settings->settings_version;
        $checkoutTaxLinesBefore = CheckoutTaxLine::query()->count();
        $orderTaxLinesBefore = OrderTaxLine::query()->count();
        $securityLogsBefore = SecurityLog::query()->count();

        $this->calculate($store, $settings->fresh());

        $this->assertSame($settingsVersionBefore, $settings->fresh()->settings_version);
        $this->assertSame($checkoutTaxLinesBefore, CheckoutTaxLine::query()->count());
        $this->assertSame($orderTaxLinesBefore, OrderTaxLine::query()->count());
        $this->assertSame($securityLogsBefore, SecurityLog::query()->count());
    }

    /**
     * @param  list<TaxLineItemInput>  $items
     * @param  list<array<string, mixed>>  $rates
     * @return array{0: Store, 1: TaxSetting}
     */
    private function taxFixture(
        array $settings = [],
        array $rates = [],
        string $storeName = 'Tax Calculator Store',
        string $ownerEmail = 'tax-calculator@example.com',
        string $currency = 'USD',
    ): array {
        $owner = User::factory()->create([
            'email' => $ownerEmail,
            'role_id' => Role::firstOrCreate(['name' => 'user'])->id,
        ]);

        $store = Store::create([
            'user_id' => $owner->id,
            'name' => $storeName,
            'slug' => str($storeName)->slug().'-'.fake()->unique()->numberBetween(1000, 9999),
            'logo' => null,
            'address' => 'Test Address',
            'currency' => $currency,
            'timezone' => 'UTC',
            'category' => 'physical',
            'settings' => [],
            'onboarding_completed' => true,
        ]);

        $store->members()->syncWithoutDetaching([
            $owner->id => ['role' => Store::ROLE_OWNER],
        ]);

        $taxSetting = $store->taxSetting;
        $taxSetting->update(array_merge([
            'enabled' => true,
            'prices_include_tax' => false,
            'default_product_taxable' => true,
            'shipping_taxable' => false,
        ], $settings));

        foreach ($rates as $rate) {
            TaxRate::query()->create(array_merge([
                'store_id' => $store->id,
                'country_code' => 'US',
                'region_code' => 'CA',
                'name' => 'Default Rate',
                'rate_percent' => '10.0000',
                'priority' => 100,
                'is_active' => true,
            ], $rate));
        }

        return [$store, $taxSetting->fresh()];
    }

    /**
     * @param  list<TaxLineItemInput>  $items
     */
    private function request(
        Store $store,
        TaxSetting $settings,
        array $items = [],
        string $shipping = '0.00',
        string $currency = 'USD',
        ?TaxAddressInput $destination = null,
    ): TaxCalculationRequest {
        if ($items === []) {
            $items = [new TaxLineItemInput('line-1', 1, '10.00')];
        }

        return new TaxCalculationRequest(
            $store,
            $settings,
            $currency,
            $items,
            $shipping,
            $destination ?? new TaxAddressInput('US', 'CA'),
        );
    }

    /**
     * @param  list<TaxLineItemInput>  $items
     */
    private function calculate(
        Store $store,
        TaxSetting $settings,
        array $items = [],
        string $shipping = '0.00',
        string $currency = 'USD',
        ?TaxAddressInput $destination = null,
    ): TaxCalculationResult {
        return app(TaxCalculator::class)->calculate(
            $this->request($store, $settings, $items, $shipping, $currency, $destination),
        );
    }
}
