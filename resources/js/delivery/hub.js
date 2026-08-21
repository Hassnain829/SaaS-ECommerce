/**
 * Delivery hub drawers + postal/region builders.
 * Expects #shipping-page[data-zone-store-url][data-method-store-url].
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

        function applyHashTarget() {
            const hash = window.location.hash.replace(/^#/, '');
            if (! hash) {
                return;
            }
            const el = document.getElementById(hash);
            if (! el) {
                return;
            }
            if (hash === 'delivery-troubleshooting') {
                const details = el.querySelector('details');
                if (details) {
                    details.open = true;
                }
            }
            el.scrollIntoView({ behavior: 'smooth', block: 'start' });
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

        function renderRegionMulti(countryCode, selectedRegions) {
            const host = document.getElementById('zone-region-multi-host');
            if (! host) {
                return;
            }
            selectedRegions = selectedRegions || [];
            const regions = regionCatalog[countryCode] || {};
            const keys = Object.keys(regions);
            let html = '<div id="zone-region-multi" class="space-y-2" data-role="geo-region-multi" data-country="' + countryCode + '" data-name="region_codes">';
            html += '<div class="flex items-center justify-between gap-2"><span class="text-xs font-semibold text-[#64748B]">States / provinces (optional)</span>';
            if (keys.length) {
                html += '<button type="button" class="text-[11px] font-semibold text-[#1D4ED8] hover:underline" data-region-action="clear">Clear all</button>';
            }
            html += '</div><p class="text-[11px] text-[#94A3B8]">Leave empty to cover the entire country.</p>';
            if (! countryCode) {
                html += '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">Choose a country first to see states or provinces.</p>';
            } else if (! keys.length) {
                html += '<p class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">This country has no predefined regions. The entire country will be covered.</p>';
            } else {
                html += '<div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2">';
                keys.forEach((code) => {
                    const checked = selectedRegions.indexOf(code) !== -1 ? ' checked' : '';
                    html += '<label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-[#334155] hover:bg-white"><input type="checkbox" name="region_codes[]" value="' + code + '"' + checked + ' class="rounded border-[#CBD5E1]"><span>' + regions[code] + ' (' + code + ')</span></label>';
                });
                html += '</div>';
            }
            html += '</div>';
            host.innerHTML = html;
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
            renderRegionMulti('', []);
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
            renderRegionMulti(zoneData.country_code || '', zoneData.region_codes || []);
            renderPostalRules(document.getElementById('zone-postal-builder'), zoneData.postal_rules || []);
        }

        function resetMethodFormForAdd() {
            const form = document.getElementById('method-drawer-form');
            if (! form) {
                return;
            }
            form.action = methodStoreUrl;
            document.getElementById('method-drawer-title').textContent = 'Add delivery option';
            const method = document.getElementById('method-form-method');
            method.disabled = true;
            method.value = 'POST';
            form.reset();
            const available = document.getElementById('method-field-available');
            if (available) {
                available.checked = true;
            }
            const simpleAvailability = document.getElementById('method-simple-availability');
            if (simpleAvailability) {
                simpleAvailability.classList.remove('hidden');
            }
            const warning = document.getElementById('method-flag-warning');
            if (warning) {
                warning.classList.add('hidden');
                warning.textContent = '';
            }
            const advancedPanel = document.getElementById('method-advanced-panel');
            if (advancedPanel) {
                advancedPanel.open = false;
            }
            setMethodPriceMode('fixed');
            syncMethodFields();
        }

        function setMethodPriceMode(mode) {
            document.querySelectorAll('[data-method-price-mode]').forEach((radio) => {
                radio.checked = radio.value === mode;
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

        function openDrawer(id) {
            const drawer = document.getElementById('shipping-drawer-' + id);
            if (! drawer) {
                return;
            }
            drawer.classList.remove('hidden');
            drawer.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            const focusTarget = drawer.querySelector('input:not([type="hidden"]), select, textarea, button[data-close-drawer]');
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
        }

        function syncMethodFields() {
            const rateHidden = document.getElementById('method-field-rate-type-hidden');
            const advancedRate = document.getElementById('method-field-rate-type-advanced');
            const carrier = document.getElementById('method-field-carrier');
            const advancedPanel = document.getElementById('method-advanced-panel');
            const rt = (advancedRate && advancedPanel && advancedPanel.open && advancedRate.value)
                ? advancedRate.value
                : (rateHidden ? rateHidden.value : 'flat');
            const rateCarrierNote = document.getElementById('method-rate-carrier-note');
            if (rateCarrierNote) {
                rateCarrierNote.classList.toggle('hidden', rt !== 'carrier_calculated_later');
            }
            if (carrier) {
                const carrierNote = document.getElementById('method-carrier-note');
                if (carrierNote) {
                    carrierNote.classList.toggle('hidden', carrier.value !== '');
                }
            }
        }

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }
            const openDrawerEl = document.querySelector('.shipping-drawer:not(.hidden)');
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
                    }
                }
                openDrawer(key);
            });
        });

        document.querySelectorAll('[data-close-drawer]').forEach((el) => {
            el.addEventListener('click', closeDrawers);
        });

        document.querySelectorAll('.shipping-drawer').forEach((drawer) => {
            drawer.addEventListener('click', (event) => {
                if (event.target === drawer) {
                    closeDrawers();
                }
            });
        });

        document.querySelectorAll('.zone-edit-btn').forEach((btn) => {
            btn.addEventListener('click', () => {
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
                const advancedRate = document.getElementById('method-field-rate-type-advanced');
                if (advancedRate) {
                    advancedRate.value = btn.getAttribute('data-rate-type') || 'flat';
                }
                setMethodPriceMode(btn.getAttribute('data-price-mode') || 'fixed');

                const mismatch = btn.getAttribute('data-flag-mismatch') === '1';
                const active = btn.getAttribute('data-active') === '1';
                const checkout = btn.getAttribute('data-checkout') === '1';
                const available = document.getElementById('method-field-available');
                if (available) {
                    available.checked = mismatch ? false : (active && checkout);
                }
                const simpleAvailability = document.getElementById('method-simple-availability');
                if (simpleAvailability) {
                    simpleAvailability.classList.remove('hidden');
                }
                const warning = document.getElementById('method-flag-warning');
                if (warning) {
                    if (mismatch) {
                        warning.classList.remove('hidden');
                        warning.textContent = active
                            ? 'This option has mixed visibility settings. Turn on “Available at checkout” and save to show it to customers, or leave it off to hide it.'
                            : 'This option has mixed visibility settings. Turn on “Available at checkout” and save to show it to customers, or leave it off to hide it.';
                    } else {
                        warning.classList.add('hidden');
                        warning.textContent = '';
                    }
                }
                syncMethodFields();
                openDrawer('method');
            });
        });

        const countrySelect = document.getElementById('zone-field-country');
        if (countrySelect) {
            countrySelect.addEventListener('change', () => {
                renderRegionMulti(countrySelect.value || '', []);
            });
        }

        document.addEventListener('click', (event) => {
            if (event.target.matches('[data-region-action="clear"]')) {
                document.querySelectorAll('#zone-region-multi input[type="checkbox"]').forEach((box) => {
                    box.checked = false;
                });
            }
        });

        const zoneLegacyPanel = document.getElementById('zone-legacy-panel');
        if (zoneLegacyPanel) {
            zoneLegacyPanel.addEventListener('toggle', () => {
                setZoneEditorMode(zoneLegacyPanel.open ? 'legacy' : 'simple');
            });
        }

        bindPostalRuleBuilder(document.getElementById('zone-postal-builder'));

        document.querySelectorAll('[data-method-price-mode]').forEach((radio) => {
            radio.addEventListener('change', () => {
                setMethodPriceMode(radio.value);
                syncMethodFields();
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

        const rateAdvanced = document.getElementById('method-field-rate-type-advanced');
        const carrierEl = document.getElementById('method-field-carrier');
        if (rateAdvanced) {
            rateAdvanced.addEventListener('change', syncMethodFields);
        }
        if (carrierEl) {
            carrierEl.addEventListener('change', syncMethodFields);
        }

        const zoneForm = document.getElementById('zone-drawer-form');
        if (zoneForm) {
            zoneForm.addEventListener('submit', () => {
                syncPostalRulesJson(document.getElementById('zone-postal-builder'));
                const legacyPanel = document.getElementById('zone-legacy-panel');
                setZoneEditorMode(legacyPanel && legacyPanel.open ? 'legacy' : 'simple');
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
                const advancedPanel = document.getElementById('method-advanced-panel');
                const advancedRate = document.getElementById('method-field-rate-type-advanced');
                const rateHidden = document.getElementById('method-field-rate-type-hidden');
                if (advancedPanel && advancedPanel.open && advancedRate && rateHidden) {
                    rateHidden.value = advancedRate.value;
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

        applyHashTarget();
        syncMethodFields();
    };

    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
})();
