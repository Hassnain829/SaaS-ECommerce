@extends('layouts.user.user-sidebar')

@section('title', 'Delivery | BaaS Core')

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
        'flat' => 'Fixed price',
        'free' => 'Free',
        'manual' => 'Manual price',
        'carrier_calculated_later' => 'Carrier calculated later',
    ];
    $statusBadge = fn (bool $active) => $active ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#F1F5F9] text-[#64748B]';
    $deliverySetup = $deliverySetup ?? [];
    $advancedTab = request('tab');
    $openAdvanced = in_array($advancedTab, ['advanced', 'providers', 'areas', 'options', 'ship-from', 'zones', 'methods', 'carriers', 'locations'], true);
@endphp

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[color:var(--color-border)] bg-white/95 px-4 backdrop-blur md:px-8">
        <button id="sidebarToggle" onclick="openSidebar()" class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-[color:var(--color-border)] bg-white text-[color:var(--color-ink-secondary)] shadow-sm md:hidden" aria-label="Open sidebar">
            <svg width="18" height="14" viewBox="0 0 20 14" fill="none"><path d="M0 14V12H20V14H0ZM0 8V6H20V8H0ZM0 2V0H20V2H0Z" fill="currentColor"/></svg>
        </button>
        <div class="min-w-0">
            <h1 class="truncate font-heading text-lg font-semibold text-[color:var(--color-ink)] md:text-xl">Delivery</h1>
            <p class="hidden text-xs text-[color:var(--color-ink-muted)] sm:block">Where orders ship from, where you deliver, and what customers see at checkout.</p>
        </div>
        <a href="{{ route('settings.taxes.index') }}" class="ui-btn ui-btn-secondary ml-auto hidden sm:inline-flex">
            Checkout &amp; tax
        </a>
    </header>
@endsection

