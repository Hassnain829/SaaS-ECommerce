@extends('layouts.user.user-sidebar')

@section('title', 'Payments — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Payments" lead="Customers pay through this portal with Stripe.">
        <x-slot:actions>
            <a href="{{ route('settings.taxes.index') }}" class="inline-flex h-9 items-center rounded-md border border-border bg-surface px-3.5 text-sm font-semibold text-ink-secondary transition hover:bg-surface-muted hover:text-ink">Taxes</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
<div
    class="settings-workspace-fluid settings-page payments-console"
    data-pc-root
    data-pc-active="{{ $activeMode }}"
    data-pc-selected="{{ $selectedMode }}"
    data-pc-can-manage="{{ $canManagePayments ? '1' : '0' }}"
    data-pc-store="{{ $selectedStore->id }}"
    data-pc-test-ready="{{ $testReady ? '1' : '0' }}"
    data-pc-live-ready="{{ $liveReady ? '1' : '0' }}"
    data-pc-test-configured="{{ $testConfigured ? '1' : '0' }}"
    data-pc-live-configured="{{ $liveConfigured ? '1' : '0' }}"
>
    @include('user_view.partials.flash_success')

    @if ($errors->any())
        <div class="pc-alert" role="alert">{{ $errors->first() }}</div>
    @endif

    @unless ($canManagePayments)
        <div class="pc-alert pc-alert-muted">You can view payment accounts, but you do not have permission to change them.</div>
    @endunless

    <div class="pc-intro">
        <h2>Payment operations</h2>
        <p>How this store accepts payments. Connect Stripe and control platform checkout for this store.</p>
    </div>

    <section class="pc-readiness-ribbon" aria-label="Payment readiness">
        <div class="pc-readiness-main {{ $activeReady ? '' : 'is-blocked' }}">
            <div class="pc-readiness-icon" aria-hidden="true">
                @include('user_view.payments.partials.icon', ['name' => $activeReady ? 'shield-check' : 'triangle-alert', 'size' => 34])
            </div>
            <div>
                <div class="pc-eyebrow">{{ strtoupper($activeMode) }} mode active</div>
                @if ($activeReady)
                    <h3>{{ $activeMode === 'live' ? 'Live checkout ready' : 'Test checkout ready' }}</h3>
                    <p>
                        Platform checkout is active.
                        {{ $activeMode === 'live' ? 'Real customer payments are enabled.' : 'Safe test transactions · No real money is charged.' }}
                    </p>
                @else
                    <h3>Checkout is blocked until Stripe is connected</h3>
                    <p>Platform checkout is the only payment path. Connect Stripe below before customers can pay.</p>
                @endif
            </div>
        </div>
        <div class="pc-mode-control">
            <div class="pc-eyebrow">Active payment mode</div>
            <div class="pc-mode-buttons">
                <button
                    class="pc-mode-button {{ $activeMode === 'test' ? 'is-active' : '' }} {{ ! $testReady && $activeMode !== 'test' ? 'is-blocked' : '' }}"
                    type="{{ $canManagePayments && $activeMode !== 'test' && $testReady ? 'submit' : 'button' }}"
                    @if ($canManagePayments && $activeMode !== 'test' && $testReady) form="pc-mode-test-form" @endif
                    data-pc-switch="test"
                    aria-pressed="{{ $activeMode === 'test' ? 'true' : 'false' }}"
                    @disabled(! $testConfigured)
                >
                    <span class="pc-mode-dot" aria-hidden="true"></span>
                    <span>Test @if (! $testReady && $activeMode !== 'test')<small>{{ $paymentModes['test']['pillLabel'] }}</small>@endif</span>
                </button>
                <button
                    class="pc-mode-button {{ $activeMode === 'live' ? 'is-active' : '' }} {{ ! $liveReady && $activeMode !== 'live' ? 'is-blocked' : '' }}"
                    type="{{ $canManagePayments && $activeMode !== 'live' && $liveReady ? 'submit' : 'button' }}"
                    @if ($canManagePayments && $activeMode !== 'live' && $liveReady) form="pc-mode-live-form" @endif
                    data-pc-switch="live"
                    aria-pressed="{{ $activeMode === 'live' ? 'true' : 'false' }}"
                    @disabled(! $liveConfigured)
                >
                    <span class="pc-mode-dot" aria-hidden="true"></span>
                    <span>Live @if (! $liveReady && $activeMode !== 'live')<small>{{ $paymentModes['live']['pillLabel'] }}</small>@endif</span>
                </button>
            </div>
        </div>
        <div class="pc-last-verified">
            <div>
                <div class="pc-eyebrow">Last verified</div>
                <div class="pc-last-value">{{ $activeVerified }}</div>
            </div>
            @if ($canManagePayments && ($activeConnectAccount || $activeState['connected']))
                <button class="pc-secondary" type="submit" form="pc-refresh-{{ $activeMode }}" data-turbo="false">Refresh status</button>
            @else
                <button class="pc-secondary" type="button" data-pc-select="{{ $activeMode }}" {{ $canManagePayments ? '' : 'disabled' }}>Refresh status</button>
            @endif
        </div>
    </section>

    <section class="pc-console" aria-label="Stripe payment accounts">
        <div class="pc-mode-list">
            <div class="pc-mode-list-title">
                <h3>Payment modes</h3>
                <span class="pc-count">2</span>
            </div>
            @foreach ($paymentModes as $modeKey => $modeState)
                <a
                    class="pc-mode-row {{ $selectedMode === $modeKey ? 'is-selected' : '' }}"
                    href="{{ route('settings.payments.index', ['mode' => $modeKey]) }}"
                    data-pc-select="{{ $modeKey }}"
                    aria-pressed="{{ $selectedMode === $modeKey ? 'true' : 'false' }}"
                >
                    <span class="pc-mode-icon">
                        @include('user_view.payments.partials.icon', ['name' => $modeState['configured'] ? 'credit-card' : 'lock', 'size' => 20])
                    </span>
                    <span>
                        <span class="pc-mode-name-line">
                            <strong>{{ $modeState['label'] }}</strong>
                            <span class="pc-pill {{ $modeState['pillKey'] }}">{{ $modeState['pillLabel'] }}</span>
                        </span>
                        <span class="pc-mode-account">{{ $modeState['accountIdText'] }}</span>
                        <span class="pc-mode-helper">{{ $modeState['rowHelper'] }}</span>
                    </span>
                    @include('user_view.payments.partials.icon', ['name' => 'chevron-right', 'size' => 16])
                </a>
            @endforeach
        </div>
        <div class="pc-detail-stack">
            @foreach ($paymentModes as $modeKey => $modeState)
                @include('user_view.payments.partials.mode_detail', [
                    'modeState' => $modeState,
                    'selected' => $selectedMode === $modeKey,
                    'canManagePayments' => $canManagePayments,
                ])
            @endforeach
        </div>
    </section>

    <p class="sr-only">{{ $paymentModes['test']['helper'] }}</p>

    @if (! $activeReady)
        <section class="pc-action-strip is-warning">
            <div class="pc-action-copy">
                @include('user_view.payments.partials.icon', ['name' => 'triangle-alert', 'size' => 26])
                <div>
                    <strong>Customers cannot pay in {{ $activeMode === 'live' ? 'Live' : 'Test' }} mode</strong>
                    <span>Reconnect this Stripe account{{ $otherReady ? ' or use the ready '.($otherMode === 'live' ? 'Live' : 'Test').' account.' : '.' }}</span>
                </div>
            </div>
            @if ($otherReady && $canManagePayments)
                <button class="pc-secondary" type="submit" form="pc-mode-{{ $otherMode }}-form" data-pc-switch="{{ $otherMode }}">Switch to {{ $otherMode }} mode</button>
            @else
                <a class="pc-secondary" href="{{ route('settings.payments.index', ['mode' => $activeMode]) }}" data-pc-select="{{ $activeMode }}">Review connection</a>
            @endif
        </section>
    @elseif ($activeMode === 'live')
        <section class="pc-action-strip">
            <div class="pc-action-copy">
                @include('user_view.payments.partials.icon', ['name' => 'flask', 'size' => 26])
                <div>
                    <strong>Need to test checkout?</strong>
                    <span>Your connected test account remains available without affecting live payments.</span>
                </div>
            </div>
            @if ($canManagePayments)
                <button class="pc-secondary" type="submit" form="pc-mode-test-form" data-pc-switch="test">Switch to test mode</button>
            @endif
        </section>
    @elseif ($otherReady)
        <section class="pc-action-strip">
            <div class="pc-action-copy">
                @include('user_view.payments.partials.icon', ['name' => 'rocket', 'size' => 26])
                <div>
                    <strong>Ready to accept real payments?</strong>
                    <span>Your live Stripe account is connected and ready.</span>
                </div>
            </div>
            @if ($canManagePayments)
                <button class="pc-secondary" type="submit" form="pc-mode-live-form" data-pc-switch="live">Switch to live mode</button>
            @endif
        </section>
    @else
        <section class="pc-action-strip">
            <div class="pc-action-copy">
                @include('user_view.payments.partials.icon', ['name' => 'flask', 'size' => 26])
                <div>
                    <strong>Test checkout is ready</strong>
                    <span>No real money is charged while Test mode is active.</span>
                </div>
            </div>
            <a class="pc-secondary" href="{{ route('settings.payments.index', ['mode' => 'live']) }}" data-pc-select="live">Review live payments</a>
        </section>
    @endif

    <section class="pc-security-note">
        <div class="pc-security-copy">
            @include('user_view.payments.partials.icon', ['name' => 'shield-check', 'size' => 30])
            <div>
                <strong>Secure Stripe connection</strong>
                <span>You will connect through Stripe hosted onboarding. No Stripe secret keys are entered here. A Stripe account already used in WooCommerce cannot be reused here — connect from this portal.</span>
            </div>
        </div>
        <button class="pc-text-action" type="button" data-pc-security>
            How connection security works
            @include('user_view.payments.partials.icon', ['name' => 'arrow-right', 'size' => 16])
        </button>
    </section>

    @if ($showDeveloperDiagnostics ?? false)
        @include('user_view.payments.partials.developer_diagnostics', [
            'stripeConfig' => $stripeConfig ?? [],
            'selectedStore' => $selectedStore,
        ])
    @endif

    @if ($canManagePayments)
        <div class="sr-only" aria-hidden="true">
            <form id="pc-mode-test-form" method="POST" action="{{ route('settings.payments.platform-payment-mode') }}">
                @csrf
                <input type="hidden" name="platform_payment_mode" value="test">
            </form>
            <form id="pc-mode-live-form" method="POST" action="{{ route('settings.payments.platform-payment-mode') }}">
                @csrf
                <input type="hidden" name="platform_payment_mode" value="live">
            </form>
            @if ($testConfigured)
                <form id="pc-connect-test" method="POST" action="{{ route('settings.payments.stripe.connect.test') }}" data-turbo="false">
                    @csrf
                </form>
            @endif
            @if ($liveConfigured)
                <form id="pc-connect-live" method="POST" action="{{ route('settings.payments.stripe.connect.live') }}" data-turbo="false">
                    @csrf
                </form>
            @endif
            @foreach ($paymentModes as $modeKey => $modeState)
                @if ($modeState['account'])
                    <form id="pc-refresh-{{ $modeKey }}" method="POST" action="{{ route('settings.payments.stripe.connect.status', $modeState['account']) }}" data-turbo="false">
                        @csrf
                    </form>
                    <form id="pc-continue-{{ $modeKey }}" method="POST" action="{{ route('settings.payments.stripe.connect.refresh', $modeState['account']) }}" data-turbo="false">
                        @csrf
                    </form>
                    <form
                        id="pc-disconnect-{{ $modeKey }}"
                        method="POST"
                        action="{{ route('settings.payments.stripe.connect.disconnect', $modeState['account']) }}"
                        data-turbo="false"
                        data-ui-confirm="Existing orders stay unchanged."
                        data-ui-confirm-title="Disable this Stripe {{ $modeKey }} account?"
                        data-ui-confirm-action="Disable account"
                    >
                        @csrf
                    </form>
                @endif
            @endforeach
        </div>
    @endif
</div>
@endsection

@push('overlays')
    @if ($canManagePayments)
        <div class="pc-context-menu" id="pcAccountMenu" hidden>
            <button type="button" data-pc-menu-action="refresh">
                @include('user_view.payments.partials.icon', ['name' => 'refresh', 'size' => 16])
                Refresh status
            </button>
            <button class="danger" type="button" data-pc-menu-action="disconnect">
                @include('user_view.payments.partials.icon', ['name' => 'unlink', 'size' => 16])
                Disconnect account
            </button>
        </div>
    @endif

    <dialog class="pc-dialog" id="pcModeDialog">
        <div class="pc-dialog-header">
            <h3 id="pcModeDialogTitle">Switch payment mode?</h3>
            <button class="pc-dialog-close" type="button" data-pc-close="pcModeDialog" aria-label="Close">
                @include('user_view.payments.partials.icon', ['name' => 'x', 'size' => 16])
            </button>
        </div>
        <div class="pc-dialog-body" id="pcModeDialogBody"></div>
        <div class="pc-dialog-footer">
            <button class="pc-secondary" type="button" data-pc-close="pcModeDialog">Cancel</button>
            <button class="pc-primary" type="button" id="pcConfirmModeButton">Switch mode</button>
        </div>
    </dialog>

    <dialog class="pc-dialog" id="pcConnectDialog">
        <div class="pc-dialog-header">
            <h3 id="pcConnectDialogTitle">Connect Stripe account</h3>
            <button class="pc-dialog-close" type="button" data-pc-close="pcConnectDialog" aria-label="Close">
                @include('user_view.payments.partials.icon', ['name' => 'x', 'size' => 16])
            </button>
        </div>
        <div class="pc-dialog-body">
            <p id="pcConnectDialogCopy"></p>
            <div class="pc-onboarding-steps">
                <div class="pc-onboarding-step"><span class="pc-step-number">1</span><span>Continue securely to Stripe hosted onboarding.</span></div>
                <div class="pc-onboarding-step"><span class="pc-step-number">2</span><span>Confirm the business and payout details Stripe requires.</span></div>
                <div class="pc-onboarding-step"><span class="pc-step-number">3</span><span>Return here and verify account readiness.</span></div>
            </div>
            <div class="pc-dialog-callout">
                @include('user_view.payments.partials.icon', ['name' => 'shield-check', 'size' => 18])
                <span>No Stripe secret keys are entered here.</span>
            </div>
        </div>
        <div class="pc-dialog-footer">
            <button class="pc-secondary" type="button" data-pc-close="pcConnectDialog">Cancel</button>
            <button class="pc-primary" type="button" id="pcContinueToStripe">Continue to Stripe</button>
        </div>
    </dialog>

    <dialog class="pc-dialog" id="pcDisconnectDialog">
        <div class="pc-dialog-header">
            <h3>Disconnect Stripe account?</h3>
            <button class="pc-dialog-close" type="button" data-pc-close="pcDisconnectDialog" aria-label="Close">
                @include('user_view.payments.partials.icon', ['name' => 'x', 'size' => 16])
            </button>
        </div>
        <div class="pc-dialog-body">
            <p id="pcDisconnectDialogCopy"></p>
            <div class="pc-dialog-callout">
                @include('user_view.payments.partials.icon', ['name' => 'triangle-alert', 'size' => 18])
                <span>Existing orders stay unchanged. If this is the active mode, customers cannot pay until another ready mode is selected or this account is reconnected.</span>
            </div>
        </div>
        <div class="pc-dialog-footer">
            <button class="pc-secondary" type="button" data-pc-close="pcDisconnectDialog">Cancel</button>
            <button class="pc-danger" type="button" id="pcConfirmDisconnect">Disconnect</button>
        </div>
    </dialog>

    <dialog class="pc-dialog" id="pcInfoDialog">
        <div class="pc-dialog-header">
            <h3>Secure Stripe connection</h3>
            <button class="pc-dialog-close" type="button" data-pc-close="pcInfoDialog" aria-label="Close">
                @include('user_view.payments.partials.icon', ['name' => 'x', 'size' => 16])
            </button>
        </div>
        <div class="pc-dialog-body">
            <p>You will connect through Stripe hosted onboarding. No Stripe secret keys are entered here.</p>
            <p>The platform stores the connected-account reference and readiness status — not Stripe secret keys entered through this page. Test and live accounts stay separate and are scoped to the active store.</p>
            <p>A Stripe account already used in WooCommerce cannot be reused here — connect from this portal.</p>
        </div>
        <div class="pc-dialog-footer">
            <button class="pc-primary" type="button" data-pc-close="pcInfoDialog">Got it</button>
        </div>
    </dialog>
@endpush

@push('scripts')
    <script>
    (function () {
        var root = document.querySelector('[data-pc-root]');
        if (! root) return;

        var state = {
            active: root.getAttribute('data-pc-active') || 'test',
            selected: root.getAttribute('data-pc-selected') || 'test',
            canManage: root.getAttribute('data-pc-can-manage') === '1',
            pendingMode: null,
            pendingDisconnect: null,
            menuMode: null
        };
        var ready = {
            test: root.getAttribute('data-pc-test-ready') === '1',
            live: root.getAttribute('data-pc-live-ready') === '1'
        };
        var configured = {
            test: root.getAttribute('data-pc-test-configured') === '1',
            live: root.getAttribute('data-pc-live-configured') === '1'
        };

        function byId(id) { return document.getElementById(id); }
        function modeName(mode) { return mode === 'live' ? 'Live' : 'Test'; }
        function formFor(prefix, mode) { return byId(prefix + '-' + mode) || byId(prefix + '-' + mode + '-form'); }

        function selectMode(mode) {
            if (mode !== 'test' && mode !== 'live') return;
            state.selected = mode;
            root.setAttribute('data-pc-selected', mode);
            root.querySelectorAll('[data-pc-select]').forEach(function (el) {
                var on = el.getAttribute('data-pc-select') === mode;
                el.classList.toggle('is-selected', on && el.classList.contains('pc-mode-row'));
                if (el.classList.contains('pc-mode-row')) {
                    el.setAttribute('aria-pressed', on ? 'true' : 'false');
                }
            });
            root.querySelectorAll('[data-pc-detail]').forEach(function (el) {
                el.hidden = el.getAttribute('data-pc-detail') !== mode;
            });
            try {
                var url = new URL(window.location.href);
                url.searchParams.set('mode', mode);
                window.history.replaceState({}, '', url);
            } catch (e) {}
            closeMenu();
        }

        function closeDialog(id) {
            var dialog = byId(id);
            if (dialog && dialog.open) dialog.close();
        }

        function closeMenu() {
            var menu = byId('pcAccountMenu');
            if (menu) menu.hidden = true;
        }

        function showMenu(button, mode) {
            var menu = byId('pcAccountMenu');
            if (! menu) return;
            state.menuMode = mode;
            menu.hidden = false;
            var rect = button.getBoundingClientRect();
            menu.style.left = Math.min(window.innerWidth - 220, Math.max(10, rect.right - 210)) + 'px';
            menu.style.top = Math.min(window.innerHeight - 120, rect.bottom + 6) + 'px';
        }

        function submitForm(form) {
            if (! form) return;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        }

        function requestModeSwitch(mode, event) {
            if (event) event.preventDefault();
            if (state.active === mode) {
                selectMode(mode);
                return;
            }
            if (! configured[mode]) {
                selectMode(mode);
                return;
            }
            if (! ready[mode] || ! state.canManage) {
                selectMode(mode);
                return;
            }
            state.pendingMode = mode;
            byId('pcModeDialogTitle').textContent = 'Switch to ' + modeName(mode) + ' mode?';
            byId('pcModeDialogBody').innerHTML = mode === 'live'
                ? '<p>Live mode will become the payment path for this store’s platform checkout.</p><div class="pc-dialog-callout"><span>Customers will be charged real money after this change.</span></div>'
                : '<p>Test mode will become the payment path for this store’s platform checkout.</p><div class="pc-dialog-callout"><span>Test payments do not charge real money.</span></div>';
            byId('pcConfirmModeButton').textContent = 'Switch to ' + modeName(mode);
            byId('pcModeDialog').showModal();
        }

        function confirmModeSwitch() {
            if (! state.pendingMode) return;
            var form = byId('pc-mode-' + state.pendingMode + '-form');
            closeDialog('pcModeDialog');
            submitForm(form);
        }

        function openConnect(mode, event) {
            if (event) event.preventDefault();
            if (! configured[mode] || ! state.canManage) return;
            selectMode(mode);
            byId('pcConnectDialogTitle').textContent = 'Connect Stripe ' + mode + ' account';
            byId('pcConnectDialogCopy').textContent = mode === 'live'
                ? 'Use a separate live Stripe account for real customer payments. You will connect through Stripe hosted onboarding.'
                : 'Use Stripe test mode to verify checkout safely. You will connect through Stripe hosted onboarding.';
            byId('pcContinueToStripe').setAttribute('data-mode', mode);
            byId('pcConnectDialog').showModal();
        }

        function continueToStripe() {
            var mode = byId('pcContinueToStripe').getAttribute('data-mode');
            closeDialog('pcConnectDialog');
            submitForm(byId('pc-connect-' + mode));
        }

        function requestDisconnect(mode) {
            var form = byId('pc-disconnect-' + mode);
            if (! form || ! state.canManage) return;
            state.pendingDisconnect = mode;
            var title = mode === 'live' ? 'Stripe live account' : 'Stripe test account';
            byId('pcDisconnectDialogCopy').textContent = 'Disable this ' + title.toLowerCase() + ' for ' + @json($storeName) + '? Existing orders stay unchanged.';
            byId('pcDisconnectDialog').showModal();
        }

        function confirmDisconnect() {
            var mode = state.pendingDisconnect;
            var form = byId('pc-disconnect-' + mode);
            if (! form) return;
            form.removeAttribute('data-ui-confirm');
            form.removeAttribute('data-ui-confirm-title');
            form.removeAttribute('data-ui-confirm-action');
            closeDialog('pcDisconnectDialog');
            submitForm(form);
        }

        document.querySelectorAll('[data-pc-select]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                if (el.tagName === 'BUTTON' && el.getAttribute('data-pc-switch')) return;
                event.preventDefault();
                selectMode(el.getAttribute('data-pc-select'));
            });
        });

        document.querySelectorAll('[data-pc-switch]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                requestModeSwitch(el.getAttribute('data-pc-switch'), event);
            });
        });

        document.querySelectorAll('[data-pc-connect]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                openConnect(el.getAttribute('data-pc-connect'), event);
            });
        });

        document.querySelectorAll('[data-pc-disconnect]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                event.preventDefault();
                requestDisconnect(el.getAttribute('data-pc-disconnect'));
            });
        });

        document.querySelectorAll('[data-pc-more]').forEach(function (el) {
            el.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                closeMenu();
                showMenu(el, el.getAttribute('data-pc-more'));
            });
        });

        var confirmMode = byId('pcConfirmModeButton');
        if (confirmMode) confirmMode.addEventListener('click', confirmModeSwitch);
        var continueBtn = byId('pcContinueToStripe');
        if (continueBtn) continueBtn.addEventListener('click', continueToStripe);
        var confirmDisconnectBtn = byId('pcConfirmDisconnect');
        if (confirmDisconnectBtn) confirmDisconnectBtn.addEventListener('click', confirmDisconnect);

        var menu = byId('pcAccountMenu');
        if (menu) {
            menu.addEventListener('click', function (event) {
                var action = event.target.closest('[data-pc-menu-action]');
                if (! action || ! state.menuMode) return;
                var mode = state.menuMode;
                var kind = action.getAttribute('data-pc-menu-action');
                closeMenu();
                if (kind === 'refresh') submitForm(byId('pc-refresh-' + mode));
                if (kind === 'disconnect') requestDisconnect(mode);
            });
        }

        document.querySelectorAll('[data-pc-close]').forEach(function (el) {
            el.addEventListener('click', function () { closeDialog(el.getAttribute('data-pc-close')); });
        });

        var security = document.querySelector('[data-pc-security]');
        if (security) {
            security.addEventListener('click', function () {
                var dialog = byId('pcInfoDialog');
                if (dialog && dialog.showModal) dialog.showModal();
            });
        }

        var diagnosticsToggle = document.querySelector('[data-pc-diagnostics-toggle]');
        var diagnosticsPanel = document.querySelector('[data-pc-diagnostics-panel]');
        if (diagnosticsToggle && diagnosticsPanel) {
            var key = 'payments-diagnostics-' + (root.getAttribute('data-pc-store') || '0');
            var open = window.MerchantUi ? MerchantUi.recallDisclosure(key, false) : false;
            diagnosticsPanel.hidden = ! open;
            diagnosticsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            var chevron = diagnosticsToggle.querySelector('.payments-diagnostics-chevron');
            if (chevron) chevron.classList.toggle('is-open', open);
            diagnosticsToggle.addEventListener('click', function () {
                open = diagnosticsPanel.hidden;
                diagnosticsPanel.hidden = ! open;
                diagnosticsToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
                if (chevron) chevron.classList.toggle('is-open', open);
                if (window.MerchantUi) MerchantUi.rememberDisclosure(key, open);
            });
        }

        document.addEventListener('click', function (event) {
            if (! event.target.closest('#pcAccountMenu') && ! event.target.closest('[data-pc-more]')) closeMenu();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') closeMenu();
        });
        window.addEventListener('resize', closeMenu);
        window.addEventListener('scroll', closeMenu, true);
    })();
    </script>
@endpush
