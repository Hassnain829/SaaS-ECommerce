<?php

namespace App\Services\Carriers\Core;

use App\Models\CarrierAccount;
use App\Models\Location;
use App\Models\Store;
use App\Services\Carriers\Core\DTO\CarrierConnectionTestResult;
use App\Services\Carriers\Core\DTO\CarrierOriginReadinessResult;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\Carriers\USPS\Support\USPSConfig;
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
                'summary' => $uspsConfig->merchantConnectionEnabled()
                    ? 'Connect your merchant-owned USPS business account. Postage stays on your USPS payment account. Authorize BmyBrand as your Label Provider in the USPS Business Portal.'
                    : 'Platform testing connection for address validation and test rate quotes.',
                'available' => $uspsConfig->merchantConnectionEnabled() || $uspsConfig->isConfigured(),
                'deferred' => false,
                'blocked' => false,
                'connect_route' => $uspsConfig->merchantConnectionEnabled()
                    ? 'settings.shipping.usps-merchant.start'
                    : 'shipping.carriers.connect.show',
                'action' => $uspsConfig->merchantConnectionEnabled()
                    ? 'Connect USPS account'
                    : ($uspsConfig->isConfigured() ? 'Connect for testing' : 'Platform testing unavailable'),
            ],
            self::CARRIER_FEDEX => [
                'code' => self::CARRIER_FEDEX,
                'name' => 'FedEx',
                'summary' => $fedExConfig->modelAEnabled() && $fedExConfig->defaultConnectionModel() === 'integrator_provider'
                    ? 'Connect your merchant-owned FedEx account through the platform integrator registration flow. FedEx billing stays between you and FedEx.'
                    : 'Connect your own FedEx Developer credentials and FedEx account number. FedEx billing stays between you and FedEx. Labels and checkout live rates are not enabled in this phase.',
                'available' => $fedExConfig->isEnabled() && ($fedExConfig->modelAEnabled() || $fedExConfig->modelBDeveloperFallbackEnabled()),
                'deferred' => false,
                'blocked' => false,
                'action' => $fedExConfig->modelAEnabled() && $fedExConfig->defaultConnectionModel() === 'integrator_provider'
                    ? 'Connect FedEx account'
                    : 'Connect FedEx credentials',
                'connect_route' => $fedExConfig->modelAEnabled() && $fedExConfig->defaultConnectionModel() === 'integrator_provider'
                    ? 'settings.shipping.fedex-integrator.start'
                    : 'shipping.carriers.connect.show',
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
    public function ownershipOptions(string $carrier, USPSConfig $uspsConfig, FedExConfig $fedExConfig): array
    {
        return match ($carrier) {
            self::CARRIER_USPS => array_values(array_filter([
                $uspsConfig->merchantConnectionEnabled() ? [
                    'value' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
                    'label' => 'Merchant-owned USPS account',
                    'description' => 'Connect your USPS business account through Label Provider authorization. Postage stays on your EPA. You never paste API keys or passwords here.',
                ] : null,
                $uspsConfig->isConfigured() ? [
                    'value' => CarrierAccount::OWNERSHIP_PLATFORM_TESTING,
                    'label' => 'Platform testing connection',
                    'description' => 'Uses platform testing credentials for address validation and test rate quotes. This is not your merchant-owned USPS account.',
                ] : null,
            ])),
            self::CARRIER_FEDEX => array_values(array_filter([
                $fedExConfig->modelAEnabled() ? [
                    'value' => CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER,
                    'label' => 'FedEx Integrator Provider',
                    'description' => 'Recommended. Connect your FedEx account through platform registration. No FedEx Developer project required.',
                ] : null,
                $fedExConfig->modelBDeveloperFallbackEnabled() ? [
                    'value' => CarrierAccount::OWNERSHIP_MERCHANT_OWNED,
                    'label' => 'Merchant FedEx Developer credentials',
                    'description' => 'Developer fallback. Connect your FedEx Developer API key, secret, and account number.',
                ] : null,
            ])),
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
