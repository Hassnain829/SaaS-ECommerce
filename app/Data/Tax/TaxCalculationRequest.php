<?php

namespace App\Data\Tax;

use App\Models\Store;
use App\Models\TaxSetting;
use App\Support\Money\DecimalString;
use InvalidArgumentException;

final readonly class TaxCalculationRequest
{
    public string $currencyCode;

    public string $shippingAmount;

    /**
     * @param  list<TaxLineItemInput>  $items
     */
    public function __construct(
        public Store $store,
        public TaxSetting $settings,
        string $currencyCode,
        public array $items,
        string $shippingAmount,
        public TaxAddressInput $destination,
    ) {
        if ((int) $settings->store_id !== (int) $store->id) {
            throw new InvalidArgumentException('Tax settings do not belong to the provided store.');
        }

        $currencyCode = trim($currencyCode);

        if ($currencyCode === '') {
            throw new InvalidArgumentException('Currency code is required.');
        }

        $shippingAmount = DecimalString::normalizeNonNegative(
            $shippingAmount,
            'Shipping amount must be a non-negative decimal amount.',
        );

        $seenLineKeys = [];

        foreach ($items as $item) {
            if (! $item instanceof TaxLineItemInput) {
                throw new InvalidArgumentException('Each cart line must be a TaxLineItemInput.');
            }

            if (isset($seenLineKeys[$item->lineKey])) {
                throw new InvalidArgumentException('Duplicate line keys are not allowed.');
            }

            $seenLineKeys[$item->lineKey] = true;
        }

        $this->currencyCode = $currencyCode;
        $this->shippingAmount = $shippingAmount;
    }
}