@section('content')
    <div
        class="settings-workspace-wide settings-hub ui-page-enter"
        id="shipping-page"
        x-data="{
            advancedOpen: {{ $openAdvanced ? 'true' : 'false' }},
            storageKey: 'delivery-advanced-open-{{ (int) optional($selectedStore ?? $currentStore ?? null)->id }}',
            init() {
                if (! this.advancedOpen && localStorage.getItem(this.storageKey) === '1') {
                    this.advancedOpen = true;
                }
            },
            persist() {
                localStorage.setItem(this.storageKey, this.advancedOpen ? '1' : '0');
            }
        }"
    >
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @if ($isExternalManaged ?? false)
            <div class="settings-alert">External storefront manages checkout shipping. The settings below apply to platform checkout and dashboard fulfillment.</div>
        @elseif ($isPlatformManaged ?? false)
            <div class="settings-alert" style="border-color:#bbf7d0;background:#f0fdf4;color:#166534;">Delivery options can appear during platform checkout when setup is complete.</div>
        @endif

        @include('user_view.shipping.tabs.overview')

        <details
            id="delivery-advanced-panel"
            class="settings-hub-details"
            x-bind:open="advancedOpen"
            @toggle="advancedOpen = $event.target.open; persist()"
        >
            <summary>Advanced delivery &amp; carrier settings</summary>
            <div class="settings-hub-details-body">
                @include('user_view.shipping.tabs.advanced')
            </div>
        </details>

        @if (! ($deliverySetup['is_ready'] ?? false) && ($canManageShipping ?? false))
            @php
                $next = collect($deliverySetup['health_items'] ?? [])->first();
                $nextHref = $next['action_href'] ?? route('settings.delivery.setup.ship-from');
                $nextLabel = $next['action_label'] ?? 'Continue setup';
                $nextMessage = $next['message'] ?? 'Finish delivery setup so customers can see delivery options at checkout.';
            @endphp
            <x-ui.sticky-next :message="$nextMessage" :action-label="$nextLabel" :action-href="$nextHref" />
        @endif
    </div>

    @if ($canManageShipping ?? false)
        @include('user_view.shipping.partials.drawers')
    @endif

    <script>
    (function () {
        var legacyTabMap = {
            overview: 'setup',
            carriers: 'providers',
            zones: 'areas',
            methods: 'options',
            locations: 'ship-from'
        };

        function normalizeTab(tab) {
            if (!tab) return null;
            if (legacyTabMap[tab]) return legacyTabMap[tab];
            return tab;
        }

        function openAdvancedPanel() {
            var panel = document.getElementById('delivery-advanced-panel');
            if (panel) panel.open = true;
        }

        function openAdvancedSection(section) {
            openAdvancedPanel();
            var target = document.querySelector('[data-advanced-section="' + section + '"]');
            if (target) {
                var detailsParent = target.closest('details');
                if (detailsParent) detailsParent.open = true;
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }

        function resolveInitialSection() {
            var params = new URLSearchParams(window.location.search);
            var tab = normalizeTab(params.get('tab'));
            if (!tab || tab === 'setup') return;
            if (['providers', 'areas', 'options', 'ship-from', 'advanced'].indexOf(tab) !== -1) {
                openAdvancedSection(tab === 'advanced' ? 'ship-from' : tab);
            }
        }

        function drawerKey(raw) {
            if (!raw) return null;
            if (raw.indexOf('zone') === 0) return 'zone';
            if (raw.indexOf('method') === 0) return 'method';
            return raw;
        }

        var regionCatalog = {};
        try {
            var catalogEl = document.getElementById('delivery-region-catalog');
            if (catalogEl) regionCatalog = JSON.parse(catalogEl.textContent || '{}');
        } catch (e) {}

        function renderRegionMulti(countryCode, selectedRegions) {
            var host = document.getElementById('zone-region-multi-host');
            if (!host) return;
            selectedRegions = selectedRegions || [];
            var regions = regionCatalog[countryCode] || {};
            var keys = Object.keys(regions);
            var html = '<div id="zone-region-multi" class="space-y-2" data-role="geo-region-multi" data-country="' + countryCode + '" data-name="region_codes">';
            html += '<div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold text-[#64748B]">States / provinces (optional)</span>';
            if (keys.length) html += '<button type="button" class="text-[11px] font-semibold text-[#1D4ED8] hover:underline" data-region-action="clear">Clear all</button>';
            html += '</div><p class="text-[11px] text-[#94A3B8]">Leave empty to cover the entire country.</p>';
            if (!countryCode) {
                html += '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">Choose a country first to see states or provinces.</p>';
            } else if (!keys.length) {
                html += '<p class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">This country has no predefined regions. The entire country will be covered.</p>';
            } else {
                html += '<div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2">';
                keys.forEach(function (code) {
                    var checked = selectedRegions.indexOf(code) !== -1 ? ' checked' : '';
                    html += '<label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-[#334155] hover:bg-white"><input type="checkbox" name="region_codes[]" value="' + code + '"' + checked + ' class="rounded border-[#CBD5E1]"><span>' + regions[code] + ' (' + code + ')</span></label>';
                });
                html += '</div>';
            }
            html += '</div>';
            host.innerHTML = html;
        }

        function syncPostalRulesJson(container) {
            if (!container) return;
            var hidden = container.querySelector('input[type="hidden"]');
            if (!hidden) return;
            var rules = [];
            container.querySelectorAll('[data-postal-rule-row]').forEach(function (row) {
                var typeEl = row.querySelector('[data-postal-rule-type]');
                var valueEl = row.querySelector('[data-postal-rule-value]');
                var value = (valueEl && valueEl.value ? valueEl.value : '').replace(/\s+/g, '').toUpperCase();
                if (!value) return;
                rules.push({ type: typeEl ? typeEl.value : 'exact', value: value });
            });
            hidden.value = JSON.stringify(rules);
            var empty = container.querySelector('[data-postal-rules-empty]');
            if (empty) empty.classList.toggle('hidden', rules.length > 0);
        }

        function renderPostalRules(container, rules) {
            if (!container) return;
            var list = container.querySelector('[data-postal-rules-list]');
            if (!list) return;
            list.innerHTML = '';
            rules = rules || [];
            if (!rules.length) {
                list.innerHTML = '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-3 py-2 text-xs text-[#94A3B8]" data-postal-rules-empty>No postal rules — entire selected geography applies.</p>';
            } else {
                rules.forEach(function (rule) {
                    list.appendChild(createPostalRuleRow(rule.type || 'exact', rule.value || ''));
                });
            }
            syncPostalRulesJson(container);
        }

        function createPostalRuleRow(type, value) {
            var row = document.createElement('div');
            row.className = 'flex flex-wrap items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2';
            row.setAttribute('data-postal-rule-row', '1');
            row.innerHTML = '<select class="h-9 rounded-lg border border-[#CBD5E1] bg-white px-2 text-xs font-semibold text-[#475569]" data-postal-rule-type><option value="exact">Exact postal code</option><option value="prefix">Starts with</option></select><input type="text" placeholder="75002 or 606" class="h-9 min-w-[8rem] flex-1 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm uppercase" data-postal-rule-value><button type="button" class="rounded-lg border border-[#FECACA] bg-white px-2 py-1 text-xs font-semibold text-[#991B1B]" data-postal-rule-remove aria-label="Remove rule">Remove</button>';
            row.querySelector('[data-postal-rule-type]').value = type === 'prefix' ? 'prefix' : 'exact';
            row.querySelector('[data-postal-rule-value]').value = value || '';
            return row;
        }

        function bindPostalRuleBuilder(container) {
            if (!container || container.dataset.bound === '1') return;
            container.dataset.bound = '1';
            container.addEventListener('click', function (event) {
                var list = container.querySelector('[data-postal-rules-list]');
                if (event.target.matches('[data-postal-rule-add]')) {
                    var empty = container.querySelector('[data-postal-rules-empty]');
                    if (empty) empty.remove();
                    list.appendChild(createPostalRuleRow('exact', ''));
                }
                if (event.target.matches('[data-postal-rule-remove]')) {
                    event.target.closest('[data-postal-rule-row]').remove();
                    if (!list.querySelector('[data-postal-rule-row]')) {
                        list.innerHTML = '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-3 py-2 text-xs text-[#94A3B8]" data-postal-rules-empty>No postal rules — entire selected geography applies.</p>';
                    }
                    syncPostalRulesJson(container);
                }
            });
            container.addEventListener('input', function (event) {
                if (event.target.matches('[data-postal-rule-value]')) syncPostalRulesJson(container);
            });
            container.addEventListener('change', function (event) {
                if (event.target.matches('[data-postal-rule-type]')) syncPostalRulesJson(container);
            });
        }

        function setZoneEditorMode(mode) {
            var modeInput = document.getElementById('zone-editor-mode');
            var legacyPanel = document.getElementById('zone-legacy-panel');
            if (modeInput) modeInput.value = mode;
            if (legacyPanel) legacyPanel.open = mode === 'legacy';
        }

        function resetZoneFormForAdd() {
            var form = document.getElementById('zone-drawer-form');
            if (!form) return;
            form.action = '{{ route('settings.shipping.zones.store') }}';
            document.getElementById('zone-drawer-title').textContent = 'Add delivery area';
            var method = document.getElementById('zone-form-method');
            method.disabled = true;
            method.value = 'POST';
            form.reset();
            document.getElementById('zone-field-active').checked = true;
            setZoneEditorMode('simple');
            renderRegionMulti('', []);
            renderPostalRules(document.getElementById('zone-postal-builder'), []);
        }

        function populateZoneForm(zoneData) {
            document.getElementById('zone-field-name').value = zoneData.name || '';
            document.getElementById('zone-field-sort').value = zoneData.sort_order || 0;
            document.getElementById('zone-field-active').checked = !!zoneData.is_active;
            document.getElementById('zone-field-legacy-countries').value = zoneData.legacy_countries || '';
            document.getElementById('zone-field-legacy-regions').value = zoneData.legacy_regions || '';
            document.getElementById('zone-field-legacy-postal').value = zoneData.legacy_postal_patterns || '';
            var countrySelect = document.getElementById('zone-field-country');
            if (countrySelect) countrySelect.value = zoneData.country_code || '';
            setZoneEditorMode(zoneData.editor_mode === 'legacy' ? 'legacy' : 'simple');
            renderRegionMulti(zoneData.country_code || '', zoneData.region_codes || []);
            renderPostalRules(document.getElementById('zone-postal-builder'), zoneData.postal_rules || []);
        }

        function resetMethodFormForAdd() {
            var form = document.getElementById('method-drawer-form');
            if (!form) return;
            form.action = '{{ route('settings.shipping.methods.store') }}';
            document.getElementById('method-drawer-title').textContent = 'Add delivery option';
            var method = document.getElementById('method-form-method');
            method.disabled = true;
            method.value = 'POST';
            form.reset();
            document.getElementById('method-field-checkout').checked = true;
            document.getElementById('method-field-active').checked = true;
            document.getElementById('method-field-available').checked = true;
            document.getElementById('method-simple-availability').classList.remove('hidden');
            document.getElementById('method-flag-warning').classList.add('hidden');
            document.getElementById('method-advanced-panel').open = false;
            setMethodPriceMode('fixed');
            syncMethodFields();
        }

        function setMethodPriceMode(mode) {
            var radios = document.querySelectorAll('[data-method-price-mode]');
            radios.forEach(function (radio) { radio.checked = radio.value === mode; });
            var fixed = document.getElementById('method-price-fixed');
            var freeOver = document.getElementById('method-price-free-over');
            var rateHidden = document.getElementById('method-field-rate-type-hidden');
            if (fixed) fixed.classList.toggle('hidden', mode !== 'fixed');
            if (freeOver) freeOver.classList.toggle('hidden', mode !== 'free_over');
            if (rateHidden) rateHidden.value = mode === 'free' ? 'free' : 'flat';
            syncMethodFlatMirror();
        }

        function syncMethodFlatMirror() {
            var flat = document.getElementById('method-field-flat');
            var mirror = document.getElementById('method-field-flat-mirror');
            if (flat && mirror) mirror.value = flat.value;
        }

        function openDrawer(id) {
            var drawer = document.getElementById('shipping-drawer-' + id);
            if (!drawer) return;
            drawer.classList.remove('hidden');
            drawer.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
            document.body.style.overflow = 'hidden';
            var focusTarget = drawer.querySelector('input:not([type="hidden"]), select, textarea, button[data-close-drawer]');
            if (focusTarget) focusTarget.focus();
        }

        function closeDrawers() {
            document.querySelectorAll('.shipping-drawer').forEach(function (d) {
                d.classList.remove('is-open');
                d.classList.add('hidden');
                d.setAttribute('aria-hidden', 'true');
            });
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function (event) {
            if (event.key !== 'Escape') return;
            var openDrawerEl = document.querySelector('.shipping-drawer:not(.hidden)');
            if (openDrawerEl) {
                event.preventDefault();
                closeDrawers();
            }
        });

        document.querySelectorAll('[data-shipping-tab], [data-open-drawer]').forEach(function (el) {
            el.addEventListener('click', function () {
                var tab = el.getAttribute('data-shipping-tab');
                if (tab) {
                    openAdvancedSection(tab);
                }
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
                document.getElementById('zone-drawer-title').textContent = 'Edit delivery area';
                var zoneMethod = document.getElementById('zone-form-method');
                zoneMethod.disabled = false;
                zoneMethod.value = 'PATCH';
                var zoneData = {};
                try { zoneData = JSON.parse(btn.getAttribute('data-zone-form') || '{}'); } catch (e) {}
                populateZoneForm(zoneData);
                openDrawer('zone');
            });
        });

        document.querySelectorAll('.method-edit-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.getElementById('method-drawer-form').action = btn.getAttribute('data-action');
                document.getElementById('method-drawer-title').textContent = 'Edit delivery option';
                var methodMethod = document.getElementById('method-form-method');
                methodMethod.disabled = false;
                methodMethod.value = 'PATCH';
                document.getElementById('method-field-name').value = btn.getAttribute('data-name') || '';
                document.getElementById('method-field-zone').value = btn.getAttribute('data-zone') || '';
                document.getElementById('method-field-carrier').value = btn.getAttribute('data-carrier') || '';
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
                var advancedRate = document.getElementById('method-field-rate-type-advanced');
                if (advancedRate) advancedRate.value = btn.getAttribute('data-rate-type') || 'flat';
                setMethodPriceMode(btn.getAttribute('data-price-mode') || 'fixed');
                var mismatch = btn.getAttribute('data-flag-mismatch') === '1';
                var warning = document.getElementById('method-flag-warning');
                var simpleAvailability = document.getElementById('method-simple-availability');
                var advancedPanel = document.getElementById('method-advanced-panel');
                if (simpleAvailability) simpleAvailability.classList.add('hidden');
                if (warning) {
                    if (mismatch) {
                        warning.classList.remove('hidden');
                        warning.textContent = btn.getAttribute('data-active') === '1'
                            ? 'This option is active but hidden from checkout. Use advanced settings below to fix visibility.'
                            : 'This option is shown at checkout but currently inactive. Use advanced settings below to fix visibility.';
                        if (advancedPanel) advancedPanel.open = true;
                    } else {
                        warning.classList.add('hidden');
                        warning.textContent = '';
                    }
                }
                syncMethodFields();
                openDrawer('method');
            });
        });

        function syncMethodFields() {
            var rateHidden = document.getElementById('method-field-rate-type-hidden');
            var advancedRate = document.getElementById('method-field-rate-type-advanced');
            var carrier = document.getElementById('method-field-carrier');
            var rt = (advancedRate && document.getElementById('method-advanced-panel') && document.getElementById('method-advanced-panel').open && advancedRate.value)
                ? advancedRate.value
                : (rateHidden ? rateHidden.value : 'flat');
            var rateCarrierNote = document.getElementById('method-rate-carrier-note');
            if (rateCarrierNote) rateCarrierNote.classList.toggle('hidden', rt !== 'carrier_calculated_later');
            if (carrier) {
                var carrierNote = document.getElementById('method-carrier-note');
                if (carrierNote) carrierNote.classList.toggle('hidden', carrier.value !== '');
            }
        }

        var countrySelect = document.getElementById('zone-field-country');
        if (countrySelect) {
            countrySelect.addEventListener('change', function () {
                renderRegionMulti(countrySelect.value || '', []);
            });
        }

        document.addEventListener('click', function (event) {
            if (event.target.matches('[data-region-action="clear"]')) {
                document.querySelectorAll('#zone-region-multi input[type="checkbox"]').forEach(function (box) { box.checked = false; });
            }
        });

        var zoneLegacyPanel = document.getElementById('zone-legacy-panel');
        if (zoneLegacyPanel) {
            zoneLegacyPanel.addEventListener('toggle', function () {
                setZoneEditorMode(zoneLegacyPanel.open ? 'legacy' : 'simple');
            });
        }

        bindPostalRuleBuilder(document.getElementById('zone-postal-builder'));

        document.querySelectorAll('[data-method-price-mode]').forEach(function (radio) {
            radio.addEventListener('change', function () { setMethodPriceMode(radio.value); syncMethodFields(); });
        });

        var flatInput = document.getElementById('method-field-flat');
        var flatMirror = document.getElementById('method-field-flat-mirror');
        if (flatInput) flatInput.addEventListener('input', syncMethodFlatMirror);
        if (flatMirror) flatMirror.addEventListener('input', function () {
            if (flatInput) flatInput.value = flatMirror.value;
        });

        var rateAdvanced = document.getElementById('method-field-rate-type-advanced');
        var carrierEl = document.getElementById('method-field-carrier');
        if (rateAdvanced) rateAdvanced.addEventListener('change', syncMethodFields);
        if (carrierEl) carrierEl.addEventListener('change', syncMethodFields);

        var zoneForm = document.getElementById('zone-drawer-form');
        if (zoneForm) {
            zoneForm.addEventListener('submit', function () {
                syncPostalRulesJson(document.getElementById('zone-postal-builder'));
                var legacyPanel = document.getElementById('zone-legacy-panel');
                setZoneEditorMode(legacyPanel && legacyPanel.open ? 'legacy' : 'simple');
            });
        }

        var methodForm = document.getElementById('method-drawer-form');
        if (methodForm) {
            methodForm.addEventListener('submit', function () {
                var mode = document.querySelector('[data-method-price-mode]:checked');
                var priceMode = mode ? mode.value : 'fixed';
                if (priceMode === 'free_over') {
                    var mirror = document.getElementById('method-field-flat-mirror');
                    var flat = document.getElementById('method-field-flat');
                    if (mirror && flat) flat.value = mirror.value;
                }
                var advancedPanel = document.getElementById('method-advanced-panel');
                var advancedRate = document.getElementById('method-field-rate-type-advanced');
                var rateHidden = document.getElementById('method-field-rate-type-hidden');
                if (advancedPanel && advancedPanel.open && advancedRate && rateHidden) {
                    rateHidden.value = advancedRate.value;
                }
            });
        }

        document.querySelectorAll('.shipping-submit-form').forEach(function (form) {
            form.addEventListener('submit', function () {
                var btn = form.querySelector('.shipping-submit-btn');
                if (btn) { btn.disabled = true; btn.textContent = 'Saving…'; }
            });
        });

        resolveInitialSection();
        bindPostalRuleBuilder(document.getElementById('zone-postal-builder'));
        syncMethodFields();
    })();
    </script>
@endsection
