/**
 * Delivery hub: side drawers, availability toggles, area/option editors.
 */
(function () {
    const boot = () => {
        const page = document.getElementById('shipping-page');
        if (! page || page.dataset.deliveryHubBound === '1') {
            return;
        }
        page.dataset.deliveryHubBound = '1';

        const zoneStoreUrl = page.getAttribute('data-zone-store-url') || '';
        const methodStoreUrl = page.getAttribute('data-method-store-url') || '';
        let lastFocusEl = null;
        let csrfToken = '';
        const csrfMeta = document.querySelector('meta[name="csrf-token"]');
        if (csrfMeta) {
            csrfToken = csrfMeta.getAttribute('content') || '';
        }

        function drawerKey(raw) {
            if (! raw) {
                return null;
            }
            if (raw.indexOf('zone') === 0) {
                return 'zone';
            }
            if (raw.indexOf('method') === 0) {
                return 'method';
            }
            if (raw.indexOf('fedex') === 0) {
                return 'fedex-services';
            }
            return raw;
        }

        let regionCatalog = {};
        try {
            const catalogEl = document.getElementById('delivery-region-catalog');
            if (catalogEl) {
                regionCatalog = JSON.parse(catalogEl.textContent || '{}');
            }
        } catch (e) {
            regionCatalog = {};
        }

        let fedExCatalog = [];
        try {
            const fedExEl = document.getElementById('fedex-services-catalog');
            if (fedExEl) {
                fedExCatalog = JSON.parse(fedExEl.textContent || '[]');
            }
        } catch (e) {
            fedExCatalog = [];
        }

        function renderRegionMulti(countryCode, selectedRegions) {
            const host = document.getElementById('zone-region-multi-host');
            if (! host) {
                return;
            }
            selectedRegions = selectedRegions || [];
            const regions = regionCatalog[countryCode] || {};
            const keys = Object.keys(regions);
            let html = '<div id="zone-region-multi" class="space-y-2" data-role="geo-region-multi" data-country="' + countryCode + '" data-name="region_codes">';
            html += '<div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold text-[#64748B]">States / provinces</span>';
            if (keys.length) {
                html += '<button type="button" class="text-[11px] font-semibold text-[#1D4ED8] hover:underline" data-region-action="clear">Clear all</button>';
            }
            html += '</div>';
            if (! countryCode) {
                html += '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">Choose a country first.</p>';
            } else if (! keys.length) {
                html += '<p class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">This country has no predefined regions.</p>';
            } else {
                html += '<div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2">';
                keys.forEach((code) => {
                    const checked = selectedRegions.indexOf(code) !== -1 ? ' checked' : '';
                    html += '<label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-[#334155] hover:bg-white"><input type="checkbox" name="region_codes[]" value="' + code + '"' + checked + ' class="rounded border-[#CBD5E1]"><span>' + regions[code] + '</span></label>';
                });
                html += '</div>';
            }
            html += '</div>';
            host.innerHTML = html;
        }

        function setEntireCountry(on, selectedRegions) {
            const toggle = document.getElementById('zone-entire-country');
            const host = document.getElementById('zone-region-multi-host');
            const countrySelect = document.getElementById('zone-field-country');
            if (toggle) {
                toggle.classList.toggle('is-on', on);
                toggle.setAttribute('aria-pressed', on ? 'true' : 'false');
            }
            if (host) {
                host.classList.toggle('hidden', on);
            }
            if (on) {
                renderRegionMulti(countrySelect ? countrySelect.value : '', []);
            } else {
                renderRegionMulti(countrySelect ? countrySelect.value : '', selectedRegions || []);
            }
        }

        function syncPostalRulesJson(container) {
            if (! container) {
                return;
            }
            const hidden = container.querySelector('input[type="hidden"]');
            if (! hidden) {
                return;
            }
            const rules = [];
            container.querySelectorAll('[data-postal-rule-row]').forEach((row) => {
                const typeEl = row.querySelector('[data-postal-rule-type]');
                const valueEl = row.querySelector('[data-postal-rule-value]');
                const value = (valueEl && valueEl.value ? valueEl.value : '').replace(/\s+/g, '').toUpperCase();
                if (! value) {
                    return;
                }
                rules.push({ type: typeEl ? typeEl.value : 'exact', value: value });
            });
            hidden.value = JSON.stringify(rules);
            const empty = container.querySelector('[data-postal-rules-empty]');
            if (empty) {
                empty.classList.toggle('hidden', rules.length > 0);
            }
        }

        function createPostalRuleRow(type, value) {
            const row = document.createElement('div');
            row.className = 'flex flex-wrap items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2';
            row.setAttribute('data-postal-rule-row', '1');
            row.innerHTML = '<select class="h-9 rounded-lg border border-[#CBD5E1] bg-white px-2 text-xs font-semibold text-[#475569]" data-postal-rule-type><option value="exact">Exact postal code</option><option value="prefix">Starts with</option></select><input type="text" placeholder="75002 or 606" class="h-9 min-w-[8rem] flex-1 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm uppercase" data-postal-rule-value><button type="button" class="rounded-lg border border-[#FECACA] bg-white px-2 py-1 text-xs font-semibold text-[#991B1B]" data-postal-rule-remove aria-label="Remove rule">Remove</button>';
            row.querySelector('[data-postal-rule-type]').value = type === 'prefix' ? 'prefix' : 'exact';
            row.querySelector('[data-postal-rule-value]').value = value || '';
            return row;
        }

        function renderPostalRules(container, rules) {
            if (! container) {
                return;
            }
            const list = container.querySelector('[data-postal-rules-list]');
            if (! list) {
                return;
            }
            list.innerHTML = '';
            rules = rules || [];
            if (! rules.length) {
                list.innerHTML = '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-3 py-2 text-xs text-[#94A3B8]" data-postal-rules-empty>No postal rules — entire selected geography applies.</p>';
            } else {
                rules.forEach((rule) => {
                    list.appendChild(createPostalRuleRow(rule.type || 'exact', rule.value || ''));
                });
            }
            syncPostalRulesJson(container);
        }

        function bindPostalRuleBuilder(container) {
            if (! container || container.dataset.bound === '1') {
                return;
            }
            container.dataset.bound = '1';
            container.addEventListener('click', (event) => {
                const list = container.querySelector('[data-postal-rules-list]');
                if (event.target.matches('[data-postal-rule-add]')) {
                    const empty = container.querySelector('[data-postal-rules-empty]');
                    if (empty) {
                        empty.remove();
                    }
                    list.appendChild(createPostalRuleRow('exact', ''));
                }
                if (event.target.matches('[data-postal-rule-remove]')) {
                    event.target.closest('[data-postal-rule-row]').remove();
                    if (! list.querySelector('[data-postal-rule-row]')) {
                        list.innerHTML = '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-3 py-2 text-xs text-[#94A3B8]" data-postal-rules-empty>No postal rules — entire selected geography applies.</p>';
                    }
                    syncPostalRulesJson(container);
                }
            });
            container.addEventListener('input', (event) => {
                if (event.target.matches('[data-postal-rule-value]')) {
                    syncPostalRulesJson(container);
                }
            });
            container.addEventListener('change', (event) => {
                if (event.target.matches('[data-postal-rule-type]')) {
                    syncPostalRulesJson(container);
                }
            });
        }

        function setZoneEditorMode(mode) {
            const modeInput = document.getElementById('zone-editor-mode');
            if (modeInput) {
                modeInput.value = mode;
            }
        }

        function syncCheckboxSwitch(checkbox, switchBtn) {
            if (! checkbox || ! switchBtn) {
                return;
            }
            switchBtn.classList.toggle('is-on', checkbox.checked);
            switchBtn.setAttribute('aria-pressed', checkbox.checked ? 'true' : 'false');
        }

        function bindCheckboxSwitch(checkboxId, switchId) {
            const checkbox = document.getElementById(checkboxId);
            const switchBtn = document.getElementById(switchId);
            if (! checkbox || ! switchBtn || switchBtn.dataset.bound === '1') {
                return;
            }
            switchBtn.dataset.bound = '1';
            switchBtn.addEventListener('click', () => {
                checkbox.checked = ! checkbox.checked;
                syncCheckboxSwitch(checkbox, switchBtn);
            });
            syncCheckboxSwitch(checkbox, switchBtn);
        }

        function resetZoneFormForAdd() {
            const form = document.getElementById('zone-drawer-form');
            if (! form) {
                return;
            }
            form.action = zoneStoreUrl;
            document.getElementById('zone-drawer-title').textContent = 'Add delivery area';
            const method = document.getElementById('zone-form-method');
            method.disabled = true;
            method.value = 'POST';
            form.reset();
            document.getElementById('zone-field-active').checked = true;
            setZoneEditorMode('simple');
            setEntireCountry(true, []);
            renderPostalRules(document.getElementById('zone-postal-builder'), []);
        }

        function populateZoneForm(zoneData) {
            document.getElementById('zone-field-name').value = zoneData.name || '';
            document.getElementById('zone-field-sort').value = zoneData.sort_order || 0;
            document.getElementById('zone-field-active').checked = !! zoneData.is_active;
            document.getElementById('zone-field-legacy-countries').value = zoneData.legacy_countries || '';
            document.getElementById('zone-field-legacy-regions').value = zoneData.legacy_regions || '';
            document.getElementById('zone-field-legacy-postal').value = zoneData.legacy_postal_patterns || '';
            const countrySelect = document.getElementById('zone-field-country');
            if (countrySelect) {
                countrySelect.value = zoneData.country_code || '';
            }
            setZoneEditorMode(zoneData.editor_mode === 'legacy' ? 'legacy' : 'simple');
            const regions = zoneData.region_codes || [];
            setEntireCountry(regions.length === 0, regions);
            renderPostalRules(document.getElementById('zone-postal-builder'), zoneData.postal_rules || []);
        }

        function setMethodPriceMode(mode) {
            document.querySelectorAll('[data-method-price-mode]').forEach((radio) => {
                radio.checked = radio.value === mode;
                const card = radio.closest('.dh-pricecard');
                if (card) {
                    card.classList.toggle('is-selected', radio.checked);
                }
            });
            const fixed = document.getElementById('method-price-fixed');
            const freeOver = document.getElementById('method-price-free-over');
            const rateHidden = document.getElementById('method-field-rate-type-hidden');
            if (fixed) {
                fixed.classList.toggle('hidden', mode !== 'fixed');
            }
            if (freeOver) {
                freeOver.classList.toggle('hidden', mode !== 'free_over');
            }
            if (rateHidden) {
                rateHidden.value = mode === 'free' ? 'free' : 'flat';
            }
            syncMethodFlatMirror();
        }

        function syncMethodFlatMirror() {
            const flat = document.getElementById('method-field-flat');
            const mirror = document.getElementById('method-field-flat-mirror');
            if (flat && mirror) {
                mirror.value = flat.value;
            }
        }

        function resetMethodFormForAdd() {
            const form = document.getElementById('method-drawer-form');
            if (! form) {
                return;
            }
            form.action = methodStoreUrl;
            document.getElementById('method-drawer-title').textContent = 'Add delivery option';
            const lead = document.getElementById('method-drawer-lead');
            if (lead) {
                lead.textContent = 'Create a customer-facing checkout choice.';
            }
            const method = document.getElementById('method-form-method');
            method.disabled = true;
            method.value = 'POST';
            form.reset();
            const available = document.getElementById('method-field-available');
            if (available) {
                available.checked = true;
            }
            bindCheckboxSwitch('method-field-available', 'method-available-switch');
            syncCheckboxSwitch(available, document.getElementById('method-available-switch'));
            const warning = document.getElementById('method-flag-warning');
            if (warning) {
                warning.classList.add('hidden');
                warning.textContent = '';
            }
            setMethodPriceMode('fixed');
        }

        function openDrawer(id) {
            const drawer = document.getElementById('shipping-drawer-' + id);
            if (! drawer) {
                return;
            }
            drawer.classList.remove('hidden');
            // force reflow so transform transition runs
            void drawer.offsetWidth;
            drawer.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            const focusTarget = drawer.querySelector('input:not([type="hidden"]):not(.sr-only), select, textarea, button[data-close-drawer]');
            if (focusTarget) {
                focusTarget.focus();
            }
        }

        function closeDrawers() {
            document.querySelectorAll('.shipping-drawer').forEach((d) => {
                d.classList.remove('is-open');
                d.classList.add('hidden');
                d.setAttribute('aria-hidden', 'true');
            });
            document.body.classList.remove('overflow-hidden');
            if (lastFocusEl && typeof lastFocusEl.focus === 'function') {
                lastFocusEl.focus();
            }
            lastFocusEl = null;
        }

        function populateFedExServicesDrawer(trigger) {
            const form = document.getElementById('fedex-services-drawer-form');
            if (! form) {
                return;
            }
            form.action = trigger.getAttribute('data-action') || '#';
            const zoneName = trigger.getAttribute('data-zone-name') || 'Delivery area';
            document.getElementById('fedex-services-drawer-title').textContent = 'FedEx live rates';
            document.getElementById('fedex-services-drawer-lead').textContent = zoneName;
            const available = trigger.getAttribute('data-available') === '1';
            const availableInput = document.getElementById('fedex-services-available');
            if (availableInput) {
                availableInput.checked = available;
            }
            bindCheckboxSwitch('fedex-services-available', 'fedex-services-available-switch');
            syncCheckboxSwitch(availableInput, document.getElementById('fedex-services-available-switch'));

            let selected = [];
            try {
                selected = JSON.parse(trigger.getAttribute('data-services') || '[]');
            } catch (e) {
                selected = [];
            }
            const list = document.getElementById('fedex-services-list');
            if (! list) {
                return;
            }
            list.innerHTML = '';
            (fedExCatalog || []).forEach((service) => {
                const code = service.code || '';
                const checked = selected.indexOf(code) !== -1 ? ' checked' : '';
                const row = document.createElement('div');
                row.className = 'dh-service-row';
                row.innerHTML = '<label><input type="checkbox" name="fedex_services[]" value="' + code + '"' + checked + ' class="mt-0.5 rounded border-[#CBD5E1]"><span><strong>' + (service.name || code) + '</strong><small>' + (service.description || '') + '</small></span></label>';
                list.appendChild(row);
            });
        }

        async function patchAvailability(url, available) {
            const response = await fetch(url, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: JSON.stringify({ available: available }),
            });
            if (! response.ok) {
                throw new Error('save_failed');
            }
            return response.json();
        }

        document.querySelectorAll('[data-availability-toggle]').forEach((btn) => {
            btn.addEventListener('click', async () => {
                if (btn.disabled) {
                    return;
                }
                const url = btn.getAttribute('data-toggle-url');
                if (! url) {
                    return;
                }
                const previous = btn.getAttribute('data-available') === '1';
                const next = ! previous;
                btn.disabled = true;
                btn.classList.toggle('is-on', next);
                btn.setAttribute('data-available', next ? '1' : '0');
                btn.setAttribute('aria-pressed', next ? 'true' : 'false');
                try {
                    await patchAvailability(url, next);
                } catch (e) {
                    btn.classList.toggle('is-on', previous);
                    btn.setAttribute('data-available', previous ? '1' : '0');
                    btn.setAttribute('aria-pressed', previous ? 'true' : 'false');
                    window.alert('Could not save that change. Please try again.');
                } finally {
                    btn.disabled = false;
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            const openDrawerEl = document.querySelector('.shipping-drawer.is-open');
            if (openDrawerEl) {
                event.preventDefault();
                closeDrawers();
            }
        });

        document.querySelectorAll('[data-open-drawer]').forEach((el) => {
            el.addEventListener('click', () => {
                const drawerRaw = el.getAttribute('data-open-drawer');
                if (! drawerRaw) {
                    return;
                }
                lastFocusEl = el;
                const key = drawerKey(drawerRaw);
                if (drawerRaw.indexOf('-add') !== -1) {
                    if (key === 'zone') {
                        resetZoneFormForAdd();
                    }
                    if (key === 'method') {
                        resetMethodFormForAdd();
                        const zoneId = el.getAttribute('data-zone-id');
                        const zoneSelect = document.getElementById('method-field-zone');
                        if (zoneId && zoneSelect) {
                            zoneSelect.value = zoneId;
                        }
                        const lead = document.getElementById('method-drawer-lead');
                        if (lead && zoneSelect && zoneSelect.selectedOptions[0]) {
                            lead.textContent = zoneSelect.selectedOptions[0].textContent + ' · Simple shipping';
                        }
                    }
                }
                if (key === 'fedex-services') {
                    populateFedExServicesDrawer(el);
                }
                openDrawer(key);
            });
        });

        document.querySelectorAll('[data-close-drawer]').forEach((el) => {
            el.addEventListener('click', closeDrawers);
        });

        document.querySelectorAll('.zone-edit-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                lastFocusEl = btn;
                document.getElementById('zone-drawer-form').action = btn.getAttribute('data-action');
                document.getElementById('zone-drawer-title').textContent = 'Edit delivery area';
                const zoneMethod = document.getElementById('zone-form-method');
                zoneMethod.disabled = false;
                zoneMethod.value = 'PATCH';
                let zoneData = {};
                try {
                    zoneData = JSON.parse(btn.getAttribute('data-zone-form') || '{}');
                } catch (e) {
                    zoneData = {};
                }
                populateZoneForm(zoneData);
                openDrawer('zone');
            });
        });

        document.querySelectorAll('.method-edit-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
                lastFocusEl = btn;
                const menu = btn.closest('details.dh-menu');
                if (menu) {
                    menu.open = false;
                }
                document.getElementById('method-drawer-form').action = btn.getAttribute('data-action');
                document.getElementById('method-drawer-title').textContent = 'Edit delivery option';
                const methodMethod = document.getElementById('method-form-method');
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
                setMethodPriceMode(btn.getAttribute('data-price-mode') || 'fixed');

                const mismatch = btn.getAttribute('data-flag-mismatch') === '1';
                const active = btn.getAttribute('data-active') === '1';
                const checkout = btn.getAttribute('data-checkout') === '1';
                const available = document.getElementById('method-field-available');
                if (available) {
                    available.checked = mismatch ? false : (active && checkout);
                }
                bindCheckboxSwitch('method-field-available', 'method-available-switch');
                syncCheckboxSwitch(available, document.getElementById('method-available-switch'));
                const warning = document.getElementById('method-flag-warning');
                if (warning) {
                    if (mismatch) {
                        warning.classList.remove('hidden');
                        warning.textContent = 'This option has mixed visibility settings. Turn on “Available at checkout” and save to show it, or leave it off to hide it.';
                    } else {
                        warning.classList.add('hidden');
                        warning.textContent = '';
                    }
                }
                openDrawer('method');
            });
        });

        const entireCountryBtn = document.getElementById('zone-entire-country');
        if (entireCountryBtn && entireCountryBtn.dataset.bound !== '1') {
            entireCountryBtn.dataset.bound = '1';
            entireCountryBtn.addEventListener('click', () => {
                const next = ! entireCountryBtn.classList.contains('is-on');
                setEntireCountry(next, []);
            });
        }

        const countrySelect = document.getElementById('zone-field-country');
        if (countrySelect) {
            countrySelect.addEventListener('change', () => {
                const entireOn = entireCountryBtn ? entireCountryBtn.classList.contains('is-on') : true;
                if (! entireOn) {
                    renderRegionMulti(countrySelect.value || '', []);
                }
            });
        }

        document.addEventListener('click', (event) => {
            if (event.target.matches('[data-region-action="clear"]')) {
                document.querySelectorAll('#zone-region-multi input[type="checkbox"]').forEach((box) => {
                    box.checked = false;
                });
            }
        });

        bindPostalRuleBuilder(document.getElementById('zone-postal-builder'));
        bindCheckboxSwitch('method-field-available', 'method-available-switch');
        bindCheckboxSwitch('fedex-services-available', 'fedex-services-available-switch');

        document.querySelectorAll('[data-method-price-mode]').forEach((radio) => {
            radio.addEventListener('change', () => {
                setMethodPriceMode(radio.value);
            });
        });

        const flatInput = document.getElementById('method-field-flat');
        const flatMirror = document.getElementById('method-field-flat-mirror');
        if (flatInput) {
            flatInput.addEventListener('input', syncMethodFlatMirror);
        }
        if (flatMirror) {
            flatMirror.addEventListener('input', () => {
                if (flatInput) {
                    flatInput.value = flatMirror.value;
                }
            });
        }

        const zoneForm = document.getElementById('zone-drawer-form');
        if (zoneForm) {
            zoneForm.addEventListener('submit', () => {
                syncPostalRulesJson(document.getElementById('zone-postal-builder'));
                const entireOn = entireCountryBtn ? entireCountryBtn.classList.contains('is-on') : true;
                if (entireOn) {
                    document.querySelectorAll('#zone-region-multi input[type="checkbox"]').forEach((box) => {
                        box.checked = false;
                    });
                }
                setZoneEditorMode('simple');
            });
        }

        const methodForm = document.getElementById('method-drawer-form');
        if (methodForm) {
            methodForm.addEventListener('submit', () => {
                const mode = document.querySelector('[data-method-price-mode]:checked');
                const priceMode = mode ? mode.value : 'fixed';
                if (priceMode === 'free_over') {
                    const mirror = document.getElementById('method-field-flat-mirror');
                    const flat = document.getElementById('method-field-flat');
                    if (mirror && flat) {
                        flat.value = mirror.value;
                    }
                }
            });
        }

        document.querySelectorAll('.shipping-submit-form').forEach((form) => {
            form.addEventListener('submit', () => {
                const btn = form.querySelector('.shipping-submit-btn');
                if (btn) {
                    btn.disabled = true;
                    btn.textContent = 'Saving…';
                }
            });
        });

        const hash = window.location.hash.replace(/^#/, '');
        if (hash === 'delivery-troubleshooting') {
            const el = document.getElementById(hash);
            if (el) {
                const details = el.querySelector('details');
                if (details) {
                    details.open = true;
                }
                el.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        }
    };

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
})();
