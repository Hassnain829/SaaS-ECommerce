@php
    $mode = $modeState['key'];
    $isLive = $mode === 'live';
    $modeName = $isLive ? 'Live' : 'Test';
    $selected = (bool) ($selected ?? false);
    $canManage = (bool) ($canManagePayments ?? false);
    $configured = (bool) $modeState['configured'];
    $ready = (bool) $modeState['ready'];
    $connected = (bool) $modeState['connected'];
    $needsAction = (bool) $modeState['needsAction'];
    $inProgress = (bool) $modeState['inProgress'];
    $account = $modeState['account'];
@endphp
<article class="pc-detail" data-pc-detail="{{ $mode }}" @if (! $selected) hidden @endif>
    <div class="pc-detail-header">
        <div>
            <div class="pc-selected-label">Selected mode</div>
            <div class="pc-provider-line">
                <x-brand.stripe-logo variant="wordmark" :size="92" />
                <h3>{{ $modeState['accountTitle'] }}</h3>
                <span class="pc-pill {{ $modeState['pillKey'] }}">{{ $modeState['pillLabel'] }}</span>
            </div>
        </div>
        @if ($canManage && $connected)
            <button class="pc-more-button" type="button" data-pc-more="{{ $mode }}" aria-label="Account actions for {{ $modeState['accountTitle'] }}">
                @include('user_view.payments.partials.icon', ['name' => 'more-vertical', 'size' => 18])
            </button>
        @endif
    </div>

    @if (! $configured)
        <div class="pc-connect-empty">
            <div>
                <span class="pc-connect-empty-icon is-locked">
                    @include('user_view.payments.partials.icon', ['name' => 'lock', 'size' => 26])
                </span>
                <h4>{{ $isLive ? 'Live payments are not available yet' : 'Test payments are not available yet' }}</h4>
                <p>{{ $modeState['unavailableMessage'] }}</p>
            </div>
        </div>
        <div class="pc-detail-footer">
            <span class="pc-detail-footer-note">Platform configuration required</span>
        </div>
    @elseif ($ready)
        <div class="pc-detail-banner">
            <span class="pc-detail-banner-icon" aria-hidden="true">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <circle cx="20" cy="20" r="20" fill="#087f5b"/>
                    <path d="M12.5 20.2 17.2 24.8 27.5 14.2" stroke="#fff" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </span>
            <div>
                <strong>Ready for {{ strtolower($modeName) }} checkout</strong>
                <span>Charges and payouts are enabled for this connected {{ strtolower($modeName) }} account.</span>
            </div>
        </div>
        <div class="pc-details-grid">
            <section class="pc-details-section">
                <h4>Connection</h4>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'copy-check', 'size' => 16])
                    <span>Account ID</span>
                    <strong>{{ $modeState['accountIdText'] }}</strong>
                </div>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'list-checks', 'size' => 16])
                    <span>Onboarding</span>
                    <strong>{{ $modeState['onboardingComplete'] ? 'Complete' : 'In progress' }}</strong>
                </div>
            </section>
            <section class="pc-details-section">
                <h4>Capabilities</h4>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'credit-card', 'size' => 16])
                    <span>{{ $modeName }} charges</span>
                    <span class="pc-data-value">
                        @if ($modeState['chargesEnabled'])
                            @include('user_view.payments.partials.icon', ['name' => 'circle-check', 'size' => 16])
                            Enabled
                        @else
                            Not ready
                        @endif
                    </span>
                </div>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'arrow-up-down', 'size' => 16])
                    <span>{{ $modeName }} payouts</span>
                    <span class="pc-data-value">
                        @if ($modeState['payoutsEnabled'])
                            @include('user_view.payments.partials.icon', ['name' => 'circle-check', 'size' => 16])
                            Enabled
                        @else
                            Not ready
                        @endif
                    </span>
                </div>
            </section>
        </div>
        @if ($isLive && $modeState['usesLocalMirror'])
            <div class="pc-environment-note is-live">
                @include('user_view.payments.partials.icon', ['name' => 'triangle-alert', 'size' => 18])
                <span>Local simulation: live Stripe setup is using test platform keys.</span>
            </div>
        @else
            <div class="pc-environment-note {{ $isLive ? 'is-live' : '' }}">
                @include('user_view.payments.partials.icon', ['name' => $isLive ? 'triangle-alert' : 'info', 'size' => 18])
                <span>{{ $isLive ? 'Live mode charges customers real money. Confirm checkout before opening your store.' : 'Test payments simulate checkout behavior. Customers are never charged.' }}</span>
            </div>
        @endif
        <div class="pc-detail-footer">
            <span class="pc-detail-footer-note">Status checked {{ $modeState['lastVerifiedLower'] }}</span>
            @if ($canManage && $account)
                <div class="pc-detail-actions">
                    <button class="pc-secondary" type="submit" form="pc-refresh-{{ $mode }}" data-turbo="false">Refresh status</button>
                    <button class="pc-danger" type="submit" form="pc-disconnect-{{ $mode }}" data-pc-disconnect="{{ $mode }}">Disconnect</button>
                </div>
            @endif
        </div>
    @elseif ($connected)
        <div class="pc-detail-banner is-blocked">
            <span class="pc-detail-banner-icon" aria-hidden="true">
                <svg width="40" height="40" viewBox="0 0 40 40" fill="none">
                    <circle cx="20" cy="20" r="20" fill="#bd6507"/>
                    <path d="M20 12v10" stroke="#fff" stroke-width="3" stroke-linecap="round"/>
                    <circle cx="20" cy="27.5" r="1.8" fill="#fff"/>
                </svg>
            </span>
            <div>
                <strong>{{ $needsAction ? 'Stripe needs more details' : 'Finish Stripe onboarding' }}</strong>
                <span>
                    Stripe needs more account details before this {{ strtolower($modeName) }} account can accept platform checkout payments.
                    You will connect through Stripe hosted onboarding. No Stripe secret keys are entered here.
                </span>
            </div>
        </div>
        <div class="pc-details-grid">
            <section class="pc-details-section">
                <h4>Connection</h4>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'copy-check', 'size' => 16])
                    <span>Account ID</span>
                    <strong>{{ $modeState['accountIdText'] }}</strong>
                </div>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'list-checks', 'size' => 16])
                    <span>Onboarding</span>
                    <strong>{{ $modeState['onboardingComplete'] ? 'Complete' : 'In progress' }}</strong>
                </div>
            </section>
            <section class="pc-details-section">
                <h4>Capabilities</h4>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'credit-card', 'size' => 16])
                    <span>{{ $modeName }} charges</span>
                    <span class="pc-data-value">{{ $modeState['chargesEnabled'] ? 'Enabled' : 'Not ready' }}</span>
                </div>
                <div class="pc-data-row">
                    @include('user_view.payments.partials.icon', ['name' => 'arrow-up-down', 'size' => 16])
                    <span>{{ $modeName }} payouts</span>
                    <span class="pc-data-value">{{ $modeState['payoutsEnabled'] ? 'Enabled' : 'Not ready' }}</span>
                </div>
            </section>
        </div>
        @if ($isLive && $modeState['usesLocalMirror'])
            <div class="pc-environment-note is-live">
                @include('user_view.payments.partials.icon', ['name' => 'triangle-alert', 'size' => 18])
                <span>Local simulation: live Stripe setup is using test platform keys.</span>
            </div>
        @endif
        <div class="pc-detail-footer">
            <span class="pc-detail-footer-note">Continue on Stripe to finish this connection.</span>
            @if ($canManage && $account)
                <div class="pc-detail-actions">
                    <button class="pc-primary" type="submit" form="pc-continue-{{ $mode }}" data-turbo="false">{{ $modeState['continueLabel'] }}</button>
                    <button class="pc-secondary" type="submit" form="pc-refresh-{{ $mode }}" data-turbo="false">Refresh status</button>
                    <button class="pc-danger" type="submit" form="pc-disconnect-{{ $mode }}" data-pc-disconnect="{{ $mode }}">Disconnect</button>
                </div>
            @endif
        </div>
    @else
        <div class="pc-connect-empty">
            <div>
                <span class="pc-connect-empty-icon">
                    @include('user_view.payments.partials.icon', ['name' => 'link', 'size' => 26])
                </span>
                <h4>Connect {{ $modeState['accountTitle'] }}</h4>
                <p>
                    {{ $isLive
                        ? 'Connect a separate live account through Stripe hosted onboarding before accepting real customer payments.'
                        : 'Connect a test account through Stripe hosted onboarding to safely verify checkout without charging customers.' }}
                    You will connect through Stripe hosted onboarding. No Stripe secret keys are entered here.
                </p>
                <ul class="pc-connect-points">
                    <li>You will connect through Stripe hosted onboarding.</li>
                    <li>No Stripe secret keys are entered here.</li>
                </ul>
                @if ($isLive && $modeState['usesLocalMirror'])
                    <p class="pc-mirror-note">Local simulation: live Stripe setup is using test platform keys.</p>
                @endif
                @if ($canManage)
                    <button class="pc-primary" type="submit" form="pc-connect-{{ $mode }}" data-pc-connect="{{ $mode }}" data-turbo="false">{{ $modeState['connectLabel'] }}</button>
                @endif
            </div>
        </div>
        <div class="pc-detail-footer">
            <span class="pc-detail-footer-note">No Stripe account connected</span>
        </div>
    @endif
</article>
