<?php

namespace App\Services\Carriers\FedEx\Operations;

use App\Models\CarrierAccount;
use App\Models\Store;
use App\Services\Carriers\FedEx\Support\FedExConfig;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Single gate for store-scoped FedEx production operations (rates, address, availability, later labels/track).
 * Never allows platform sandbox fallback credentials for merchant ops.
 */
final class FedExOperationGuard
{
    public const CAPABILITY_ADDRESS_VALIDATION = 'address_validation';

    public const CAPABILITY_SERVICE_AVAILABILITY = 'service_availability';

    public const CAPABILITY_NEGOTIATED_RATES = 'negotiated_rates';

    public const CAPABILITY_CHECKOUT_RATES = 'checkout_rates';

    public const CAPABILITY_SHIP_LABELS = 'ship_labels';

    public const CAPABILITY_TRACKING = 'tracking';

    public function __construct(
        private readonly FedExConfig $config,
    ) {}

    /**
     * @throws HttpException
     */
    public function assertAccountForOperation(
        Store $store,
        CarrierAccount $account,
        string $capability,
    ): void {
        abort_unless((int) $account->store_id === (int) $store->id, 404);
        abort_unless($account->isFedEx(), 404);

        if ($account->usesSandboxPlatformFallback() || $account->isSandboxPlatformFallback()) {
            abort(422, 'Platform FedEx credentials cannot be used for merchant shipping operations.');
        }

        if (! $account->usesFedExIntegratorProvider()) {
            if ($account->usesMerchantFedExDeveloperCredentials() && $this->config->modelBRoutesEnabled()) {
                abort_unless($account->hasMerchantFedExDeveloperCredentials(), 422, 'FedEx developer credentials are incomplete.');
            } else {
                abort(422, 'Connect FedEx through the official Integrator Provider before running shipping operations.');
            }
        } else {
            $this->assertActiveModelAAccount($store, $account);
        }

        if ($account->environment === CarrierAccount::ENVIRONMENT_LIVE && ! $this->config->productionEnabled()) {
            abort(422, 'Live FedEx operations are not enabled yet.');
        }

        // Production app never runs merchant ops against sandbox accounts.
        if (app()->environment('production')
            && $account->environment === CarrierAccount::ENVIRONMENT_SANDBOX
        ) {
            abort(422, 'Sandbox FedEx accounts cannot be used for shipping operations in production.');
        }

        if (! $this->config->allowsIntegratorEnvironment((string) $account->environment)
            && $account->usesFedExIntegratorProvider()) {
            abort(422, 'This FedEx environment is not allowed.');
        }

        if (! $this->capabilityEnabled($capability)) {
            abort(422, $this->capabilityDisabledMessage($capability));
        }

        if ($capability === self::CAPABILITY_CHECKOUT_RATES) {
            abort_unless((bool) $account->enabled_for_checkout, 422, 'This FedEx account is not enabled for checkout rates.');
            $caps = is_array($account->capabilities) ? $account->capabilities : [];
            abort_unless((bool) ($caps['checkout_rates'] ?? false), 422, 'Checkout rates are not enabled for this FedEx account.');
        }

        if ($capability === self::CAPABILITY_SHIP_LABELS) {
            $caps = is_array($account->capabilities) ? $account->capabilities : [];
            abort_unless(
                (bool) ($caps['labels'] ?? $caps['ship_labels'] ?? false),
                422,
                'Label purchase is not enabled for this FedEx account.',
            );
        }

        if ($capability === self::CAPABILITY_TRACKING) {
            $caps = is_array($account->capabilities) ? $account->capabilities : [];
            abort_unless((bool) ($caps['tracking'] ?? false), 422, 'Tracking is not enabled for this FedEx account.');
        }
    }

