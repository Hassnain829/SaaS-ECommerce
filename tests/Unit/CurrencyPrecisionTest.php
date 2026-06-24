<?php

namespace Tests\Unit;

use App\Support\Money\CurrencyPrecision;
use InvalidArgumentException;
use Tests\TestCase;

class CurrencyPrecisionTest extends TestCase
{
    public function test_usd_exponent_and_scale_are_two(): void
    {
        $this->assertSame(2, CurrencyPrecision::exponent('USD'));
        $this->assertSame(2, CurrencyPrecision::scale('USD'));
    }

    public function test_jpy_exponent_and_scale_are_zero(): void
    {
        $this->assertSame(0, CurrencyPrecision::exponent('JPY'));
        $this->assertSame(0, CurrencyPrecision::scale('JPY'));
    }

    public function test_currency_codes_are_case_insensitive(): void
    {
        $this->assertTrue(CurrencyPrecision::isZeroDecimal('jpy'));
        $this->assertTrue(CurrencyPrecision::isZeroDecimal('JPY'));
        $this->assertSame(2, CurrencyPrecision::exponent('usd'));
        $this->assertSame(2, CurrencyPrecision::exponent('Usd'));
    }

    public function test_canonical_zero_decimal_list_contains_expected_currencies_without_duplicates(): void
    {
        $currencies = CurrencyPrecision::zeroDecimalCurrencies();

        $this->assertContains('jpy', $currencies);
        $this->assertContains('krw', $currencies);
        $this->assertSame(count($currencies), count(array_unique($currencies)));
        $this->assertCount(16, $currencies);
    }

    public function test_unknown_normal_currency_defaults_to_exponent_two(): void
    {
        $this->assertSame(2, CurrencyPrecision::exponent('EUR'));
        $this->assertFalse(CurrencyPrecision::isZeroDecimal('CAD'));
    }

    public function test_empty_currency_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Currency code is required.');

        CurrencyPrecision::exponent('   ');
    }

    public function test_usd_positive_half_up_rounding(): void
    {
        $this->assertSame('1.00', CurrencyPrecision::roundMajor('1.004', 'USD'));
        $this->assertSame('1.01', CurrencyPrecision::roundMajor('1.005', 'USD'));
        $this->assertSame('1.01', CurrencyPrecision::roundMajor('1.006', 'USD'));
    }

    public function test_usd_negative_half_up_rounding(): void
    {
        $this->assertSame('-1.00', CurrencyPrecision::roundMajor('-1.004', 'USD'));
        $this->assertSame('-1.01', CurrencyPrecision::roundMajor('-1.005', 'USD'));
    }

    public function test_jpy_half_up_rounding(): void
    {
        $this->assertSame('100', CurrencyPrecision::roundMajor('100.4', 'JPY'));
        $this->assertSame('101', CurrencyPrecision::roundMajor('100.5', 'JPY'));
    }

    public function test_usd_major_to_minor_conversion(): void
    {
        $this->assertSame(1234, CurrencyPrecision::toMinorUnits('12.34', 'USD'));
        $this->assertSame(1235, CurrencyPrecision::toMinorUnits('12.345', 'USD'));
    }

    public function test_jpy_major_to_minor_conversion(): void
    {
        $this->assertSame(1000, CurrencyPrecision::toMinorUnits('1000', 'JPY'));
        $this->assertSame(1001, CurrencyPrecision::toMinorUnits('1000.5', 'JPY'));
    }

    public function test_usd_minor_to_major_conversion(): void
    {
        $this->assertSame('12.34', CurrencyPrecision::fromMinorUnits(1234, 'USD'));
    }

    public function test_jpy_minor_to_major_conversion(): void
    {
        $this->assertSame('1000', CurrencyPrecision::fromMinorUnits(1000, 'JPY'));
    }

    public function test_usd_round_trip(): void
    {
        $major = '19.99';
        $minor = CurrencyPrecision::toMinorUnits($major, 'USD');
        $this->assertSame($major, CurrencyPrecision::fromMinorUnits($minor, 'USD'));
    }

    public function test_jpy_round_trip(): void
    {
        $major = '2500';
        $minor = CurrencyPrecision::toMinorUnits($major, 'JPY');
        $this->assertSame($major, CurrencyPrecision::fromMinorUnits($minor, 'JPY'));
    }

    public function test_invalid_numeric_input_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a valid decimal number.');

        CurrencyPrecision::roundMajor('not-a-number', 'USD');
    }

    public function test_scientific_notation_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be a valid decimal number.');

        CurrencyPrecision::roundMajor('1e3', 'USD');
    }

    public function test_malformed_decimal_input_is_rejected(): void
    {
        $cases = ['1E-2', '1,000.00', '10abc', 'NaN', 'INF'];

        foreach ($cases as $case) {
            try {
                CurrencyPrecision::roundMajor($case, 'USD');
                $this->fail("Expected InvalidArgumentException for [{$case}]");
            } catch (InvalidArgumentException $exception) {
                $this->assertSame('Amount must be a valid decimal number.', $exception->getMessage());
            }
        }
    }

    public function test_plain_decimal_formats_are_accepted(): void
    {
        $this->assertSame('0.50', CurrencyPrecision::roundMajor('.50', 'USD'));
        $this->assertSame('10.00', CurrencyPrecision::roundMajor('+10.00', 'USD'));
        $this->assertSame('-1.00', CurrencyPrecision::roundMajor('-1.004', 'USD'));
    }

    public function test_overflow_is_rejected_instead_of_silently_truncating(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount overflows minor-unit conversion.');

        CurrencyPrecision::toMinorUnits(bcadd(bcdiv((string) PHP_INT_MAX, '100', 0), '1000', 0), 'USD');
    }
}
