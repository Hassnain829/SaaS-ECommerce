<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Models\Location;
use App\Services\Carriers\Core\CarrierOriginReadinessService;
use App\Services\Carriers\Core\CarrierProviderManager;
use App\Services\Carriers\USPS\Auth\USPSOAuthTokenService;
use App\Services\Carriers\USPS\Operations\USPSDomesticRateQuoteService;
use App\Services\Carriers\USPS\Support\USPSConfig;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class USPSShippingSettingsController extends Controller
{
    public function storeUspsCarrierAccount(Request $request, USPSConfig $uspsConfig, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        abort_unless($uspsConfig->merchantRoutesAccessible(), 404);

        if (! $uspsConfig->isConfigured()) {
            return back()
                ->withErrors(['usps' => 'USPS testing connection is not available on this platform environment yet. Contact the platform admin.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['testing'])],
            'default_origin_location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'enabled_for_checkout' => ['nullable', 'boolean'],
        ]);

        $uspsCarrier = Carrier::query()->where('code', 'usps')->where('is_active', true)->firstOrFail();
        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : 'USPS testing account';

        $settings = [];
        if (filled($validated['default_origin_location_id'] ?? null)) {
            $settings['default_origin_location_id'] = (int) $validated['default_origin_location_id'];
        }

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $uspsCarrier->id,
            'provider' => CarrierAccount::PROVIDER_USPS,
            'environment' => CarrierAccount::ENVIRONMENT_TESTING,
            'display_name' => $displayName,
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_USPS_PLATFORM,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'settings' => $settings,
            'default_origin_location_id' => filled($validated['default_origin_location_id'] ?? null)
                ? (int) $validated['default_origin_location_id']
                : null,
            'supported_countries' => ['US'],
            'enabled_for_checkout' => $request->boolean('enabled_for_checkout'),
            'created_by' => $request->user()?->id,
            ...CarrierAccount::ownershipAttributesForUspsPlatformTesting(),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.usps_carrier_account_created',
            store: $store,
            metadata: ['carrier_account_id' => $account->id, 'display_name' => $account->display_name]
        );

        return back()
            ->with('success', 'USPS testing account saved. Run Test connection to verify OAuth.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function testUspsCarrierAccount(
        Request $request,
        CarrierAccount $carrierAccount,
        CarrierProviderManager $providerManager,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isUsps(), 404);
        abort_unless(app(USPSConfig::class)->merchantRoutesAccessible(), 404);

        try {
            $result = $providerManager->provider(CarrierAccount::PROVIDER_USPS)->testConnection(
                $carrierAccount->load('store')
            );
        } catch (\Throwable) {
            $carrierAccount->markFailed('USPS connection test failed. Please try again.');

            return back()
                ->withErrors(['usps' => 'USPS connection test failed. Please try again.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $securityLogRecorder->record(
            $request,
            'shipping.usps_carrier_account_tested',
            store: $store,
            metadata: [
                'carrier_account_id' => $carrierAccount->id,
                'success' => $result->success,
            ]
        );

        if (! $result->success) {
            return back()
                ->withErrors(['usps' => $result->detailMessage ?? $result->message])
                ->with('error_title', 'Shipping & delivery')
                ->with('usps_connection_message', $result->message)
                ->with('usps_connection_steps', $result->steps);
        }

        return back()
            ->with('success', $result->message)
            ->with('success_title', 'Shipping & delivery')
            ->with('usps_connection_steps', $result->steps);
    }

    public function storeUspsTestPackage(Request $request, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);
        abort_unless(app(USPSConfig::class)->merchantRoutesAccessible(), 404);

        $validated = $request->validate([
            'origin_location_id' => [
                'required',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
            'destination_postal_code' => ['required', 'string', 'max:16'],
            'weight_value' => ['required', 'numeric', 'gt:0'],
            'length' => ['required', 'numeric', 'gt:0'],
            'width' => ['required', 'numeric', 'gt:0'],
            'height' => ['required', 'numeric', 'gt:0'],
            'mail_class' => ['nullable', 'string', 'max:64'],
            'carrier_account_id' => [
                'required',
                'integer',
                Rule::exists('carrier_accounts', 'id')->where('store_id', $store->id),
            ],
        ]);

        $package = $store->shipmentPackages()->create([
            'origin_location_id' => $validated['origin_location_id'] ?? null,
            'name' => 'USPS test package',
            'weight_value' => $validated['weight_value'],
            'weight_unit' => 'lb',
            'length' => $validated['length'],
            'width' => $validated['width'],
            'height' => $validated['height'],
            'dimension_unit' => 'in',
            'package_type' => 'parcel',
            'metadata' => [
                'destination_postal_code' => $validated['destination_postal_code'],
                'mail_class' => $validated['mail_class'] ?? null,
            ],
            'created_by' => $request->user()?->id,
        ]);

        $account = CarrierAccount::query()
            ->where('store_id', $store->id)
            ->where('provider', CarrierAccount::PROVIDER_USPS)
            ->whereKey((int) $validated['carrier_account_id'])
            ->firstOrFail();

        $originLocation = Location::query()
            ->where('store_id', $store->id)
            ->whereKey((int) $validated['origin_location_id'])
            ->firstOrFail();

        $originReadiness = app(CarrierOriginReadinessService::class)->assess(
            $originLocation,
            CarrierOriginReadinessService::CARRIER_USPS,
        );

        $accessToken = '';
        if ($originReadiness->ready) {
            $oauth = app(USPSOAuthTokenService::class)->accessToken();
            if ($oauth === null) {
                return back()
                    ->withErrors(['usps' => 'USPS OAuth token is unavailable. Test the USPS connection first.'])
                    ->with('error_title', 'Shipping & delivery');
            }

            $accessToken = (string) ($oauth['access_token'] ?? '');
        }

        ['result' => $quoteResult] = app(USPSDomesticRateQuoteService::class)->quotePackage(
            $store,
            $account,
            $package,
            $validated['destination_postal_code'],
            $accessToken,
            $request->user(),
            $validated['mail_class'] ?? null,
        );

        $securityLogRecorder->record(
            $request,
            'shipping.usps_test_rate_quote_requested',
            store: $store,
            metadata: [
                'carrier_account_id' => $account->id,
                'package_id' => $package->id,
                'success' => $quoteResult->success,
            ]
        );

        if (! $quoteResult->success) {
            return back()
                ->withErrors(['usps' => $quoteResult->errorMessage ?? 'USPS rate quote failed.'])
                ->with('error_title', 'Shipping & delivery');
        }

        return back()
            ->with('success', 'USPS test rate quote saved. This quote is informational only and does not change checkout totals.')
            ->with('success_title', 'Shipping & delivery');
    }
}
