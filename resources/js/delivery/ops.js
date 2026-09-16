/**
 * Delivery operations chrome: route strip, health/troubleshooting drawers,
 * and FedEx next-step drawer. Zone/method/FedEx-service editors stay in hub.js.
 * Document-level listeners stay bound once so Turbo cache/restore still works.
 */
(function () {
    function shippingPage() {
        return document.getElementById('shipping-page');
    }

    function opsRoot() {
        return document.querySelector('.delivery-ops') || shippingPage();
    }

    function attr(name) {
        const root = opsRoot();
        const page = shippingPage();
        return (root && root.getAttribute(name)) || (page && page.getAttribute(name)) || '';
    }

    function closeOpenDrawers() {
        document.querySelectorAll('.shipping-drawer.is-open').forEach((drawer) => {
            drawer.classList.remove('is-open');
            drawer.classList.add('hidden');
            drawer.setAttribute('aria-hidden', 'true');
        });
        document.body.classList.remove('overflow-hidden');
    }

    function openNamedDrawer(id) {
        const drawer = document.getElementById('shipping-drawer-' + id);
        if (! drawer) {
            return false;
        }
        closeOpenDrawers();
        drawer.classList.remove('hidden');
        void drawer.offsetWidth;
        drawer.classList.add('is-open');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        const focusTarget = drawer.querySelector('input:not([type="hidden"]), select, textarea, a.dh-btn, button[data-close-drawer]');
        if (focusTarget && typeof focusTarget.focus === 'function') {
            focusTarget.focus();
        }
        return true;
    }

    function triggerExisting(selector) {
        const el = document.querySelector(selector);
        if (el) {
            el.click();
            return true;
        }
        return false;
    }

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, (char) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;',
        }[char]));
    }

    function setActiveStage(id) {
        const page = shippingPage();
        const region = document.getElementById('route-detail-region');
        const stages = page ? page.querySelectorAll('.stage[data-stage-id]') : [];
        stages.forEach((button) => {
            const on = Boolean(id) && button.getAttribute('data-stage-id') === id;
            button.setAttribute('aria-expanded', on ? 'true' : 'false');
        });
        if (! region) {
            return;
        }
        if (! id || ! page) {
            region.innerHTML = '';
            return;
        }
        const stage = page.querySelector('.stage[data-stage-id="' + id + '"]');
        if (! stage) {
            region.innerHTML = '';
            return;
        }
        const icon = stage.getAttribute('data-stage-icon') || 'pin';
        const label = stage.getAttribute('data-stage-label') || '';
        const value = stage.getAttribute('data-stage-value') || '';
        const detail = stage.getAttribute('data-stage-detail') || '';
        const actionLabel = stage.getAttribute('data-stage-action-label') || 'Open';
        const action = stage.getAttribute('data-stage-action') || '';
        const href = stage.getAttribute('data-stage-href') || '';
        const primaryAction = href
            ? '<a class="do-text-action" href="' + escapeHtml(href) + '">' + escapeHtml(actionLabel) + ' →</a>'
            : '<button class="do-text-action" type="button" data-delivery-action="' + escapeHtml(action) + '">' + escapeHtml(actionLabel) + ' →</button>';
        region.innerHTML =
            '<div class="detail-tray">' +
                '<span class="detail-icon"><svg class="do-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><use href="#do-i-' + escapeHtml(icon) + '"/></svg></span>' +
                '<div><strong>' + escapeHtml(label) + ' · ' + escapeHtml(value) + '</strong><p>' + escapeHtml(detail) + '</p></div>' +
                '<div class="tray-actions">' +
                    primaryAction +
                    '<button class="do-btn" type="button" data-delivery-action="close-detail">Close</button>' +
                '</div>' +
            '</div>';
    }

    function fillTestRegions(form, country) {
        let catalog = {};
        const catalogEl = document.getElementById('delivery-region-catalog');
        try {
            if (catalogEl) {
                catalog = JSON.parse(catalogEl.textContent || '{}');
            } else {
                catalog = JSON.parse(form.getAttribute('data-regions') || '{}');
            }
        } catch (error) {
            catalog = {};
        }
        const regions = catalog[String(country || '').toUpperCase()] || {};
        const host = form.querySelector('[data-test-region-host]');
        if (! host) {
            return;
        }
        const codes = Object.keys(regions);
        if (codes.length === 0) {
            host.innerHTML =
                '<label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">State / province (optional)</span>' +
                '<input name="region_code" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm uppercase"></label>';
            return;
        }
        host.innerHTML =
            '<label class="block space-y-1"><span class="text-xs font-semibold text-[#64748B]">State / province (optional)</span>' +
            '<select name="region_code" class="h-10 w-full rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm">' +
            '<option value="">Select a state / province</option>' +
            codes.map((code) => '<option value="' + escapeHtml(code) + '">' + escapeHtml(regions[code]) + ' (' + escapeHtml(code) + ')</option>').join('') +
            '</select></label>';
    }

    function renderTestCheckoutResult(result) {
        const packageReason = {
            missing_weight: 'missing weight',
            missing_dimensions: 'missing dimensions',
            missing_package_preset: 'no package preset or custom package',
            preset_incomplete: 'selected preset is incomplete',
            preset_not_found: 'package preset not found',
        };
        const parts = ['<div class="rounded-xl border border-[#E2E8F0] bg-white p-3 space-y-2">', '<p class="text-sm font-semibold text-[#0F172A]">Results</p>'];
        if (result.ship_from && result.ship_from.name) {
            parts.push('<p class="text-xs text-[#64748B]">Ship from: ' + escapeHtml(result.ship_from.name) + '</p>');
        }
        if (result.package && result.package.ready === false) {
            parts.push(
                '<p class="rounded-lg border border-[#FDE68A] bg-[#FFFBEB] px-3 py-2 text-xs text-[#92400E]">Package not ready for live FedEx quotes: ' +
                escapeHtml(packageReason[result.package.reason] || result.package.reason || 'provide a preset or custom package') +
                '.</p>'
            );
        }
        if (result.has_matching_area) {
            const names = (result.matched_areas || []).map((area) => area.name).filter(Boolean).join(', ') || 'None';
            parts.push('<p class="text-xs text-[#64748B]">Matched delivery area(s): ' + escapeHtml(names) + '</p>');
        } else {
            parts.push('<p class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-xs text-[#991B1B]">No active delivery area matches this address.</p>');
        }
        const options = result.options || [];
        if (options.length === 0) {
            parts.push('<p class="text-sm text-[#64748B]">No delivery options are configured for this store.</p>');
        } else {
            parts.push('<ul class="space-y-2">');
            options.forEach((option) => {
                const available = option.status === 'available';
                parts.push(
                    '<li class="rounded-lg border px-3 py-2 ' + (available ? 'border-[#BBF7D0] bg-[#F0FDF4]' : 'border-[#E2E8F0] bg-[#F8FAFC]') + '">' +
                    '<div class="flex items-start justify-between gap-2"><p class="text-sm font-semibold text-[#0F172A]">' + escapeHtml(option.name || '') + '</p>' +
                    (available && option.amount !== null && option.amount !== undefined
                        ? '<p class="text-sm font-semibold tabular-nums">' + escapeHtml(option.currency_code || '') + ' ' + Number(option.amount).toFixed(2) + '</p>'
                        : '') +
                    '</div><p class="mt-1 text-xs text-[#64748B]">' + escapeHtml(option.message || '') + '</p>' +
                    (option.estimated_label ? '<p class="mt-1 text-xs text-[#64748B]">' + escapeHtml(option.estimated_label) + '</p>' : '') +
                    '</li>'
                );
            });
            parts.push('</ul>');
        }
        parts.push('</div>');
        return parts.join('');
    }

    function handleActionClick(event) {
        const trigger = event.target.closest('[data-delivery-action]');
        if (! trigger) {
            return;
        }
        const page = shippingPage();
        if (! page) {
            return;
        }

        const action = trigger.getAttribute('data-delivery-action');
        if (! action) {
            return;
        }

        if (trigger.tagName === 'A' && trigger.getAttribute('href') && action === 'navigate') {
            return;
        }

        event.preventDefault();

        const setupMode = attr('data-setup-mode') === '1';
        if (setupMode && (action === 'flow-origin' || action === 'flow-area' || action === 'flow-checkout' || action === 'continue-setup')) {
            const href = trigger.getAttribute('data-stage-href') || attr('data-continue-setup-url');
            if (href) {
                window.location.href = href;
            }
            return;
        }

        if (action === 'preview-checkout' || action === 'test-checkout') {
            openNamedDrawer('test-checkout');
            return;
        }

        if (action === 'troubleshooting') {
            openNamedDrawer('troubleshooting');
            return;
        }

        if (action === 'toggle-stage') {
            const id = trigger.getAttribute('data-stage-id');
            const current = trigger.getAttribute('aria-expanded') === 'true';
            setActiveStage(current ? null : id);
            return;
        }

        if (action === 'close-detail') {
            setActiveStage(null);
            return;
        }

        if (action === 'view-health') {
            const problem = page.querySelector('.stage.is-blocked, .stage.is-warning');
            if (problem) {
                setActiveStage(problem.getAttribute('data-stage-id'));
            }
            openNamedDrawer('health');
            return;
        }

        if (action === 'flow-origin' || action === 'edit-origin') {
            const href = attr('data-locations-url');
            if (href) {
                window.location.href = href;
            }
            return;
        }

        if (action === 'flow-area') {
            if (! triggerExisting('.zone-edit-btn')) {
                triggerExisting('[data-open-drawer="zone-add"]');
            }
            return;
        }

        if (action === 'flow-checkout' || action === 'add-option') {
            const zoneId = trigger.getAttribute('data-zone-id');
            if (zoneId) {
                const scoped = document.querySelector('[data-open-drawer="method-add"][data-zone-id="' + zoneId + '"]');
                if (scoped) {
                    scoped.click();
                    return;
                }
            }
            triggerExisting('[data-open-drawer="method-add"]');
            return;
        }

        if (action === 'flow-fedex' || action === 'connect-fedex' || action === 'resume-fedex') {
            const status = attr('data-fedex-status');
            if (status === 'connected') {
                const href = attr('data-fedex-manage-url') || attr('data-fedex-href');
                if (href) {
                    window.location.href = href;
                }
                return;
            }
            if (status === 'disabled') {
                return;
            }
            if (! openNamedDrawer('fedex-connect')) {
                const href = attr('data-fedex-href');
                if (href) {
                    window.location.href = href;
                }
            }
            return;
        }

        if (action === 'manage-fedex') {
            const href = attr('data-fedex-manage-url') || attr('data-fedex-href');
            if (href) {
                window.location.href = href;
            }
            return;
        }

        if (action === 'manage-packages') {
            const href = attr('data-packages-url');
            if (href) {
                window.location.href = href;
            }
            return;
        }

        if (action === 'continue-setup') {
            const href = attr('data-continue-setup-url');
            if (href) {
                window.location.href = href;
            }
        }
    }

    function handleTestCountryChange(event) {
        const target = event.target;
        if (! (target instanceof Element) || target.id !== 'delivery-test-country') {
            return;
        }
        const form = document.getElementById('delivery-test-checkout-form');
        if (form) {
            fillTestRegions(form, target.value);
        }
    }

    async function handleTestCheckoutSubmit(event) {
        const form = event.target;
        if (! (form instanceof HTMLFormElement) || form.id !== 'delivery-test-checkout-form') {
            return;
        }
        event.preventDefault();
        const results = document.getElementById('delivery-test-checkout-results');
        if (! results) {
            return;
        }
        const submit = form.querySelector('button[type="submit"]');
        if (submit) {
            submit.disabled = true;
        }
        results.innerHTML = '<p class="text-sm text-[#64748B]">Checking live checkout options…</p>';
        try {
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const response = await fetch(form.getAttribute('action'), {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': token,
                },
                body: new FormData(form),
            });
            const payload = await response.json().catch(() => ({}));
            if (! response.ok) {
                const firstError = payload.message || Object.values(payload.errors || {})[0]?.[0] || 'Could not preview checkout for that address.';
                results.innerHTML = '<p class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-sm text-[#991B1B]">' + escapeHtml(firstError) + '</p>';
                return;
            }
            results.innerHTML = renderTestCheckoutResult(payload.result || {});
        } catch (error) {
            results.innerHTML = '<p class="rounded-lg border border-[#FECACA] bg-[#FEF2F2] px-3 py-2 text-sm text-[#991B1B]">Could not preview checkout for that address.</p>';
        } finally {
            if (submit) {
                submit.disabled = false;
            }
        }
    }

    const bindOnce = () => {
        if (window.__deliveryOpsDocBound) {
            return;
        }
        window.__deliveryOpsDocBound = true;
        document.addEventListener('click', handleActionClick);
        document.addEventListener('change', handleTestCountryChange);
        document.addEventListener('submit', handleTestCheckoutSubmit);
        document.addEventListener('turbo:before-cache', () => {
            closeOpenDrawers();
            const page = shippingPage();
            if (page) {
                delete page.dataset.deliveryOpsBound;
            }
        });
    };

    const boot = () => {
        const page = shippingPage();
        if (! page) {
            return;
        }
        bindOnce();
        if (page.dataset.deliveryOpsBound === '1') {
            return;
        }
        page.dataset.deliveryOpsBound = '1';

        const form = document.getElementById('delivery-test-checkout-form');
        const country = form ? form.querySelector('#delivery-test-country') : null;
        if (form && country && country.value) {
            fillTestRegions(form, country.value);
        }
    };

    bindOnce();
    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);
})();
