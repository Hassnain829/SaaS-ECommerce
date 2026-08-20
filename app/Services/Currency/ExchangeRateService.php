<?php

namespace App\Services\Currency;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ExchangeRateService
{
    /**
     * Return how many units of $toCurrency equal 1 unit of $fromCurrency.
     */
    public function rate(string $fromCurrency, string $toCurrency): string
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === '' || $to === '' || strlen($from) !== 3 || strlen($to) !== 3) {
            throw new RuntimeException('Invalid currency code for exchange rate lookup.');
        }

        if ($from === $to) {
            return '1';
        }

        $cacheKey = "fx.rate.{$from}.{$to}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($from, $to): string {
            $response = Http::timeout(8)
                ->acceptJson()
                ->get("https://open.er-api.com/v6/latest/{$from}");

            if (! $response->successful()) {
                throw new RuntimeException('Unable to fetch live exchange rates. Try again in a moment.');
            }

            $payload = $response->json();
            if (($payload['result'] ?? null) !== 'success') {
                throw new RuntimeException('Exchange rate provider returned an unsuccessful response.');
            }

            $rate = data_get($payload, "rates.{$to}");
            if ($rate === null || ! is_numeric($rate) || (float) $rate <= 0) {
                throw new RuntimeException("No exchange rate available for {$from} → {$to}.");
            }

            return (string) $rate;
        });
    }
}
