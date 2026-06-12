<?php

namespace App\Services\Carriers;

use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Store;
use App\Models\User;
use App\Services\Carriers\DTO\CarrierConnectionTestResult;
use App\Services\Carriers\DTO\CarrierOriginReadinessResult;
use App\Services\Carriers\FedEx\FedExConfig;
use App\Services\Carriers\USPS\USPSConfig;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CarrierConnectionWizardService
{
    public const CARRIER_USPS = 'usps';

    public const CARRIER_FEDEX = 'fedex';

    public const CARRIER_UPS = 'ups';

    public const CARRIER_DHL = 'dhl';

    public const CARRIER_MANUAL = 'manual';

    /**
     * @return list<string>
     */
    public function supportedCarriers(): array
    {
        return [
            self::CARRIER_USPS,
            self::CARRIER_FEDEX,
            self::CARRIER_UPS,
            self::CARRIER_DHL,
            self::CARRIER_MANUAL,
        ];
    }

    public function normalizeCarrierCode(string $carrier): string
    {
        $carrier = Str::lower(trim($carrier));

        abort_unless(in_array($carrier, $this->supportedCarriers(), true), 404);

        return $carrier;
    }

    /**
     * @return array<string, mixed>
     */
    public function carrierCard(string $carrier, Store $store, USPSConfig $uspsConfig, FedExConfig $fedExConfig): array
    {
        return match ($carrier) {
            self::CARRIER_USPS => [
                'code' => self::CARRIER_USPS,
                'name' => 'USPS',
                'summary' => 'Platform testing connection for address validation and test rate quotes.',
                'available' => $uspsConfig->isConfigured(),
                'deferred' => false,
                'blocked' => false,
                'action' => $uspsConfig->isConfigured() ? 'Connect for testing' : 'Platform testing unavailable',
            ],
            self::CARRIER_FEDEX => [
                'code' => self::CARRIER_FEDEX,
                'name' => 'FedEx',
                'summary' => 'Connect a merchant-owned FedEx account for account setup and testing. Labels and FedEx billing remain handled by the merchant.',
                'available' => $fedExConfig->isConfigured(),
                'deferred' => false,
                'blocked' => $this->storeHasBlockedFedExAccount($store),
                'action' => $fedExConfig->isConfigured() ? 'Start FedEx setup' : 'Setup unavailable',
            ],
            self::CARRIER_UPS => [
                'code' => self::CARRIER_UPS,
                'name' => 'UPS',
                'summary' => 'UPS carrier integration is planned for a later phase.',
                'available' => false,
                'deferred' => true,
                'blocked' => false,
                'action' => 'Coming later',
            ],
            self::CARRIER_DHL => [
                'code' => self::CARRIER_DHL,
                'name' => 'DHL',
                'summary' => 'DHL carrier integration is planned for a later phase.',
                'available' => false,
                'deferred' => true,
                'blocked' => false,
                'action' => 'Coming later',
            ],
            self::CARRIER_MANUAL => [
                'code' => self::CARRIER_MANUAL,
                'name' => 'Manual / Local delivery',
                'summary' => 'Use your own courier, local driver, or manual tracking workflow.',
                'available' => true,
                'deferred' => false,
                'blocked' => false,
                'action' => 'Add manual delivery',
            ],
            default => abort(404),
        };
    }

    /**
     * @return Collection<int, array{location: Location, readiness: CarrierOriginReadinessResult}>
     */
    public function originOptions(Store $store, string $carrierContext = CarrierOriginReadinessService::CARRIER_USPS): Collection
    {
        return $store->locations()
            ->orderByDesc('is_default')
            ->orderByDesc('is_active')
            ->orderBy('name')
            ->get()
            ->map(function (Location $location) use ($carrierContext): array {
                return [
                    'location' => $location,
                    'readiness' => app(CarrierOriginReadinessService::class)->assessForFulfillmentOrigin($location, $carrierContext),
                ];
            });
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function ownershipOptions(string $carrier, USPSConfig $uspsConfig): array
    {
        return match ($carrier) {
            self::CARRIER_USPS => array_values(array_filter([
                $uspsConfig->isConfigured() ? [
                    'value' => CarrierAccount::OWNERSHIP_PLATFORM_TESTING,
                    'label' => 'Platform testing connection',
                    'description' => 'Uses platform testing credentials for address validation and test rate quotes. This is not your merchant-owned USPS account.',
                ] : null,
                [
                    'value' => 'merchant_owned_planned',
                    'label' => 'Merchant-owned USPS account',
                    'description' => 'Merchant-owned USPS credential setup is planned for a later phase. Use platform testing or manual delivery for now.',
                    'disabled' => true,
                ],
            ])),
            self::CARRIER_FEDEX => [[
                'value' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
                'label' => 'Merchant-owned FedEx account',
                'description' => 'Connect your FedEx account number and registration details. FedEx billing stays between you and FedEx.',
            ]],
            self::CARRIER_MANUAL => [[
                'value' => CarrierAccount::OWNERSHIP_MANUAL,
                'label' => 'Manual/local delivery',
                'description' => 'Track shipments manually without a live carrier API connection.',
            ]],
            default => [],
        };
    }

    public function applyOriginSelection(CarrierAccount $account, Location $location, string $carrierContext): CarrierOriginReadinessResult
    {
        abort_unless((int) $location->store_id === (int) $account->store_id, 404);

        $readiness = app(CarrierOriginReadinessService::class)->assessForFulfillmentOrigin($location, $carrierContext);

        $account->assignDefaultOriginLocation($location->id);
        $account->syncOriginValidation(
            $readiness->ready ? CarrierAccount::ORIGIN_VALIDATION_READY : CarrierAccount::ORIGIN_VALIDATION_NEEDS_ATTENTION,
            $readiness->merchantMessage,
        );

        return $readiness;
    }

    public function testConnection(CarrierAccount $account): CarrierConnectionTestResult
    {
        return app(CarrierProviderManager::class)
            ->provider($account->provider)
            ->testConnection($account->load('store'));
    }

    private function storeHasBlockedFedExAccount(Store $store): bool
    {
        return $store->carrierAccounts()
            ->where('provider', CarrierAccount::PROVIDER_FEDEX)
            ->where('connection_status', CarrierAccount::CONNECTION_BLOCKED_BY_FEDEX)
            ->exists();
    }
}
