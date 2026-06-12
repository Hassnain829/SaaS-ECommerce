@extends('layouts.user.user-sidebar')

@section('title', 'Shipping & Delivery | BaaS Core')

@php
    $connectionStatusLabels = [
        'not_connected' => 'Not connected',
        'setup_required' => 'Setup required',
        'pending_validation' => 'Pending validation',
        'connected' => 'Connected',
        'failed' => 'Failed',
        'blocked_by_fedex' => 'Carrier support required',
        'sandbox_platform_fallback' => 'Connected for testing',
        'disabled' => 'Disabled',
    ];
    $connectionStatusBadge = fn (string $status) => match ($status) {
        'connected' => 'bg-[#ECFDF5] text-[#047857]',
        'sandbox_platform_fallback' => 'bg-[#FFF7ED] text-[#C2410C]',
        'blocked_by_fedex' => 'bg-[#FEF2F2] text-[#991B1B]',
        'failed' => 'bg-[#FEF2F2] text-[#991B1B]',
        'disabled' => 'bg-[#F1F5F9] text-[#64748B]',
        default => 'bg-[#FEF3C7] text-[#92400E]',
    };
    $rateLabels = [
        'flat' => 'Flat rate',
        'free' => 'Free',
        'manual' => 'Manual price',
        'carrier_calculated_later' => 'Carrier calculated later',
    ];
    $statusBadge = fn (bool $active) => $active ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#F1F5F9] text-[#64748B]';
    $connectedCarriersCount = $carrierAccounts->filter(fn ($a) => $a->isConnected() || ($a->isManualProvider() && $a->status === 'enabled'))->count();
    $activeZonesCount = $shippingZones->where('is_active', true)->count();
    $activeMethodsCount = $shippingMethods->where('is_active', true)->count();
@endphp

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <button id="sidebarToggle" onclick="openSidebar()" class="md:hidden h-10 w-10 rounded-lg border border-[#E2E8F0] bg-white text-[#475569] shadow-sm flex items-center justify-center shrink-0" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <div class="min-w-0">
            <h1 class="truncate text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Shipping &amp; Delivery</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Manage carrier accounts, delivery coverage, methods, and fulfillment origins.</p>
        </div>
    </header>
@endsection

