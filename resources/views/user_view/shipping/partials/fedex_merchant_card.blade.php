@php
    $presenter = \App\Support\CarrierAccountStatusPresenter::for($account);
    $envLabel = $account->environment === \App\Models\CarrierAccount::ENVIRONMENT_LIVE ? 'Production' : 'Sandbox';
    $fedExOriginId = (int) data_get($account->settings, 'default_origin_location_id', $account->defaultOriginLocationId());
    $fedExOriginReadiness = $fedExOriginId > 0 ? ($originReadinessByLocationId[$fedExOriginId] ?? null) : null;
    $stepDiagnostics = ($fedExStepDiagnostics[$account->id] ?? []);
    $registrationDiagnostics = ($fedExRegistrationRequestDiagnostics[$account->id] ?? null);
    $accountEvents = ($fedExApiEvents ?? collect())->where('carrier_account_id', $account->id);
    $capabilityMatrix = ($fedExValidationStatusByAccountId[$account->id] ?? []);
    $capabilityBadge = static function (array $cap): string {
        return match ($cap['status'] ?? 'not_run') {
            'passed' => 'bg-emerald-50 text-emerald-800',
            'blocked' => 'bg-amber-50 text-amber-900',
            'failed' => 'bg-red-50 text-red-800',
            'in_progress', 'not_started' => 'bg-slate-100 text-slate-700',
            default => 'bg-slate-100 text-slate-600',
        };
    };
