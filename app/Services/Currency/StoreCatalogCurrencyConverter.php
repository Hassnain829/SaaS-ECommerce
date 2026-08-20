<?php

namespace App\Services\Currency;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Store;
use Illuminate\Support\Facades\DB;
use Money\Converter;
use Money\Currencies\ISOCurrencies;
use Money\Currency;
use Money\Exchange\FixedExchange;
use Money\Formatter\DecimalMoneyFormatter;
use Money\Parser\DecimalMoneyParser;
use RuntimeException;

class StoreCatalogCurrencyConverter
{
    public function __construct(
        private readonly ExchangeRateService $exchangeRates,
    ) {}

    /**
     * Convert store catalog prices from one currency to another.
     * Historical orders are never rewritten.
     *
     * @return array{
     *     from: string,
     *     to: string,
     *     rate: string,
     *     products_updated: int,
     *     variants_updated: int,
     *     provider: string
     * }
     */
    public function convertStoreCatalog(Store $store, string $fromCurrency, string $toCurrency): array
    {
        $from = strtoupper(trim($fromCurrency));
        $to = strtoupper(trim($toCurrency));

        if ($from === $to) {
            return [
                'from' => $from,
                'to' => $to,
                'rate' => '1',
                'products_updated' => 0,
                'variants_updated' => 0,
                'provider' => 'none',
            ];
        }

        $rate = $this->exchangeRates->rate($from, $to);
        $currencies = new ISOCurrencies;
        $parser = new DecimalMoneyParser($currencies);
        $formatter = new DecimalMoneyFormatter($currencies);
        $converter = new Converter($currencies, new FixedExchange([
            $from => [
                $to => $rate,
            ],
        ]));
        $fromMoneyCurrency = new Currency($from);
        $toMoneyCurrency = new Currency($to);

        $productsUpdated = 0;
        $variantsUpdated = 0;

        DB::transaction(function () use (
            $store,
            $parser,
            $formatter,
            $converter,
            $fromMoneyCurrency,
            $toMoneyCurrency,
            &$productsUpdated,
            &$variantsUpdated
        ): void {
            Product::query()
                ->where('store_id', $store->id)
                ->orderBy('id')
                ->chunkById(100, function ($products) use (
                    $parser,
                    $formatter,
                    $converter,
                    $fromMoneyCurrency,
                    $toMoneyCurrency,
                    &$productsUpdated
                ): void {
                    foreach ($products as $product) {
                        $convertedBase = $this->convertDecimalAmount(
                            $product->base_price,
                            $parser,
                            $formatter,
                            $converter,
                            $fromMoneyCurrency,
                            $toMoneyCurrency
                        );

                        if ($convertedBase === null) {
                            continue;
                        }

                        $product->forceFill([
                            'base_price' => $convertedBase,
                        ])->save();
                        $productsUpdated++;
                    }
                });

            ProductVariant::query()
                ->where('store_id', $store->id)
                ->orderBy('id')
                ->chunkById(100, function ($variants) use (
                    $parser,
                    $formatter,
                    $converter,
                    $fromMoneyCurrency,
                    $toMoneyCurrency,
                    &$variantsUpdated
                ): void {
                    foreach ($variants as $variant) {
                        $updates = [];

                        $convertedPrice = $this->convertDecimalAmount(
                            $variant->price,
                            $parser,
                            $formatter,
                            $converter,
                            $fromMoneyCurrency,
                            $toMoneyCurrency
                        );
                        if ($convertedPrice !== null) {
                            $updates['price'] = $convertedPrice;
                        }

                        if ($variant->compare_at_price !== null && $variant->compare_at_price !== '') {
                            $convertedCompare = $this->convertDecimalAmount(
                                $variant->compare_at_price,
                                $parser,
                                $formatter,
                                $converter,
                                $fromMoneyCurrency,
                                $toMoneyCurrency
                            );
                            if ($convertedCompare !== null) {
                                $updates['compare_at_price'] = $convertedCompare;
                            }
                        }

                        if ($updates === []) {
                            continue;
                        }

                        $variant->forceFill($updates)->save();
                        $variantsUpdated++;
                    }
                });
        });

        return [
            'from' => $from,
            'to' => $to,
            'rate' => $rate,
            'products_updated' => $productsUpdated,
            'variants_updated' => $variantsUpdated,
            'provider' => 'exchangerate-api.com',
        ];
    }

    private function convertDecimalAmount(
        mixed $amount,
        DecimalMoneyParser $parser,
        DecimalMoneyFormatter $formatter,
        Converter $converter,
        Currency $from,
        Currency $to,
    ): ?string {
        if ($amount === null || $amount === '') {
            return null;
        }

        $normalized = number_format((float) $amount, 2, '.', '');

        try {
            $money = $parser->parse($normalized, $from);
            $converted = $converter->convert($money, $to);

            return $formatter->format($converted);
        } catch (\Throwable $exception) {
            throw new RuntimeException('Failed to convert a catalog price: '.$exception->getMessage(), 0, $exception);
        }
    }
}
