{{-- Certification / FedEx approval tools — Advanced only. Not day-to-day delivery setup. --}}
@if (($fedExConfig->validationModeEnabled() ?? false) && ($fedExAccounts ?? collect())->isNotEmpty())
    <x-ui.disclosure summary="FedEx approval tools" class="mt-4">
        <x-ui.operator-banner title="Certification tools" class="mb-4">
            These tools prepare a FedEx approval package. They are not required to finish everyday delivery setup.
        </x-ui.operator-banner>

        <div class="space-y-4">
            @foreach ($fedExAccounts as $account)
                @if ($account->usesFedExIntegratorProvider())
                    <x-ui.panel>
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-[color:var(--color-ink)]">{{ $account->display_name }}</p>
                                <p class="mt-1 text-sm text-[color:var(--color-ink-muted)]">Open the approval checklist, upload proof photos, or review capability marks.</p>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                <x-ui.button variant="secondary" :href="route('settings.shipping.carrier-accounts.fedex.validation', $account)">
                                    Open FedEx approval tools
                                </x-ui.button>
                                <x-ui.button variant="ghost" :href="route('settings.shipping.carrier-accounts.fedex.capabilities', [$account, 'evidence_mode' => 1])">
                                    Branding evidence page
                                </x-ui.button>
                            </div>
                        </div>

                        @include('user_view.shipping.partials.fedex_testing_tools', ['account' => $account])
                    </x-ui.panel>
                @endif
            @endforeach
        </div>
    </x-ui.disclosure>
@endif
