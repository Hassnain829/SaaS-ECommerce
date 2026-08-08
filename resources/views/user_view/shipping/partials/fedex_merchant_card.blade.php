@php
    $presenter = \App\Support\CarrierAccountStatusPresenter::for($account);
    $envLabel = $account->environment === \App\Models\CarrierAccount::ENVIRONMENT_LIVE ? 'Live' : 'Sandbox';
    $fedExOriginId = (int) data_get($account->settings, 'default_origin_location_id', $account->defaultOriginLocationId());
    $statusKey = match ($account->connection_status) {
        'connected', 'sandbox_platform_fallback' => 'connected',
        'setup_required', 'pending_validation', 'not_connected' => 'setup_required',
        'failed', 'blocked_by_fedex' => 'needs_attention',
        'disabled' => 'disabled',
        default => 'needs_attention',
    };
    $isModelA = $account->usesFedExIntegratorProvider();
@endphp
<x-ui.card class="overflow-hidden !p-0">
    <div class="border-b border-[color:var(--color-border)] px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[color:var(--color-ink-muted)]">FedEx</p>
                <h3 class="mt-1 text-lg font-semibold text-[color:var(--color-ink)]">{{ $account->display_name }}</h3>
                <p class="mt-1 text-sm text-[color:var(--color-ink-muted)]">
                    @if ($isModelA)
                        Your FedEx account is connected for this store. FedEx billing stays between you and FedEx.
                    @else
                        Connected with your FedEx developer account. FedEx billing stays between you and FedEx.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <x-ui.badge tone="info">{{ $envLabel }}</x-ui.badge>
                <x-ui.status-pill :status="$statusKey">
                    {{ $connectionStatusLabels[$account->connection_status] ?? str($account->connection_status)->replace('_', ' ')->title() }}
                </x-ui.status-pill>
                <x-ui.badge tone="success">Merchant-owned</x-ui.badge>
            </div>
        </div>
    </div>

    <div class="space-y-4 px-5 py-4">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div>
                <dt class="text-xs font-semibold text-[color:var(--color-ink-muted)]">Account</dt>
                <dd class="mt-0.5 font-medium text-[color:var(--color-ink)]">{{ $account->maskedAccountNumber() }}</dd>
            </div>
            @if (! $isModelA && $account->usesMerchantFedExDeveloperCredentials() && $account->hasMerchantFedExDeveloperCredentials())
                <div>
                    <dt class="text-xs font-semibold text-[color:var(--color-ink-muted)]">API key</dt>
                    <dd class="mt-0.5 font-medium text-[color:var(--color-ink)]">{{ $account->maskedMerchantClientId() }}</dd>
                </div>
            @endif
            <div>
                <dt class="text-xs font-semibold text-[color:var(--color-ink-muted)]">Billing</dt>
                <dd class="mt-0.5 text-[color:var(--color-ink)]">{{ $presenter->billingLabel() }}</dd>
            </div>
            @if ($fedExOriginId > 0)
                <div>
                    <dt class="text-xs font-semibold text-[color:var(--color-ink-muted)]">Ship-from</dt>
                    <dd class="mt-0.5 text-[color:var(--color-ink)]">{{ collect($locations)->firstWhere('id', $fedExOriginId)?->name ?? 'Location #'.$fedExOriginId }}</dd>
                </div>
            @endif
            @if ($account->last_verified_at)
                <div class="sm:col-span-2">
                    <dt class="text-xs font-semibold text-[color:var(--color-ink-muted)]">Last verified</dt>
                    <dd class="mt-0.5 text-[color:var(--color-ink)]">{{ $account->last_verified_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
        </dl>

        @if ($account->last_error_message && in_array($account->connection_status, ['failed', 'blocked_by_fedex'], true))
            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                We couldn’t finish this connection. {{ $account->last_error_message }}
            </p>
        @endif

        <div class="flex flex-wrap gap-2">
            @php
                $caps = is_array($account->capabilities) ? $account->capabilities : [];
                $checkoutOn = (bool) config('carriers.fedex.checkout_rates_enabled', false)
                    && (bool) $account->enabled_for_checkout
                    && (bool) ($caps['checkout_rates'] ?? false);
                $labelsOn = (bool) config('carriers.fedex.ops_ship_labels_enabled', false)
                    && (bool) ($caps['labels'] ?? $caps['ship_labels'] ?? false);
                $trackingOn = (bool) config('carriers.fedex.ops_tracking_enabled', false)
                    && (bool) ($caps['tracking'] ?? false);
                $statusChips = [
                    [$checkoutOn ? 'Checkout rates on' : 'Checkout rates need setup', $checkoutOn ? 'success' : 'warning'],
                    [$labelsOn ? 'Labels on' : 'Labels not enabled', $labelsOn ? 'success' : 'warning'],
                    [$trackingOn ? 'Tracking on' : 'Tracking not enabled', $trackingOn ? 'success' : 'warning'],
                ];
            @endphp
            @foreach ($statusChips as [$chip, $tone])
                <x-ui.badge :tone="$tone">{{ $chip }}</x-ui.badge>
            @endforeach
        </div>

        @if ($canManageShipping ?? false)
            <div class="flex flex-wrap gap-2 border-t border-[color:var(--color-border)] pt-4">
                @if ($isModelA)
                    <x-ui.button
                        variant="secondary"
                        :href="route('settings.shipping.fedex-integrator.manage', $account)"
                    >
                        Open FedEx Center
                    </x-ui.button>
                @else
                    @if (\Illuminate\Support\Facades\Route::has('settings.shipping.carrier-accounts.fedex.test'))
                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test', $account) }}" class="shipping-submit-form">
                            @csrf
                            <x-ui.button type="submit" class="shipping-submit-btn">Run connection check</x-ui.button>
                        </form>
                    @endif
                    <x-ui.button
                        variant="secondary"
                        :href="route('shipping.carriers.connect.show', 'fedex')"
                    >
                        Edit connection
                    </x-ui.button>
                    @if ($account->connection_status !== 'disabled')
                        <form method="POST" action="{{ route('settings.shipping.carrier-accounts.disable', $account) }}" onsubmit="return confirm('Disable this FedEx account?')">
                            @csrf
                            <x-ui.button type="submit" variant="danger">Disable</x-ui.button>
                        </form>
                    @endif
                @endif
            </div>
        @endif

        @if (! $isModelA)
            <details class="rounded-xl border border-[color:var(--color-border)] bg-[color:var(--color-surface-muted)] px-4 py-3 text-sm">
                <summary class="cursor-pointer font-semibold text-[color:var(--color-ink)]">View technical details</summary>
                <div class="mt-3 space-y-2 text-xs text-[color:var(--color-ink-secondary)]">
                    <p>Connection mode: merchant credentials</p>
                    @if ($account->last_verified_at)
                        <p>Last verified {{ $account->last_verified_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}</p>
                    @endif
                    <p>Account number stays masked. Secrets are never shown in the dashboard.</p>
                </div>
            </details>
        @endif
    </div>
</x-ui.card>