    /**
     * Active Model A account invariants for production operations.
     *
     * @throws HttpException
     */
    public function assertActiveModelAAccount(Store $store, CarrierAccount $account): void
    {
        abort_unless((int) $account->store_id === (int) $store->id, 404);
        abort_unless($account->usesFedExIntegratorProvider(), 422, 'Connect FedEx through the official Integrator Provider before running shipping operations.');
        abort_unless($account->hasLegacyFedExChildCredentials(), 422, 'FedEx connection credentials are missing. Reconnect FedEx.');
        abort_unless($account->isConnected(), 422, 'FedEx is not connected for this store.');
        abort_unless($account->status === CarrierAccount::STATUS_ENABLED, 422, 'FedEx account is not enabled.');
        abort_unless($account->disconnected_at === null, 422, 'This FedEx account has been disconnected.');
        abort_unless($account->replaced_at === null, 422, 'This FedEx account has been replaced. Use the active connection.');

        $expectedKey = CarrierAccount::fedExActiveStoreKeyFor(
            (int) $store->id,
            (string) $account->environment,
        );
        abort_unless(
            (string) $account->fedex_active_store_key === $expectedKey,
            422,
            'FedEx active connection key is invalid for this store and environment.',
        );
    }

    public function resolveActiveModelAAccount(Store $store, ?string $environment = null): ?CarrierAccount
    {
        foreach ($this->operationalEnvironmentCandidates($environment) as $env) {
            $expectedKey = CarrierAccount::fedExActiveStoreKeyFor((int) $store->id, $env);
            $account = CarrierAccount::query()
                ->where('store_id', $store->id)
                ->where('provider', CarrierAccount::PROVIDER_FEDEX)
                ->where(function ($query): void {
                    $query->where('connection_model', CarrierAccount::CONNECTION_MODEL_INTEGRATOR_PROVIDER)
                        ->orWhere('fedex_integrator_account', true);
                })
                ->where('fedex_active_store_key', $expectedKey)
                ->where('environment', $env)
                ->where('connection_status', CarrierAccount::CONNECTION_CONNECTED)
                ->where('status', CarrierAccount::STATUS_ENABLED)
                ->whereNull('disconnected_at')
                ->whereNull('replaced_at')
                ->orderByDesc('id')
                ->first();

            if ($account !== null) {
                return $account;
            }
        }

        return null;
    }

    /**
     * Environments allowed for operational account resolution.
     * Production Laravel apps never fall back to sandbox.
     *
     * @return list<string>
     */
    public function operationalEnvironmentCandidates(?string $environment = null): array
    {
        // Hard rule: production app ops use live only (and only when production is enabled).
        if (app()->environment('production')) {
            return $this->config->productionEnabled()
                ? [CarrierAccount::ENVIRONMENT_LIVE]
                : [];
        }

        $requested = $this->config->environment(
            $environment ?? $this->config->environment()
        );

        $candidates = [];
        if ($requested === CarrierAccount::ENVIRONMENT_LIVE && $this->config->productionEnabled()) {
            $candidates[] = CarrierAccount::ENVIRONMENT_LIVE;
        }

        // Sandbox operational accounts are local/testing only.
        if (app()->environment(['local', 'testing'])) {
            $candidates[] = CarrierAccount::ENVIRONMENT_SANDBOX;
        }

        return array_values(array_unique($candidates));
    }

    /**
     * @throws HttpException
     */
    public function assertCountryAllowedForEnvironment(string $countryCode, ?string $environment = null): void
    {
        $country = strtoupper(trim($countryCode));
        if ($country === '' || ! preg_match('/^[A-Z]{2}$/', $country)) {
            abort(422, 'A valid country code is required.');
        }

        $environment = $this->config->environment($environment);

        if ($environment === CarrierAccount::ENVIRONMENT_LIVE && ! $this->config->isLiveCountryAllowed($country)) {
            abort(422, 'Live FedEx shipping is limited to approved countries (US and CA).');
        }
    }