@section('content')
    <div class="mx-auto max-w-[1200px] space-y-6" id="shipping-page">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if ($isExternalManaged ?? false)
            <section class="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-950">
                External storefront manages checkout shipping. Settings below apply to platform checkout and dashboard fulfillment.
            </section>
        @elseif ($isPlatformManaged ?? false)
            <section class="rounded-2xl border border-emerald-100 bg-emerald-50/70 px-5 py-4 text-sm text-emerald-950">
                Delivery methods can be shown during platform checkout.
            </section>
        @endif

        {{-- Enterprise header --}}
        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm md:p-6">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-2xl font-poppins font-semibold text-[#0F172A]">Shipping &amp; Delivery</h2>
                    <p class="mt-1 text-sm text-[#64748B]">Manage carrier accounts, delivery coverage, methods, and fulfillment origins.</p>
                </div>
                @if ($canManageShipping ?? false)
                    <div class="flex flex-wrap gap-2">
                        <button type="button" data-shipping-tab="methods" data-open-drawer="method-add" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Add delivery method</button>
                        <a href="{{ route('shipping.carriers.connect.index') }}" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Connect carrier</a>
                        <a href="{{ route('settings.locations.index') }}" class="inline-flex h-10 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Manage locations</a>
                    </div>
                @endif
            </div>
            <div class="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><p class="text-xs font-bold uppercase text-[#94A3B8]">Origins</p><p class="mt-1 text-xl font-semibold text-[#0F172A]">{{ $locations->count() }}</p></div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><p class="text-xs font-bold uppercase text-[#94A3B8]">Connected carriers</p><p class="mt-1 text-xl font-semibold text-[#0F172A]">{{ $connectedCarriersCount }}</p></div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><p class="text-xs font-bold uppercase text-[#94A3B8]">Active zones</p><p class="mt-1 text-xl font-semibold text-[#0F172A]">{{ $activeZonesCount }}</p></div>
                <div class="rounded-xl bg-[#F8FAFC] px-4 py-3"><p class="text-xs font-bold uppercase text-[#94A3B8]">Active methods</p><p class="mt-1 text-xl font-semibold text-[#0F172A]">{{ $activeMethodsCount }}</p></div>
            </div>
        </section>

        {{-- Tabs --}}
        <div class="rounded-2xl border border-[#CBD5E1] bg-white shadow-sm">
            <div class="flex flex-wrap gap-1 border-b border-[#F1F5F9] p-2" role="tablist">
                @foreach (['overview' => 'Overview', 'carriers' => 'Carriers', 'zones' => 'Zones', 'methods' => 'Methods', 'locations' => 'Locations'] as $tabId => $tabLabel)
                    <button type="button" role="tab" data-shipping-tab="{{ $tabId }}" class="shipping-tab-btn rounded-lg px-4 py-2 text-sm font-semibold text-[#64748B] hover:bg-[#F8FAFC]" aria-selected="false">{{ $tabLabel }}</button>
                @endforeach
            </div>
            <div class="p-5 md:p-6">
                <div class="shipping-tab-panel hidden" data-shipping-panel="overview">@include('user_view.shipping.tabs.overview')</div>
                <div class="shipping-tab-panel hidden" data-shipping-panel="carriers">@include('user_view.shipping.tabs.carriers')</div>
                <div class="shipping-tab-panel hidden" data-shipping-panel="zones">@include('user_view.shipping.tabs.zones')</div>
                <div class="shipping-tab-panel hidden" data-shipping-panel="methods">@include('user_view.shipping.tabs.methods')</div>
                <div class="shipping-tab-panel hidden" data-shipping-panel="locations">@include('user_view.shipping.tabs.locations')</div>
            </div>
        </div>
    </div>

    @if ($canManageShipping ?? false)
        @include('user_view.shipping.partials.drawers')
    @endif

    <script>
    (function () {
        var STORAGE_KEY = 'shipping_active_tab';
        var validTabs = ['overview', 'carriers', 'zones', 'methods', 'locations'];

        function resolveTab() {
            var params = new URLSearchParams(window.location.search);
            var fromQuery = params.get('tab');
            if (fromQuery && validTabs.indexOf(fromQuery) !== -1) return fromQuery;
            try {
                var stored = localStorage.getItem(STORAGE_KEY);
                if (stored && validTabs.indexOf(stored) !== -1) return stored;
            } catch (e) {}
            return 'overview';
        }

        function activateTab(tab) {
            if (validTabs.indexOf(tab) === -1) tab = 'overview';
            document.querySelectorAll('.shipping-tab-btn').forEach(function (btn) {
                var active = btn.getAttribute('data-shipping-tab') === tab;
                btn.setAttribute('aria-selected', active ? 'true' : 'false');
                btn.classList.toggle('bg-[#0052CC]', active);
                btn.classList.toggle('text-white', active);
                btn.classList.toggle('text-[#64748B]', !active);
            });
            document.querySelectorAll('.shipping-tab-panel').forEach(function (panel) {
                panel.classList.toggle('hidden', panel.getAttribute('data-shipping-panel') !== tab);
            });
            try { localStorage.setItem(STORAGE_KEY, tab); } catch (e) {}
            var url = new URL(window.location.href);
            url.searchParams.set('tab', tab);
            window.history.replaceState({}, '', url);
        }

        function drawerKey(raw) {
            if (!raw) return null;
            if (raw.indexOf('zone') === 0) return 'zone';
            if (raw.indexOf('method') === 0) return 'method';
            return raw;
        }

        function resetZoneFormForAdd() {
            var form = document.getElementById('zone-drawer-form');
            if (!form) return;
            form.action = '{{ route('settings.shipping.zones.store') }}';
            document.getElementById('zone-drawer-title').textContent = 'Add zone';
            var method = document.getElementById('zone-form-method');
            method.disabled = true;
            method.value = 'POST';
            form.reset();
            document.getElementById('zone-field-active').checked = true;
        }

        function resetMethodFormForAdd() {
            var form = document.getElementById('method-drawer-form');
            if (!form) return;
            form.action = '{{ route('settings.shipping.methods.store') }}';
            document.getElementById('method-drawer-title').textContent = 'Add delivery method';
            var method = document.getElementById('method-form-method');
            method.disabled = true;
            method.value = 'POST';
            form.reset();
            document.getElementById('method-field-checkout').checked = true;
            document.getElementById('method-field-active').checked = true;
            syncMethodFields();
        }

        function openDrawer(id) {
            var drawer = document.getElementById('shipping-drawer-' + id);
            if (drawer) { drawer.classList.remove('hidden'); document.body.style.overflow = 'hidden'; }
        }

        function closeDrawers() {
            document.querySelectorAll('.shipping-drawer').forEach(function (d) { d.classList.add('hidden'); });
            document.body.style.overflow = '';
        }

        document.querySelectorAll('[data-shipping-tab], [data-open-drawer]').forEach(function (el) {
            el.addEventListener('click', function () {
                var tab = el.getAttribute('data-shipping-tab');
                if (tab) activateTab(tab);
                var drawerRaw = el.getAttribute('data-open-drawer');
                if (!drawerRaw) return;
                var key = drawerKey(drawerRaw);
                if (drawerRaw.indexOf('-add') !== -1) {
                    if (key === 'zone') resetZoneFormForAdd();
                    if (key === 'method') resetMethodFormForAdd();
                }
                openDrawer(key);
            });
        });
        document.querySelectorAll('[data-close-drawer]').forEach(function (el) {
            el.addEventListener('click', closeDrawers);
        });

        document.querySelectorAll('.zone-edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('zone-drawer-form').action = btn.getAttribute('data-action');
                document.getElementById('zone-drawer-title').textContent = 'Edit zone';
                var zoneMethod = document.getElementById('zone-form-method');
                zoneMethod.disabled = false;
                zoneMethod.value = 'PATCH';
                document.getElementById('zone-field-name').value = btn.getAttribute('data-name') || '';
                document.getElementById('zone-field-countries').value = btn.getAttribute('data-countries') || '';
                document.getElementById('zone-field-regions').value = btn.getAttribute('data-regions') || '';
                document.getElementById('zone-field-postal').value = btn.getAttribute('data-postal') || '';
                document.getElementById('zone-field-sort').value = btn.getAttribute('data-sort') || '0';
                document.getElementById('zone-field-active').checked = btn.getAttribute('data-active') === '1';
                openDrawer('zone');
            });
        });

        document.querySelectorAll('.method-edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('method-drawer-form').action = btn.getAttribute('data-action');
                document.getElementById('method-drawer-title').textContent = 'Edit delivery method';
                var methodMethod = document.getElementById('method-form-method');
                methodMethod.disabled = false;
                methodMethod.value = 'PATCH';
                document.getElementById('method-field-name').value = btn.getAttribute('data-name') || '';
                document.getElementById('method-field-zone').value = btn.getAttribute('data-zone') || '';
                document.getElementById('method-field-carrier').value = btn.getAttribute('data-carrier') || '';
                document.getElementById('method-field-rate-type').value = btn.getAttribute('data-rate-type') || 'flat';
                document.getElementById('method-field-label').value = btn.getAttribute('data-label') || '';
                document.getElementById('method-field-flat').value = btn.getAttribute('data-flat') || '0';
                document.getElementById('method-field-free-over').value = btn.getAttribute('data-free-over') || '';
                document.getElementById('method-field-min-order').value = btn.getAttribute('data-min-order') || '';
                document.getElementById('method-field-max-order').value = btn.getAttribute('data-max-order') || '';
                document.getElementById('method-field-min-days').value = btn.getAttribute('data-min-days') || '';
                document.getElementById('method-field-max-days').value = btn.getAttribute('data-max-days') || '';
                document.getElementById('method-field-description').value = btn.getAttribute('data-description') || '';
                document.getElementById('method-field-sort').value = btn.getAttribute('data-sort') || '0';
                document.getElementById('method-field-checkout').checked = btn.getAttribute('data-checkout') === '1';
                document.getElementById('method-field-active').checked = btn.getAttribute('data-active') === '1';
                syncMethodFields();
                openDrawer('method');
            });
        });

        function syncMethodFields() {
            var rateType = document.getElementById('method-field-rate-type');
            var carrier = document.getElementById('method-field-carrier');
            if (!rateType) return;
            var rt = rateType.value;
            var flatFields = document.getElementById('method-flat-fields');
            var carrierNote = document.getElementById('method-carrier-note');
            var rateCarrierNote = document.getElementById('method-rate-carrier-note');
            if (flatFields) flatFields.classList.toggle('hidden', rt === 'free');
            if (rateCarrierNote) rateCarrierNote.classList.toggle('hidden', rt !== 'carrier_calculated_later');
            if (carrierNote && carrier) carrierNote.classList.toggle('hidden', carrier.value !== '');
        }
        var rateEl = document.getElementById('method-field-rate-type');
        var carrierEl = document.getElementById('method-field-carrier');
        if (rateEl) rateEl.addEventListener('change', syncMethodFields);
        if (carrierEl) carrierEl.addEventListener('change', syncMethodFields);

        document.querySelectorAll('.shipping-submit-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('.shipping-submit-btn');
                if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
            });
        });

        activateTab(resolveTab());
        syncMethodFields();
    })();
    </script>
@endsection