@endphp
<article class="overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-sm">
    <div class="border-b border-[#F1F5F9] px-5 py-4">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="min-w-0">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">FedEx {{ $account->usesFedExIntegratorProvider() ? 'Integrator Provider' : 'Merchant Account' }}</p>
                <h3 class="mt-1 text-lg font-semibold text-[#0F172A]">{{ $account->display_name }}</h3>
                <p class="mt-1 text-sm text-[#64748B]">
                    @if ($account->usesFedExIntegratorProvider())
                        Merchant-owned account connected through platform FedEx registration. FedEx billing stays between you and FedEx.
                    @else
                        Your own FedEx Developer credentials. FedEx billing stays between you and FedEx.
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <span class="rounded-full bg-[#EFF6FF] px-2.5 py-1 text-xs font-bold text-[#1D4ED8]">{{ $envLabel }}</span>
                @if ($account->usesFedExIntegratorProvider())
                    <span class="rounded-full bg-[#EEF2FF] px-2.5 py-1 text-xs font-bold text-[#4338CA]">Integrator Provider</span>
                    <span class="rounded-full bg-[#F5F3FF] px-2.5 py-1 text-xs font-bold text-[#6D28D9]">integrator_child credentials</span>
                @endif
                <span class="rounded-full {{ $connectionStatusBadge($account->connection_status) }} px-2.5 py-1 text-xs font-bold">{{ $connectionStatusLabels[$account->connection_status] ?? str($account->connection_status)->replace('_', ' ')->title() }}</span>
                <span class="rounded-full bg-[#F0FDF4] px-2.5 py-1 text-xs font-bold text-[#047857]">Merchant-owned</span>
            </div>
        </div>
    </div>

    <div class="space-y-4 px-5 py-4">
        <dl class="grid gap-3 text-sm sm:grid-cols-2">
            <div><dt class="text-xs font-semibold text-[#64748B]">Account</dt><dd class="mt-0.5 font-medium text-[#0F172A]">{{ $account->maskedAccountNumber() }}</dd></div>
            @if ($account->usesMerchantFedExDeveloperCredentials() && $account->hasMerchantFedExDeveloperCredentials() && ($fedExConfig->modelBDeveloperFallbackEnabled() ?? false))
                <div><dt class="text-xs font-semibold text-[#64748B]">API key</dt><dd class="mt-0.5 font-medium text-[#0F172A]">{{ $account->maskedMerchantClientId() }}</dd></div>
            @endif
            <div><dt class="text-xs font-semibold text-[#64748B]">Billing</dt><dd class="mt-0.5 text-[#0F172A]">{{ $presenter->billingLabel() }}</dd></div>
            @if ($fedExOriginId > 0)
                <div><dt class="text-xs font-semibold text-[#64748B]">Ship-from</dt><dd class="mt-0.5 text-[#0F172A]">{{ collect($locations)->firstWhere('id', $fedExOriginId)?->name ?? 'Location #'.$fedExOriginId }}</dd></div>
            @endif
            @if ($account->last_verified_at)
                <div class="sm:col-span-2"><dt class="text-xs font-semibold text-[#64748B]">Last verified</dt><dd class="mt-0.5 text-[#0F172A]">{{ $account->last_verified_at->timezone($selectedStore->timezone ?? 'UTC')->format('M j, Y g:i A') }}</dd></div>
            @endif
        </dl>

        @if ($account->last_error_message && in_array($account->connection_status, ['failed', 'blocked_by_fedex'], true))
            <p class="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">{{ $account->last_error_message }}</p>
        @endif

        @if ($account->usesFedExIntegratorProvider() && $capabilityMatrix !== [])
            <div class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Validation capability status</p>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                    @foreach ([
                        'registration' => 'Registration',
                        'address_validation' => 'Address validation',
                        'service_availability' => 'Service availability',
                        'rate_quote' => 'Rate quote',
                        'ship_validate' => 'Ship validate',
                        'ship_label_pdf' => 'Label PDF',
                        'ship_label_png' => 'Label PNG',
                        'ship_label_zpl' => 'Label ZPL',
                    ] as $key => $label)
                        @php($cap = $capabilityMatrix[$key] ?? [])
                        <div class="flex items-start justify-between gap-2 rounded-lg border border-[#E2E8F0] bg-white px-3 py-2">
                            <dt class="text-xs font-semibold text-[#475569]">{{ $label }}</dt>
                            <dd class="text-right">
                                <span class="rounded-full px-2 py-0.5 text-[11px] font-bold {{ $capabilityBadge($cap) }}">{{ $cap['label'] ?? 'Not run' }}</span>
                            </dd>
                        </div>
                    @endforeach
                </dl>
            </div>
        @else
            <div class="flex flex-wrap gap-2">
                @foreach (['Rates not enabled', 'Labels not enabled', 'Tracking not enabled', 'Pickup not enabled'] as $chip)
                    <span class="rounded-full bg-[#F1F5F9] px-2.5 py-1 text-xs font-semibold text-[#64748B]">{{ $chip }}</span>
                @endforeach
                <span class="rounded-full bg-[#F1F5F9] px-2.5 py-1 text-xs font-semibold text-[#64748B]">Checkout live rates not enabled</span>
            </div>
        @endif

        @if ($canManageShipping ?? false)
            <div class="flex flex-wrap gap-2 border-t border-[#F1F5F9] pt-4">
                <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.test', $account) }}" class="shipping-submit-form">
                    @csrf
                    <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white shipping-submit-btn">Run connection check</button>
                </form>
                <a href="{{ $account->usesFedExIntegratorProvider() ? route('settings.shipping.fedex-integrator.start') : route('shipping.carriers.connect.show', 'fedex') }}" class="inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-4 py-2 text-sm font-semibold text-[#475569]">{{ $account->usesFedExIntegratorProvider() ? 'Reconnect FedEx' : 'Edit credentials' }}</a>
                @if (($fedExConfig->validationModeEnabled() ?? false) && $account->usesFedExIntegratorProvider())
                    <a href="{{ route('settings.shipping.carrier-accounts.fedex.validation-export', $account) }}" class="inline-flex items-center rounded-lg border border-[#CBD5E1] bg-white px-4 py-2 text-sm font-semibold text-[#475569]">Export FedEx validation evidence</a>
                @endif
                @if ($account->connection_status !== 'disabled')
                    <form method="POST" action="{{ route('settings.shipping.carrier-accounts.disable', $account) }}" onsubmit="return confirm('Disable this FedEx account?')">
                        @csrf
                        <button class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-4 py-2 text-sm font-semibold text-[#991B1B]">Disable</button>
                    </form>
                @endif
            </div>
        @endif

        @include('user_view.shipping.partials.fedex_testing_tools', compact('account'))

        <details class="rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm">
            <summary class="cursor-pointer font-semibold text-[#0F172A]">View technical details</summary>
            <div class="mt-3 space-y-3 text-xs text-[#475569]">
                @if (session('fedex_connection_steps') && ($fedExAccounts ?? collect())->first()?->id === $account->id)
                    <div>
                        <p class="font-semibold text-[#0F172A]">Latest connection test</p>
                        <ul class="mt-1 space-y-1">
                            @foreach (session('fedex_connection_steps') as $step => $status)
                                <li>{{ str($step)->replace('_', ' ')->title() }}: {{ str($status)->replace('_', ' ')->title() }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if ($stepDiagnostics !== [])
                    <div>
                        <p class="font-semibold text-[#0F172A]">Connection steps</p>
                        <ul class="mt-1 space-y-1">
                            @foreach ($stepDiagnostics as $stepKey => $diag)
                                <li>{{ str($stepKey)->replace('_', ' ')->title() }} · {{ str($diag['status'] ?? '')->replace('_', ' ')->title() }}@if ($diag['http_status'] ?? null) · HTTP {{ $diag['http_status'] }}@endif</li>
                            @endforeach
                        </ul>
                    </div>
                @endif
                @if (app()->environment(['local', 'testing']) && $account->usesLegacyFedExIntegratorRegistration())
                    <div class="space-y-3">
                        <p class="font-semibold text-[#0F172A]">Legacy FedEx integrator registration diagnostic</p>
                        @if ($registrationDiagnostics)
                            <p class="text-[#64748B]">Last registration attempt captured for local debugging.</p>
                        @endif
                        <a href="{{ route('settings.shipping.carrier-accounts.fedex.debug-payload', $account) }}" class="inline-flex text-sm font-semibold text-[#1D4ED8]">Export legacy registration diagnostic</a>
                        @php($residential = (bool) data_get($account->settings, 'registration.residential', false))
                        <p><span class="font-semibold text-[#0F172A]">Residential setting:</span> {{ $residential ? 'true' : 'false' }}</p>
                        @if ($canManageShipping ?? false)
                            <form method="POST" action="{{ route('settings.shipping.carrier-accounts.fedex.registration.update', $account) }}" class="shipping-submit-form flex flex-wrap items-center gap-2">
                                @csrf
                                <label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" name="residential" value="1" @checked($residential) class="rounded border-[#CBD5E1]"> Residential address</label>
                                <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-3 py-1.5 text-xs font-semibold text-[#475569] shipping-submit-btn">Update registration settings</button>
                            </form>
                        @endif
                    </div>
                @endif
                @if ($accountEvents->isNotEmpty())
                    <div>
                        <p class="font-semibold text-[#0F172A]">Recent API activity</p>
                        @foreach ($accountEvents->take(5) as $event)
                            <p class="mt-1">{{ str($event->action)->replace('_', ' ')->title() }} · {{ str($event->status)->title() }}@if (data_get($event->response_summary, 'http_status')) · HTTP {{ data_get($event->response_summary, 'http_status') }}@endif</p>
                        @endforeach
                    </div>
                @endif
            </div>
        </details>
    </div>
</article>
