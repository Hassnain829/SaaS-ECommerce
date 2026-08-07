<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\CarrierRateQuote;
use App\Models\FedExTradeDocument;
use App\Models\Location;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Validation\ValidationException;

/**
 * Binds a persisted ACCOUNT rate quote (and optional ETD document) to a label purchase.
 */
final class FedExShipQuoteBindingService
{
    /**
     * @param  array<string, mixed>  $packages
     */
    public function assertValidQuoteForPurchase(
        Store $store,
        Order $order,
        CarrierAccount $account,
        Location $origin,
        int $quoteId,
        string $serviceType,
        array $packages,
        string $destinationPostal,
        string $destinationCountry,
        string $currency,
        string $originCountry = 'US',
        ?bool $residential = null,
        ?string $shipDate = null,
    ): CarrierRateQuote {
        $quote = CarrierRateQuote::query()
            ->whereKey($quoteId)
            ->where('store_id', $store->id)
            ->first();

        if (! $quote) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'Select a valid FedEx rate quote for this store.',
            ]);
        }

        if ((int) $quote->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'That rate quote does not belong to this order.',
            ]);
        }

        if ((int) $quote->carrier_account_id !== (int) $account->id) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'That rate quote belongs to a different FedEx account.',
            ]);
        }

        if ($quote->status !== CarrierRateQuote::STATUS_SUCCEEDED) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'That rate quote is not usable. Get fresh FedEx rates.',
            ]);
        }

        $ttl = max(60, (int) config('carriers.fedex.ops_rate_quote_ttl_seconds', 1800));
        if ($quote->created_at === null || $quote->created_at->lt(now()->subSeconds($ttl))) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'That FedEx rate has expired. Get fresh rates before creating a label.',
            ]);
        }

        $selectedRateType = strtoupper((string) data_get($quote->response_summary, 'selected_rate_type', data_get($quote->response_summary, 'rate_type', 'ACCOUNT')));
        if ($selectedRateType !== '' && $selectedRateType !== 'ACCOUNT') {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'Only negotiated account rates can be used to purchase labels.',
            ]);
        }

        if (strtoupper((string) $quote->service_code) !== strtoupper($serviceType)) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'The selected service does not match the rate quote.',
            ]);
        }

        if (strtoupper((string) $quote->currency) !== strtoupper($currency)) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'The rate quote currency does not match this order.',
            ]);
        }

        $originPostal = $this->normalizePostal((string) ($origin->postal_code ?? ''));
        $quoteOrigin = $this->normalizePostal((string) ($quote->origin_postal_code ?? ''));
        $originKey = $this->postalMatchKey($originPostal);
        $quoteOriginKey = $this->postalMatchKey($quoteOrigin);
        if ($originKey === '' || $quoteOriginKey === '' || $originKey !== $quoteOriginKey) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'The rate quote origin does not match the selected ship-from location.',
            ]);
        }

        $destPostal = $this->normalizePostal($destinationPostal);
        $quoteDest = $this->normalizePostal((string) ($quote->destination_postal_code ?? ''));
        $destKey = $this->postalMatchKey($destPostal);
        $quoteDestKey = $this->postalMatchKey($quoteDest);
        if ($destKey === '' || $quoteDestKey === '' || $destKey !== $quoteDestKey) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'The rate quote destination does not match this order.',
            ]);
        }

        $quoteOriginLocationId = data_get($quote->request_summary, 'origin_location_id');
        if (filled($quoteOriginLocationId) && (int) $quoteOriginLocationId !== (int) $origin->id) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'The rate quote was generated for a different ship-from location.',
            ]);
        }

        $quoteOriginCountry = strtoupper((string) data_get($quote->request_summary, 'origin_country', ''));
        $purchaseOriginCountry = strtoupper(trim($originCountry));
        if ($quoteOriginCountry !== '' && $purchaseOriginCountry !== '' && $quoteOriginCountry !== $purchaseOriginCountry) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'The rate quote origin country does not match this shipment.',
            ]);
        }

        $quoteDestinationCountry = strtoupper((string) data_get($quote->request_summary, 'destination_country', ''));
        $purchaseDestinationCountry = strtoupper(trim($destinationCountry));
        if ($quoteDestinationCountry !== '' && $purchaseDestinationCountry !== '' && $quoteDestinationCountry !== $purchaseDestinationCountry) {
            throw ValidationException::withMessages([
                'carrier_rate_quote_id' => 'The rate quote destination country does not match this order.',
            ]);
        }

        if ($residential !== null && array_key_exists('destination_residential', (array) $quote->request_summary)) {
            $quoteResidential = (bool) data_get($quote->request_summary, 'destination_residential');
            if ($quoteResidential !== $residential) {
                throw ValidationException::withMessages([
                    'carrier_rate_quote_id' => 'Residential classification no longer matches the selected rate quote. Get fresh rates.',
                ]);
            }
        }

        if (filled($shipDate)) {
            $quoteShipDate = data_get($quote->request_summary, 'ship_date');
            if (filled($quoteShipDate) && (string) $quoteShipDate !== (string) $shipDate) {
                throw ValidationException::withMessages([
                    'carrier_rate_quote_id' => 'The quoted ship date does not match this label request. Get fresh rates.',
                ]);
            }
        }

        $quotePackages = data_get($quote->request_summary, 'packages');
        if (is_array($quotePackages) && $quotePackages !== []) {
            $quoteFp = $this->packageFingerprint($quotePackages);
            $requestFp = $this->packageFingerprint($packages);
            if ($quoteFp !== $requestFp) {
                throw ValidationException::withMessages([
                    'carrier_rate_quote_id' => 'Package details no longer match the selected rate quote. Get fresh rates.',
                ]);
            }
        }

        return $quote;
    }

    public function assertValidTradeDocumentForPurchase(
        Store $store,
        Order $order,
        CarrierAccount $account,
        int $tradeDocumentId,
        string $originCountry,
        string $destinationCountry,
    ): FedExTradeDocument {
        $doc = FedExTradeDocument::query()
            ->whereKey($tradeDocumentId)
            ->where('store_id', $store->id)
            ->first();

        if (! $doc) {
            throw ValidationException::withMessages([
                'fedex_trade_document_id' => 'Select a valid trade document for this store.',
            ]);
        }

        if ((int) $doc->order_id !== (int) $order->id) {
            throw ValidationException::withMessages([
                'fedex_trade_document_id' => 'That trade document does not belong to this order.',
            ]);
        }

        if ((int) $doc->carrier_account_id !== (int) $account->id) {
            throw ValidationException::withMessages([
                'fedex_trade_document_id' => 'That trade document belongs to a different FedEx account.',
            ]);
        }

        if ($doc->status !== FedExTradeDocument::STATUS_UPLOADED || ! filled($doc->fedex_document_id)) {
            throw ValidationException::withMessages([
                'fedex_trade_document_id' => 'That trade document is not ready. Upload it again before shipping.',
            ]);
        }

        if (strtoupper((string) $doc->origin_country_code) !== strtoupper($originCountry)
            || strtoupper((string) $doc->destination_country_code) !== strtoupper($destinationCountry)
        ) {
            throw ValidationException::withMessages([
                'fedex_trade_document_id' => 'Trade document countries do not match this shipment.',
            ]);
        }

        return $doc;
    }

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>  $packages
     */
    public function packageFingerprint(array $packages): string
    {
        $normalized = [];
        $rows = isset($packages[0]) && is_array($packages[0]) ? $packages : [$packages];
        foreach ($rows as $package) {
            if (! is_array($package)) {
                continue;
            }
            $normalized[] = [
                'weight' => round((float) ($package['weight'] ?? 0), 3),
                'weight_unit' => strtoupper((string) ($package['weight_unit'] ?? 'LB')),
                'length' => isset($package['length']) ? round((float) $package['length'], 2) : null,
                'width' => isset($package['width']) ? round((float) $package['width'], 2) : null,
                'height' => isset($package['height']) ? round((float) $package['height'], 2) : null,
                'dimension_unit' => strtoupper((string) ($package['dimension_unit'] ?? 'IN')),
            ];
        }

        return hash('sha256', json_encode($normalized) ?: '');
    }

    private function normalizePostal(string $postal): string
    {
        return strtoupper(trim($postal));
    }

    private function postalMatchKey(string $postal): string
    {
        $digits = preg_replace('/\D+/', '', $postal) ?? '';
        if (strlen($digits) >= 5) {
            return substr($digits, 0, 5);
        }

        // Canada / alphanumeric: first 3 non-space chars.
        $compact = preg_replace('/\s+/', '', $postal) ?? '';

        return substr($compact, 0, 3);
    }
}
