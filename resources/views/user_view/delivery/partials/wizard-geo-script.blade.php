<script>
(function () {
    var catalog = {};
    try {
        var el = document.getElementById('wizard-region-catalog');
        if (el) catalog = JSON.parse(el.textContent || '{}');
    } catch (e) {}

    function renderRegionMulti(hostId, countryCode, selected) {
        var host = document.getElementById(hostId);
        if (!host) return;
        selected = selected || [];
        var regions = catalog[countryCode] || {};
        var keys = Object.keys(regions);
        var html = '<div id="wizard-zone-regions" class="space-y-2" data-role="geo-region-multi"><span class="text-xs font-semibold text-[#64748B]">States / provinces (optional)</span><p class="text-[11px] text-[#94A3B8]">Leave empty to cover the entire country.</p>';
        if (!countryCode) {
            html += '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">Choose a country first.</p>';
        } else if (!keys.length) {
            html += '<p class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-xs text-[#64748B]">Entire country will be covered.</p>';
        } else {
            html += '<div class="max-h-40 space-y-1 overflow-y-auto rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2">';
            keys.forEach(function (code) {
                var checked = selected.indexOf(code) !== -1 ? ' checked' : '';
                html += '<label class="flex items-center gap-2 px-2 py-1.5 text-sm"><input type="checkbox" name="region_codes[]" value="' + code + '"' + checked + ' class="rounded border-[#CBD5E1]"><span>' + regions[code] + ' (' + code + ')</span></label>';
            });
            html += '</div>';
        }
        html += '</div>';
        host.innerHTML = html;
    }

    function bindPostalBuilder(container) {
        if (!container || container.dataset.bound === '1') return;
        container.dataset.bound = '1';
        container.addEventListener('click', function (event) {
            var list = container.querySelector('[data-postal-rules-list]');
            if (event.target.matches('[data-postal-rule-add]')) {
                var empty = container.querySelector('[data-postal-rules-empty]');
                if (empty) empty.remove();
                var row = document.createElement('div');
                row.className = 'flex flex-wrap items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2';
                row.setAttribute('data-postal-rule-row', '1');
                row.innerHTML = '<select class="h-9 rounded-lg border border-[#CBD5E1] bg-white px-2 text-xs font-semibold" data-postal-rule-type><option value="exact">Exact postal code</option><option value="prefix">Starts with</option></select><input type="text" class="h-9 min-w-[8rem] flex-1 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm uppercase" data-postal-rule-value><button type="button" class="rounded-lg border border-[#FECACA] bg-white px-2 py-1 text-xs font-semibold text-[#991B1B]" data-postal-rule-remove>Remove</button>';
                list.appendChild(row);
            }
            if (event.target.matches('[data-postal-rule-remove]')) {
                event.target.closest('[data-postal-rule-row]').remove();
                syncPostal(container);
            }
        });
        container.addEventListener('input', function () { syncPostal(container); });
        container.addEventListener('change', function () { syncPostal(container); });
    }

    function syncPostal(container) {
        var hidden = container.querySelector('input[type="hidden"]');
        if (!hidden) return;
        var rules = [];
        container.querySelectorAll('[data-postal-rule-row]').forEach(function (row) {
            var value = (row.querySelector('[data-postal-rule-value]')?.value || '').replace(/\s+/g, '').toUpperCase();
            if (!value) return;
            rules.push({ type: row.querySelector('[data-postal-rule-type]')?.value || 'exact', value: value });
        });
        hidden.value = JSON.stringify(rules);
    }

    var countrySelect = document.getElementById('wizard-zone-country');
    if (countrySelect) {
        countrySelect.addEventListener('change', function () {
            renderRegionMulti('wizard-zone-region-host', countrySelect.value, []);
        });
    }

    window.wizardRenderRegionMulti = renderRegionMulti;

    function hydratePostalRules(container, rules) {
        if (!container) return;
        var list = container.querySelector('[data-postal-rules-list]');
        if (!list) return;
        list.innerHTML = '';
        rules = rules || [];
        if (!rules.length) {
            list.innerHTML = '<p class="rounded-lg border border-dashed border-[#CBD5E1] bg-white px-3 py-2 text-xs text-[#94A3B8]" data-postal-rules-empty>No postal rules — entire selected geography applies.</p>';
        } else {
            rules.forEach(function (rule) {
                var row = document.createElement('div');
                row.className = 'flex flex-wrap items-center gap-2 rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] p-2';
                row.setAttribute('data-postal-rule-row', '1');
                var type = (rule.type === 'prefix' || rule.type === 'starts_with') ? 'prefix' : 'exact';
                row.innerHTML = '<select class="h-9 rounded-lg border border-[#CBD5E1] bg-white px-2 text-xs font-semibold" data-postal-rule-type><option value="exact"' + (type === 'exact' ? ' selected' : '') + '>Exact postal code</option><option value="prefix"' + (type === 'prefix' ? ' selected' : '') + '>Starts with</option></select><input type="text" value="' + (rule.value || '') + '" class="h-9 min-w-[8rem] flex-1 rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm uppercase" data-postal-rule-value><button type="button" class="rounded-lg border border-[#FECACA] bg-white px-2 py-1 text-xs font-semibold text-[#991B1B]" data-postal-rule-remove>Remove</button>';
                list.appendChild(row);
            });
        }
        syncPostal(container);
    }

    window.wizardHydratePostalRules = hydratePostalRules;

    bindPostalBuilder(document.getElementById('wizard-zone-postal-builder'));
    var postalForm = document.querySelector('form[action*="deliver-to"]');
    if (postalForm) {
        postalForm.addEventListener('submit', function () {
            syncPostal(document.getElementById('wizard-zone-postal-builder'));
        });
    }
})();
</script>
