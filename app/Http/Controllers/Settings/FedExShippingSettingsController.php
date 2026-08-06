<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Carrier;
use App\Models\CarrierAccount;
use App\Services\Carriers\Core\CarrierProviderManager;
use App\Services\Carriers\FedEx\Connection\FedExAccountRegistrationService;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use App\Services\SecurityLogRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FedExShippingSettingsController extends Controller
{
    public function storeFedExCarrierAccount(Request $request, FedExConfig $fedExConfig, SecurityLogRecorder $securityLogRecorder): RedirectResponse
    {
        abort_unless($fedExConfig->modelBRoutesEnabled(), 404);
        $store = $request->attributes->get('currentStore');
        abort_unless($store, 404);

        if (! $fedExConfig->isConfigured()) {
            return back()
                ->withErrors(['fedex' => 'FedEx sandbox connection is not available on this platform environment yet. Contact the platform admin.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $validated = $request->validate([
            'display_name' => ['nullable', 'string', 'max:120'],
            'environment' => ['required', Rule::in(['sandbox'])],
            'provider_account_number' => ['required', 'string', 'max:32'],
            'company_name' => ['required', 'string', 'max:120'],
            'contact_name' => ['required', 'string', 'max:120'],
            'address_line1' => ['required', 'string', 'max:160'],
            'city' => ['required', 'string', 'max:80'],
            'state' => ['required', 'string', 'max:80'],
            'postal_code' => ['required', 'string', 'max:32'],
            'country_code' => ['required', 'string', 'max:40'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:160'],
            'residential' => ['nullable', 'boolean'],
            'default_origin_location_id' => [
                'nullable',
                'integer',
                Rule::exists('locations', 'id')->where('store_id', $store->id),
            ],
        ]);

        $validated = app(\App\Services\Carriers\FedEx\Connection\FedExRegistrationInputValidator::class)->validateOrFail($validated);
        $accountNumber = (string) $validated['provider_account_number'];

        $fedExCarrier = Carrier::query()->where('code', 'fedex')->where('is_active', true)->firstOrFail();
        $displayName = filled($validated['display_name'] ?? null)
            ? $validated['display_name']
            : 'FedEx sandbox account';

        $settings = [
            'registration' => [
                'company_name' => $validated['company_name'],
                'contact_name' => $validated['contact_name'],
                'address_line1' => $validated['address_line1'],
                'city' => $validated['city'],
                'state' => $validated['state'] ?? null,
                'postal_code' => $validated['postal_code'],
                'country_code' => $validated['country_code'],
                'phone' => $validated['phone'],
                'email' => $validated['email'],
                'provider_account_number' => $accountNumber,
                'residential' => $request->boolean('residential'),
            ],
        ];

        if (filled($validated['default_origin_location_id'] ?? null)) {
            $settings['default_origin_location_id'] = (int) $validated['default_origin_location_id'];
        }

        $account = $store->carrierAccounts()->create([
            'carrier_id' => $fedExCarrier->id,
            'provider' => CarrierAccount::PROVIDER_FEDEX,
            'environment' => CarrierAccount::ENVIRONMENT_SANDBOX,
            'display_name' => $displayName,
            'connection_type' => CarrierAccount::CONNECTION_API,
            'connection_mode' => CarrierAccount::CONNECTION_MODE_FEDEX_INTEGRATOR,
            'provider_account_number' => $accountNumber,
            'status' => CarrierAccount::STATUS_SETUP_REQUIRED,
            'connection_status' => CarrierAccount::CONNECTION_SETUP_REQUIRED,
            'settings' => $settings,
            'default_origin_location_id' => filled($validated['default_origin_location_id'] ?? null)
                ? (int) $validated['default_origin_location_id']
                : null,
            'enabled_for_checkout' => false,
            'created_by' => $request->user()?->id,
            ...CarrierAccount::ownershipAttributesForFedExMerchantOwned(),
        ]);

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_carrier_account_created',
            store: $store,
            metadata: ['carrier_account_id' => $account->id, 'display_name' => $account->display_name]
        );

        return back()
            ->with('success', 'FedEx sandbox account saved. Run Test connection to verify registration.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function updateFedExRegistrationSettings(
        Request $request,
        CarrierAccount $carrierAccount,
        SecurityLogRecorder $securityLogRecorder,
        FedExConfig $fedExConfig,
    ): RedirectResponse {
        abort_unless($fedExConfig->modelBRoutesEnabled(), 404);
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);
        abort_unless(! $carrierAccount->usesFedExIntegratorProvider(), 404);

        $request->validate([
            'residential' => ['nullable', 'boolean'],
        ]);

        $settings = $carrierAccount->settings ?? [];
        $registration = is_array($settings['registration'] ?? null) ? $settings['registration'] : [];
        $registration['residential'] = $request->boolean('residential');
        $settings['registration'] = $registration;

        $carrierAccount->forceFill(['settings' => $settings])->save();

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_registration_settings_updated',
            store: $store,
            metadata: [
                'carrier_account_id' => $carrierAccount->id,
                'residential' => $registration['residential'],
            ]
        );

        return back()
            ->with('success', 'FedEx registration settings updated. Run Test connection again.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function exportFedExDebugPayload(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExAccountRegistrationService $registrationService,
    ): JsonResponse {
        abort_unless(app()->environment(['local', 'testing']), 404);

        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);

        return response()->json(
            $registrationService->redactedValidationSummary($carrierAccount),
            200,
            [],
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES,
        );
    }

    public function enableFedExSandboxPlatformFallback(
        Request $request,
        CarrierAccount $carrierAccount,
        FedExConfig $fedExConfig,
        SecurityLogRecorder $securityLogRecorder,
    ): RedirectResponse {
        abort_unless(app()->environment(['local', 'testing']), 404);
        abort_unless($fedExConfig->modelBRoutesEnabled(), 404);
        abort_unless($fedExConfig->allowsSandboxPlatformFallback(), 403);

        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);

        $settings = $carrierAccount->settings ?? [];
        $settings['sandbox_platform_fallback'] = true;
        $carrierAccount->forceFill(['settings' => $settings])->save();

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_sandbox_platform_fallback_enabled',
            store: $store,
            metadata: ['carrier_account_id' => $carrierAccount->id],
        );

        return back()
            ->with('success', 'Sandbox platform fallback enabled for this FedEx account. Run Test connection to verify platform OAuth only.')
            ->with('success_title', 'Shipping & delivery');
    }

    public function testFedExCarrierAccount(
        Request $request,
        CarrierAccount $carrierAccount,
        CarrierProviderManager $providerManager,
        SecurityLogRecorder $securityLogRecorder,
        FedExConfig $fedExConfig,
    ): RedirectResponse {
        abort_unless($fedExConfig->modelBRoutesEnabled(), 404);
        $store = $request->attributes->get('currentStore');
        abort_unless($store && (int) $carrierAccount->store_id === (int) $store->id, 404);
        abort_unless($carrierAccount->isFedEx(), 404);
        abort_unless(! $carrierAccount->usesFedExIntegratorProvider(), 404);

        try {
            $result = $providerManager->provider(CarrierAccount::PROVIDER_FEDEX)->testConnection(
                $carrierAccount->load('store')
            );
        } catch (\Throwable) {
            $carrierAccount->markFailed('FedEx connection test failed. Please try again.');

            return back()
                ->withErrors(['fedex' => 'FedEx connection test failed. Please try again.'])
                ->with('error_title', 'Shipping & delivery');
        }

        $securityLogRecorder->record(
            $request,
            'shipping.fedex_carrier_account_tested',
            store: $store,
            metadata: [
                'carrier_account_id' => $carrierAccount->id,
                'success' => $result->success,
            ]
        );

        if (! $result->success) {
            return back()
                ->withErrors(['fedex' => $result->detailMessage ?? $result->message])
                ->with('error_title', 'Shipping & delivery')
                ->with('fedex_connection_message', $result->message)
                ->with('fedex_connection_steps', $result->steps)
                ->with('fedex_connection_status', $result->connectionStatus);
        }

        return back()
            ->with('success', $result->message)
            ->with('success_title', 'Shipping & delivery')
            ->with('fedex_connection_steps', $result->steps)
            ->with('fedex_connection_status', $result->connectionStatus);
    }
}