    /**
     * @throws HttpException
     */
    public function assertOriginDestinationAllowed(
        string $originCountry,
        string $destinationCountry,
        ?string $environment = null,
    ): void {
        $this->assertCountryAllowedForEnvironment($originCountry, $environment);
        $destination = strtoupper(trim($destinationCountry));

        if ($destination === '' || ! preg_match('/^[A-Z]{2}$/', $destination)) {
            abort(422, 'A valid destination country is required.');
        }

        $environment = $this->config->environment($environment);
        $origin = strtoupper(trim($originCountry));

        if ($environment === CarrierAccount::ENVIRONMENT_LIVE) {
            if (! $this->config->isLiveCountryAllowed($destination)) {
                abort(422, 'Live FedEx destinations are limited to US and Canada until international customs are enabled.');
            }

            return;
        }

        if (! in_array($origin, ['US', 'CA'], true) || ! in_array($destination, ['US', 'CA'], true)) {
            abort(422, 'FedEx address and rate checks currently support US and Canada origins and destinations.');
        }
    }

    public function assertShipDate(?string $shipDate): string
    {
        $date = trim((string) ($shipDate ?: now()->toDateString()));

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            abort(422, 'Ship date must use YYYY-MM-DD format.');
        }

        $today = now()->startOfDay();
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date)->startOfDay();
        } catch (\Throwable) {
            abort(422, 'Ship date is invalid.');
        }

        if ($parsed->lt($today)) {
            abort(422, 'Ship date cannot be in the past.');
        }

        $max = $today->copy()->addDays(max(1, (int) config('carriers.fedex.ops_max_ship_date_days', 10)));
        if ($parsed->gt($max)) {
            abort(422, 'Ship date is too far in the future for FedEx rate and availability checks.');
        }

        return $date;
    }

    public function capabilityEnabled(string $capability): bool
    {
        return match ($capability) {
            self::CAPABILITY_ADDRESS_VALIDATION => filter_var(
                config('carriers.fedex.ops_address_validation_enabled', true),
                FILTER_VALIDATE_BOOL
            ),
            self::CAPABILITY_SERVICE_AVAILABILITY => filter_var(
                config('carriers.fedex.ops_service_availability_enabled', true),
                FILTER_VALIDATE_BOOL
            ),
            self::CAPABILITY_NEGOTIATED_RATES => filter_var(
                config('carriers.fedex.ops_negotiated_rates_enabled', true),
                FILTER_VALIDATE_BOOL
            ),
            self::CAPABILITY_CHECKOUT_RATES => filter_var(
                config('carriers.fedex.checkout_rates_enabled', false),
                FILTER_VALIDATE_BOOL
            ) && filter_var(
                config('carriers.fedex.ops_negotiated_rates_enabled', true),
                FILTER_VALIDATE_BOOL
            ),
            self::CAPABILITY_SHIP_LABELS => filter_var(
                config('carriers.fedex.ops_ship_labels_enabled', false),
                FILTER_VALIDATE_BOOL
            ),
            self::CAPABILITY_TRACKING => filter_var(
                config('carriers.fedex.ops_tracking_enabled', false),
                FILTER_VALIDATE_BOOL
            ),
            default => false,
        };
    }

    private function capabilityDisabledMessage(string $capability): string
    {
        return match ($capability) {
            self::CAPABILITY_ADDRESS_VALIDATION => 'FedEx address validation is not enabled.',
            self::CAPABILITY_SERVICE_AVAILABILITY => 'FedEx service availability checks are not enabled.',
            self::CAPABILITY_NEGOTIATED_RATES => 'FedEx negotiated rates are not enabled.',
            self::CAPABILITY_CHECKOUT_RATES => 'FedEx checkout rates are not enabled.',
            self::CAPABILITY_SHIP_LABELS => 'FedEx label purchase is not enabled.',
            self::CAPABILITY_TRACKING => 'FedEx tracking is not enabled.',
            default => 'This FedEx operation is not enabled.',
        };
    }

    /**
     * Stable idempotency / customer transaction id for FedEx headers and audit.
     */
    public function idempotencyKey(
        Store $store,
        CarrierAccount $account,
        string $operation,
        ?string $subjectKey = null,
    ): string {
        $raw = implode(':', array_filter([
            'fedex',
            (string) $store->id,
            (string) $account->id,
            $operation,
            $subjectKey,
        ]));

        return 'sb-'.Str::lower(Str::substr(hash('sha256', $raw), 0, 40));
    }
}
