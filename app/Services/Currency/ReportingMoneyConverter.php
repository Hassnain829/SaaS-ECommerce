<?php

namespace App\Services\Currency;

use Money\Converter;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exchange\FixedExchange;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Parser\DecimalMoneyParser;
use Throwable;

/**
 * Display-time conversion for reporting aggregates.
 * Does not rewrite stored order rows.
 */
class ReportingMoneyConverter
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * Convert an amount into the target currency for merchant reporting display.
     */
    public function convert(float|string|null $amount, string $fromCurrency, string $toCurrency): float
    {
        $value = is_numeric($amount) ? (float) $amount : 0.0;
        $from = strtoupper(trim($fromCurrency)) ?: 'USD';
        $to = strtoupper(trim($toCurrency)) ?: 'USD';

        if ($from === $to || $value == 0.0) {
            return $value;
        }

        try {
            $rate = $this->exchangeRates->rate($from, $to);
            $currencies = new ISOCurrencies;
            $parser = new DecimalMoneyParser($currencies);
            $formatter = new DecimalMoneyFormatter($currencies);
            $converter = new Converter($currencies, new FixedExchange([
                $from => [$to => $rate],
            ]));

            $money = $parser->parse(number_format(abs($value), 2, '.', ''), new Currency($from));
            $converted = $converter->convert($money, new Currency($to));
            $formatted = (float) $formatter->format($converted);

            return $value < 0 ? -1 * $formatted : $formatted;
        } catch (Throwable) {
            // Keep the original amount if live FX is unavailable; callers still choose display currency carefully.
            return $value;
        }
    }
}
