import './bootstrap';
import * as Turbo from '@hotwired/turbo';
import Alpine from 'alpinejs';
import './dashboard-workspace.js';
import './team-workspace.js';
import { initCountryComboboxes, teardownCountryComboboxes } from './country-combobox.js';

window.Turbo = Turbo;
window.Alpine = Alpine;

window.paymentsConsole = (options = {}) => {
    const config = typeof options === 'object' && options !== null && ! Array.isArray(options)
        ? options
        : {
            storeId: typeof arguments[1] === 'number' ? arguments[1] : 0,
            canManage: Boolean(arguments[2]),
            liveReady: Boolean(arguments[3]),
        };

    const storeId = config.storeId || 0;
    const canManage = Boolean(config.canManage);
    const liveReady = Boolean(config.liveReady);

    return {
        stripePanel: 'test',
        canManage,
        liveReady,
        diagnosticsOpen: MerchantUi.recallDisclosure(`payments-diagnostics-${storeId}`, false),
        setStripePanel(mode) {
            if (mode === 'live' && ! this.liveReady && this.canManage) {
                this.stripePanel = 'live';
                return;
            }
            this.stripePanel = mode;
            if (! this.canManage) {
                return;
            }
            const form = this.$refs.paymentModeForm;
            const input = mode === 'live' ? this.$refs.modeLive : this.$refs.modeTest;
            if (! form || ! input || input.disabled) {
                return;
            }
            if (input.checked) {
                return;
            }
            input.checked = true;
            form.submit();
        },
        toggleDiagnostics() {
            this.diagnosticsOpen = ! this.diagnosticsOpen;
            MerchantUi.rememberDisclosure(`payments-diagnostics-${storeId}`, this.diagnosticsOpen);
        },
    };
};

window.MerchantUi = {
    rememberDisclosure(key, open) {
        try {
            localStorage.setItem(key, open ? '1' : '0');
        } catch (e) {
            // Ignore private-mode storage failures.
        }
    },
    recallDisclosure(key, fallback = false) {
        try {
            const value = localStorage.getItem(key);
            if (value === '1') {
                return true;
            }
            if (value === '0') {
                return false;
            }
        } catch (e) {
            // Ignore.
        }

        return fallback;
    },
};

let uiConfirmPending = {
    form: null,
    submitter: null,
    resolve: null,
};

const uiConfirmModalEl = () => document.getElementById('uiConfirmModal');

const hideUiConfirmModal = () => {
    const modal = uiConfirmModalEl();
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    const anotherAlertOpen = [...document.querySelectorAll('.ui-modal-shell--alert')].some((el) => ! el.classList.contains('hidden'));
    if (! anotherAlertOpen) {
        document.body.classList.remove('overflow-hidden');
    }
};

const paintUiConfirmModal = (options = {}) => {
    const modal = uiConfirmModalEl();
    if (! modal) {
        return false;
    }

    const tone = options.tone === 'warning' ? 'warning' : 'danger';
    const panel = modal.querySelector('[data-ui-confirm-panel]');
    const hero = modal.querySelector('[data-ui-confirm-hero]');
    const icon = modal.querySelector('[data-ui-confirm-icon]');
    const title = modal.querySelector('#uiConfirmTitle');
    const lead = modal.querySelector('#uiConfirmLead');
    const callout = modal.querySelector('[data-ui-confirm-callout]');
    const calloutLabel = modal.querySelector('[data-ui-confirm-callout-label]');
    const calloutBody = modal.querySelector('[data-ui-confirm-callout-body]');
    const cancelBtn = modal.querySelector('[data-ui-confirm-cancel]');
    const okBtn = modal.querySelector('[data-ui-confirm-ok]');

    if (panel) {
        panel.classList.toggle('border-[#FDE68A]', tone === 'warning');
        panel.classList.toggle('border-[#FECACA]', tone !== 'warning');
    }
    if (hero) {
        hero.className = tone === 'warning'
            ? 'bg-[radial-gradient(circle_at_top,_rgba(245,158,11,0.18),_transparent_60%)] px-6 pb-4 pt-6'
            : 'bg-[radial-gradient(circle_at_top,_rgba(220,38,38,0.18),_transparent_60%)] px-6 pb-4 pt-6';
    }
    if (icon) {
        icon.className = tone === 'warning'
            ? 'flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFFBEB] text-[#D97706] shadow-sm'
            : 'flex h-14 w-14 items-center justify-center rounded-2xl bg-[#FFF1F2] text-[#DC2626] shadow-sm';
    }
    if (title) {
        title.textContent = options.title || 'Please confirm';
    }
    if (lead) {
        lead.textContent = options.body || '';
        lead.classList.toggle('hidden', ! options.body);
    }
    const warningBody = options.warningBody || '';
    if (callout) {
        callout.className = tone === 'warning'
            ? 'rounded-2xl border border-[#FDE68A] bg-[#FFFBEB] px-4 py-4'
            : 'rounded-2xl border border-[#FEE2E2] bg-[#FFF7F7] px-4 py-4';
        callout.classList.toggle('hidden', warningBody === '');
    }
    if (calloutLabel) {
        calloutLabel.textContent = options.warningLabel || (tone === 'warning' ? 'Please check' : 'Warning');
        calloutLabel.className = tone === 'warning'
            ? 'text-xs font-semibold uppercase tracking-[0.08em] text-[#92400E]'
            : 'text-xs font-semibold uppercase tracking-[0.08em] text-[#B42318]';
    }
    if (calloutBody) {
        calloutBody.textContent = warningBody;
        calloutBody.className = tone === 'warning'
            ? 'mt-2 text-sm text-[#78350F]'
            : 'mt-2 text-sm text-[#7F1D1D]';
    }
    if (cancelBtn) {
        cancelBtn.textContent = options.cancelLabel || 'Cancel';
    }
    if (okBtn) {
        okBtn.textContent = options.confirmLabel || 'Confirm';
        okBtn.className = tone === 'warning'
            ? 'rounded-xl bg-brand px-5 py-3 text-sm font-bold text-white shadow-lg shadow-brand/20 transition hover:bg-brand-hover'
            : 'rounded-xl bg-[#DC2626] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#DC2626]/20 transition hover:bg-[#B91C1C]';
    }

    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    cancelBtn?.focus();

    return true;
};

const finishUiConfirm = (accepted) => {
    const form = uiConfirmPending.form;
    const submitter = uiConfirmPending.submitter;
    const resolve = uiConfirmPending.resolve;
    uiConfirmPending = { form: null, submitter: null, resolve: null };
    hideUiConfirmModal();

    if (accepted && form instanceof HTMLFormElement) {
        form.dataset.uiConfirmAccepted = '1';
        const useSubmitter = submitter instanceof HTMLElement && form.contains(submitter);
        if (typeof form.requestSubmit === 'function') {
            form.requestSubmit(useSubmitter ? submitter : undefined);
        } else {
            form.submit();
        }
        return;
    }

    if (typeof resolve === 'function') {
        resolve(Boolean(accepted));
    }
};

const confirmWithMerchantModal = (options = {}) => new Promise((resolve) => {
    if (uiConfirmPending.resolve) {
        uiConfirmPending.resolve(false);
    }
    uiConfirmPending = { form: null, submitter: null, resolve };
    if (! paintUiConfirmModal(options)) {
        resolve(window.confirm(options.body || options.title || 'Are you sure?'));
        uiConfirmPending = { form: null, submitter: null, resolve: null };
    }
});

const confirmOptionsFromForm = (form) => {
    const body = form.getAttribute('data-ui-confirm') || 'Are you sure?';
    const title = form.getAttribute('data-ui-confirm-title') || (body.includes('?') ? body.split('?')[0] + '?' : 'Please confirm');
    const toneAttr = form.getAttribute('data-ui-confirm-tone');
    const dangerHint = /delete|remove|disconnect|void|cancel|disable|permanently|erase/i.test(`${title} ${body}`);

    return {
        title,
        body,
        warningLabel: form.getAttribute('data-ui-confirm-warning-label') || '',
        warningBody: form.getAttribute('data-ui-confirm-warning') || '',
        cancelLabel: form.getAttribute('data-ui-confirm-cancel') || 'Cancel',
        confirmLabel: form.getAttribute('data-ui-confirm-action') || 'Confirm',
        tone: toneAttr === 'warning' || toneAttr === 'danger' ? toneAttr : (dangerHint ? 'danger' : 'warning'),
    };
};

let uiConfirmListenersBound = false;
const initUiConfirm = () => {
    if (uiConfirmListenersBound) {
        return;
    }
    uiConfirmListenersBound = true;

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (! (form instanceof HTMLFormElement) || ! form.hasAttribute('data-ui-confirm')) {
            return;
        }
        if (form.dataset.uiConfirmAccepted === '1') {
            delete form.dataset.uiConfirmAccepted;
            return;
        }
        event.preventDefault();
        event.stopPropagation();
        if (uiConfirmPending.resolve) {
            uiConfirmPending.resolve(false);
        }
        uiConfirmPending = { form, submitter: event.submitter instanceof HTMLElement ? event.submitter : null, resolve: null };
        if (! paintUiConfirmModal(confirmOptionsFromForm(form))) {
            if (window.confirm(form.getAttribute('data-ui-confirm') || 'Are you sure?')) {
                form.dataset.uiConfirmAccepted = '1';
                const submitter = event.submitter instanceof HTMLElement && form.contains(event.submitter)
                    ? event.submitter
                    : undefined;
                if (typeof form.requestSubmit === 'function') {
                    form.requestSubmit(submitter);
                } else {
                    form.submit();
                }
            }
            uiConfirmPending = { form: null, submitter: null, resolve: null };
        }
    }, true);

    document.addEventListener('click', (event) => {
        const target = event.target;
        if (! (target instanceof Element)) {
            return;
        }
        if (target.closest('[data-ui-confirm-cancel]')) {
            event.preventDefault();
            finishUiConfirm(false);
            return;
        }
        if (target.closest('[data-ui-confirm-ok]')) {
            event.preventDefault();
            finishUiConfirm(true);
            return;
        }
        const modal = uiConfirmModalEl();
        if (modal && event.target === modal) {
            finishUiConfirm(false);
        }
    });
};

window.MerchantUi.confirm = (options = {}) => confirmWithMerchantModal(options);

/**
 * Fixed overlays must live directly under <body>.
 *
 * The merchant page wrapper is animated and scrollable. Browsers treat an
 * animated ancestor as the containing block for fixed descendants, which can
 * clip a modal to the workspace and position it relative to the current page
 * scroll. Portaling every fixed merchant layer to <body> keeps it centered in
 * the viewport on every page, including nested product and catalog dialogs.
 */
const MERCHANT_LAYER_SELECTOR = [
    '.ui-modal-shell',
    '.ui-modal-overlay',
    '.ui-drawer-panel',
    '.shipping-drawer-modal',
    '.shipping-drawer',
    '.discounts-console-drawer',
    '.discounts-console-drawer-overlay',
].join(',');

const portalMerchantLayers = () => {
    const keepers = new Map();

    document.querySelectorAll(MERCHANT_LAYER_SELECTOR).forEach((layer) => {
        if (! layer.id) {
            return;
        }
        const prev = keepers.get(layer.id);
        if (! prev) {
            keepers.set(layer.id, layer);
            return;
        }
        const incoming = layer.parentElement !== document.body;
        const prevOnBody = prev.parentElement === document.body;
        if (incoming && prevOnBody) {
            prev.remove();
            keepers.set(layer.id, layer);
            return;
        }
        layer.remove();
    });

    document.querySelectorAll(MERCHANT_LAYER_SELECTOR).forEach((layer) => {
        if (layer.parentElement !== document.body) {
            document.body.appendChild(layer);
        }
        layer.dataset.uiPortalReady = 'true';
    });
};

const showMerchantLayer = (el) => {
    if (! el) {
        return;
    }
    if (el.classList.contains('ui-drawer-panel')) {
        el.classList.remove('hidden', 'translate-x-full');
        return;
    }
    if (el.classList.contains('discounts-console-drawer') || el.classList.contains('discounts-console-drawer-overlay')) {
        el.classList.remove('hidden');
        el.classList.add('is-open');
        if (el.classList.contains('discounts-console-drawer')) {
            el.setAttribute('aria-hidden', 'false');
        }
        return;
    }
    if (el.classList.contains('shipping-drawer') || el.classList.contains('shipping-drawer-modal')) {
        el.classList.remove('hidden');
        void el.offsetWidth;
        el.classList.add('is-open');
        el.setAttribute('aria-hidden', 'false');
        return;
    }
    el.classList.remove('hidden');
    if (el.classList.contains('ui-modal-shell')) {
        el.classList.add('flex');
    }
};

const closeMerchantLayer = (el) => {
    if (! el) {
        return;
    }
    if (el.tagName === 'DIALOG') {
        if (el.open) {
            try {
                el.close();
            } catch (e) {
                // Ignore dialogs that cannot close during snapshot.
            }
        }
        return;
    }
    if (el.classList.contains('ui-drawer-panel')) {
        el.classList.add('translate-x-full');
        el.classList.remove('hidden', 'is-open', 'flex');
        return;
    }
    if (el.classList.contains('discounts-console-drawer') || el.classList.contains('discounts-console-drawer-overlay')) {
        el.classList.remove('is-open', 'hidden');
        if (el.classList.contains('discounts-console-drawer')) {
            el.setAttribute('aria-hidden', 'true');
        }
        return;
    }
    if (el.classList.contains('shipping-drawer') || el.classList.contains('shipping-drawer-modal')) {
        el.classList.remove('is-open');
        el.classList.add('hidden');
        el.setAttribute('aria-hidden', 'true');
        return;
    }
    el.classList.add('hidden');
    el.classList.remove('flex', 'is-open');
};

const closeMerchantLayers = () => {
    document.querySelectorAll('dialog[open]').forEach((dialog) => closeMerchantLayer(dialog));
    document.querySelectorAll(MERCHANT_LAYER_SELECTOR).forEach((layer) => closeMerchantLayer(layer));
    document.body.classList.remove('overflow-hidden');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    if (sidebarOverlay) {
        sidebarOverlay.classList.add('hidden');
    }
};

window.showMerchantLayer = showMerchantLayer;
window.closeMerchantLayer = closeMerchantLayer;
window.closeMerchantLayers = closeMerchantLayers;

const closeAllMerchantProfileMenus = () => {
    document.querySelectorAll('[data-merchant-profile-dropdown]').forEach((menu) => {
        menu.classList.add('hidden');
    });
    document.querySelectorAll('[data-merchant-profile-toggle]').forEach((trigger) => {
        trigger.setAttribute('aria-expanded', 'false');
    });
};

const initMerchantProfileMenus = () => {
    document.querySelectorAll('[data-merchant-profile-menu]').forEach((wrapper) => {
        if (wrapper.dataset.bound === 'true') {
            return;
        }
        wrapper.dataset.bound = 'true';

        const trigger = wrapper.querySelector('[data-merchant-profile-toggle]');
        const menu = wrapper.querySelector('[data-merchant-profile-dropdown]');
        if (! trigger || ! menu) {
            return;
        }

        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            const isOpen = ! menu.classList.contains('hidden');
            closeAllMerchantProfileMenus();
            if (! isOpen) {
                menu.classList.remove('hidden');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    });
};

const closeStoreSwitcherMenu = () => {
    const menu = document.getElementById('sidebar-store-switch-menu');
    const trigger = document.getElementById('sidebar-store-switch-trigger');
    if (menu) menu.classList.add('hidden');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
};

let storeSwitchListenersBound = false;
const storeSwitchPending = {
    storeId: '',
    storeName: '',
    redirectTo: '',
    orderId: '',
};

const currentMerchantStoreId = () => {
    const input = document.getElementById('sidebar-store-switch-id');
    if (input instanceof HTMLInputElement && input.value) {
        return String(input.value);
    }

    return String(document.querySelector('[data-current-store-id]')?.getAttribute('data-current-store-id') || '');
};

const merchantStoreSwitchUrls = () => {
    const holder = document.getElementById('merchant-store-switch-urls');
    if (! holder) {
        return {};
    }
    try {
        return JSON.parse(holder.textContent || '{}');
    } catch (error) {
        return {};
    }
};

const navigateMerchantStoreDestination = (redirectTo, orderId) => {
    const urls = merchantStoreSwitchUrls();
    if (redirectTo === 'order' && orderId) {
        window.location.href = `${urls.orderBase || '/orders/'}${orderId}`;
        return;
    }
    const href = {
        dashboard: urls.dashboard,
        orders: urls.orders,
        products: urls.products,
        locations: urls.locations,
        taxes: urls.taxes,
        delivery: urls.delivery,
    }[redirectTo];
    if (href) {
        window.location.href = href;
    }
};

const openStoreSwitchModal = () => {
    const modal = document.getElementById('storeSwitchConfirmModal');
    if (! modal) {
        return;
    }
    const nameEl = document.getElementById('storeSwitchConfirmName');
    const createWarning = document.getElementById('storeSwitchCreateWarning');
    if (nameEl) {
        nameEl.textContent = storeSwitchPending.storeName;
    }
    createWarning?.classList.toggle('hidden', ! document.querySelector('[data-product-create-guard]'));
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    modal.querySelector('[data-store-switch-cancel]')?.focus();
};

const closeStoreSwitchModal = () => {
    storeSwitchPending.storeId = '';
    storeSwitchPending.storeName = '';
    storeSwitchPending.redirectTo = '';
    storeSwitchPending.orderId = '';
    const redirectInput = document.getElementById('sidebar-store-switch-redirect');
    const orderInput = document.getElementById('sidebar-store-switch-order');
    const modal = document.getElementById('storeSwitchConfirmModal');
    if (redirectInput instanceof HTMLInputElement) {
        redirectInput.value = '';
    }
    if (orderInput instanceof HTMLInputElement) {
        orderInput.value = '';
    }
    modal?.classList.add('hidden');
    modal?.classList.remove('flex');
    document.body.classList.remove('overflow-hidden');
};

const requestMerchantStoreSwitch = ({ storeId, storeName, redirectTo = '', orderId = '' }) => {
    const nextId = String(storeId || '');
    if (nextId === '') {
        return;
    }
    if (nextId === currentMerchantStoreId()) {
        navigateMerchantStoreDestination(redirectTo, orderId);
        return;
    }
    if (! document.getElementById('storeSwitchConfirmModal') || ! document.getElementById('sidebar-store-switch-form')) {
        const form = document.getElementById('hub-store-switch-form');
        if (form instanceof HTMLFormElement) {
            const idInput = form.querySelector('[name="store_id"]');
            const redirectInput = form.querySelector('[name="redirect_to"]');
            const orderInput = form.querySelector('[name="order_id"]');
            if (idInput instanceof HTMLInputElement) {
                idInput.value = nextId;
            }
            if (redirectInput instanceof HTMLInputElement) {
                redirectInput.value = redirectTo || '';
            }
            if (orderInput instanceof HTMLInputElement) {
                orderInput.value = orderId ? String(orderId) : '';
            }
            form.submit();
        }
        return;
    }
    storeSwitchPending.storeId = nextId;
    storeSwitchPending.storeName = storeName || 'this store';
    storeSwitchPending.redirectTo = redirectTo || '';
    storeSwitchPending.orderId = orderId ? String(orderId) : '';
    openStoreSwitchModal();
};

const confirmMerchantStoreSwitch = () => {
    const form = document.getElementById('sidebar-store-switch-form');
    const storeIdInput = document.getElementById('sidebar-store-switch-id');
    const redirectInput = document.getElementById('sidebar-store-switch-redirect');
    const orderInput = document.getElementById('sidebar-store-switch-order');
    if (! (form instanceof HTMLFormElement) || ! (storeIdInput instanceof HTMLInputElement) || storeSwitchPending.storeId === '') {
        closeStoreSwitchModal();
        return;
    }
    storeIdInput.value = storeSwitchPending.storeId;
    if (redirectInput instanceof HTMLInputElement) {
        redirectInput.value = storeSwitchPending.redirectTo;
    }
    if (orderInput instanceof HTMLInputElement) {
        orderInput.value = storeSwitchPending.orderId;
    }
    window.__releaseProductCreateGuard?.();
    form.submit();
};

const initStoreSwitcher = () => {
    if (! storeSwitchListenersBound) {
        storeSwitchListenersBound = true;
        document.addEventListener('click', (event) => {
            const target = event.target;
            if (! (target instanceof Element)) {
                return;
            }
            if (target.closest('[data-store-switch-cancel]')) {
                event.preventDefault();
                closeStoreSwitchModal();
                return;
            }
            if (target.closest('[data-store-switch-confirm]')) {
                event.preventDefault();
                confirmMerchantStoreSwitch();
                return;
            }
            const modal = document.getElementById('storeSwitchConfirmModal');
            if (modal && event.target === modal) {
                closeStoreSwitchModal();
                return;
            }
            const option = target.closest('[data-store-switch-option]');
            if (option instanceof HTMLElement) {
                event.preventDefault();
                event.stopPropagation();
                closeStoreSwitcherMenu();
                requestMerchantStoreSwitch({
                    storeId: option.getAttribute('data-store-id') || '',
                    storeName: option.getAttribute('data-store-name') || 'this store',
                });
                return;
            }
            const request = target.closest('[data-store-switch-request]');
            if (request instanceof HTMLElement) {
                event.preventDefault();
                event.stopPropagation();
                requestMerchantStoreSwitch({
                    storeId: request.getAttribute('data-store-id') || '',
                    storeName: request.getAttribute('data-store-name') || 'this store',
                    redirectTo: request.getAttribute('data-redirect-to') || '',
                    orderId: request.getAttribute('data-order-id') || '',
                });
            }
        });
    }

    const root = document.querySelector('[data-store-switcher]');
    const trigger = document.getElementById('sidebar-store-switch-trigger');
    const menu = document.getElementById('sidebar-store-switch-menu');
    if (! root || ! trigger || ! menu || root.dataset.bound === 'true') {
        return;
    }
    root.dataset.bound = 'true';

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        closeAllMerchantProfileMenus();
        menu.classList.toggle('hidden');
        trigger.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
    });
};

const disableTurboOnMultipartForms = (root = document) => {
    root.querySelectorAll('form[enctype="multipart/form-data"]').forEach((form) => {
        form.setAttribute('data-turbo', 'false');
    });
};

const pathMatchesHref = (pathname, href) => {
    if (! href || href.startsWith('#') || href.startsWith('javascript:')) {
        return false;
    }

    let url;
    try {
        url = new URL(href, window.location.origin);
    } catch (e) {
        return false;
    }

    if (url.origin !== window.location.origin) {
        return false;
    }

    const target = url.pathname.replace(/\/+$/, '') || '/';
    const current = pathname.replace(/\/+$/, '') || '/';

    if (current === target) {
        return true;
    }

    // Nested routes (product workspace, order detail, settings children).
    if (target !== '/' && current.startsWith(`${target}/`)) {
        return true;
    }

    return false;
};

const syncMerchantSidebarActive = () => {
    const nav = document.getElementById('merchantNav');
    if (! nav) {
        return;
    }

    const pathname = window.location.pathname;
    const links = [...nav.querySelectorAll('a.sidebar-nav-link[href]')];
    let best = null;
    let bestLen = -1;

    links.forEach((link) => {
        link.classList.remove('sidebar-nav-link-active');
        if (! pathMatchesHref(pathname, link.getAttribute('href'))) {
            return;
        }
        const len = (link.pathname || '').length;
        if (len > bestLen) {
            best = link;
            bestLen = len;
        }
    });

    if (best) {
        best.classList.add('sidebar-nav-link-active');
    }

    const meta = document.getElementById('merchant-shell-meta');
    const storeLabel = document.getElementById('sidebar-store-label');
    if (meta && storeLabel) {
        storeLabel.textContent = meta.dataset.storeName || 'Profile';
    }
};

window.openSidebar = () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (! sidebar || ! overlay) {
        return;
    }
    sidebar.classList.remove('-translate-x-full');
    overlay.classList.remove('hidden');
    document.body.classList.add('overflow-hidden');
};

window.closeSidebar = () => {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (! sidebar || ! overlay) {
        return;
    }
    sidebar.classList.add('-translate-x-full');
    overlay.classList.add('hidden');
    document.body.classList.remove('overflow-hidden');
};

const productCreateLeave = {
    allowing: false,
    pending: null,
};

const productCreateGuardEl = () => document.querySelector('[data-product-create-guard]');

const productCreateLeaveModalEl = () => document.getElementById('productCreateLeaveModal');

const productCreateDraftsUrl = () => productCreateGuardEl()?.getAttribute('data-drafts-url') || '/products?view=drafts';

const productCatalogUrl = () => productCreateGuardEl()?.getAttribute('data-catalog-url') || '/products';

const isProductEditWizard = () => productCreateGuardEl()?.getAttribute('data-wizard-kind') === 'edit';

const isProductCreateUrl = (href) => {
    if (! href) {
        return false;
    }

    try {
        const url = new URL(href, window.location.origin);
        if (url.origin !== window.location.origin) {
            return false;
        }
        const path = url.pathname.replace(/\/+$/, '') || '/';
        const createPath = (productCreateGuardEl()?.getAttribute('data-create-path') || '/products/create').replace(/\/+$/, '') || '/products/create';
        return path === createPath;
    } catch (e) {
        return false;
    }
};

const closeProductCreateLeaveModal = ({ clearPending = true } = {}) => {
    const modal = productCreateLeaveModalEl();
    if (modal) {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
    }
    document.body.classList.remove('overflow-hidden');
    if (clearPending) {
        productCreateLeave.pending = null;
    }
};

const openProductCreateLeaveModal = () => {
    const modal = productCreateLeaveModalEl();
    if (! modal) {
        return false;
    }
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
    modal.querySelector('[data-product-create-stay]')?.focus();
    return true;
};

const interceptProductCreateLeave = (next) => {
    if (productCreateLeave.allowing || ! productCreateGuardEl()) {
        return false;
    }
    productCreateLeave.pending = next;
    return openProductCreateLeaveModal();
};

const releaseProductCreateLeaveGuard = () => {
    productCreateLeave.allowing = true;
};

window.__releaseProductCreateGuard = releaseProductCreateLeaveGuard;

const continuePendingProductWizardLeave = () => {
    const pending = productCreateLeave.pending;
    releaseProductCreateLeaveGuard();
    closeProductCreateLeaveModal({ clearPending: false });

    if (pending?.type === 'reload') {
        window.location.reload();
        return;
    }

    if (pending?.type === 'form' && pending.form instanceof HTMLFormElement) {
        if (typeof pending.form.requestSubmit === 'function') {
            pending.form.requestSubmit();
        } else {
            pending.form.submit();
        }
        return;
    }

    const href = pending?.href || (isProductEditWizard() ? productCatalogUrl() : productCreateDraftsUrl());
    if (href) {
        window.location.href = href;
    }
};

const saveProductCreateDraftAndLeave = () => {
    const form = document.getElementById('editProductForm');
    const fallbackHref = productCreateLeave.pending?.href || productCreateDraftsUrl();
    if (! (form instanceof HTMLFormElement)) {
        releaseProductCreateLeaveGuard();
        closeProductCreateLeaveModal();
        if (fallbackHref) {
            window.location.href = fallbackHref;
        }
        return;
    }

    const flag = form.querySelector('[name="_save_as_draft"]');
    const dest = form.querySelector('[name="_draft_leave_to"]');
    if (flag) {
        flag.value = '1';
    }
    if (dest) {
        dest.value = fallbackHref;
    }
    releaseProductCreateLeaveGuard();
    closeProductCreateLeaveModal({ clearPending: false });
    if (typeof form.requestSubmit === 'function') {
        form.requestSubmit();
    } else {
        form.submit();
    }
};

const disableTurboForProductCreateNav = () => {
    const guarded = Boolean(productCreateGuardEl());
    document.querySelectorAll('#merchantNav a[href], aside a[href]').forEach((link) => {
        if (! (link instanceof HTMLAnchorElement)) {
            return;
        }
        if (guarded) {
            link.setAttribute('data-turbo', 'false');
        }
    });
};

const bootMerchantUi = (root = document) => {
    portalMerchantLayers();
    initMerchantProfileMenus();
    initStoreSwitcher();
    initUiConfirm();
    initCountryComboboxes(root);
    disableTurboOnMultipartForms(root);
    disableTurboForProductCreateNav();
    syncMerchantSidebarActive();
    if (productCreateGuardEl()) {
        productCreateLeave.allowing = false;
        if (! window.history.state || ! window.history.state.productCreateGuard) {
            window.history.pushState({ productCreateGuard: true }, '', window.location.href);
        }
    }
};

document.addEventListener('click', () => {
    closeAllMerchantProfileMenus();
    closeStoreSwitcherMenu();
});

document.addEventListener('keydown', (event) => {
    if (productCreateLeave.allowing || ! productCreateGuardEl()) {
        return;
    }
    const reloadKey = event.key === 'F5'
        || (((event.ctrlKey || event.metaKey) && ! event.altKey) && (event.key === 'r' || event.key === 'R'));
    if (! reloadKey) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    interceptProductCreateLeave({ type: 'reload' });
}, true);

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }
    const confirmModal = uiConfirmModalEl();
    if (confirmModal && ! confirmModal.classList.contains('hidden')) {
        event.preventDefault();
        finishUiConfirm(false);
        return;
    }
    const leaveModal = productCreateLeaveModalEl();
    if (leaveModal && ! leaveModal.classList.contains('hidden')) {
        event.preventDefault();
        closeProductCreateLeaveModal();
        return;
    }
    const storeModal = document.getElementById('storeSwitchConfirmModal');
    if (storeModal && ! storeModal.classList.contains('hidden')) {
        event.preventDefault();
        storeModal.querySelector('[data-store-switch-cancel]')?.click();
        return;
    }
    closeStoreSwitcherMenu();
});

window.addEventListener('resize', () => {
    if (window.innerWidth >= 768) {
        const overlay = document.getElementById('sidebarOverlay');
        if (overlay) {
            overlay.classList.add('hidden');
        }
        document.body.classList.remove('overflow-hidden');
    }
});

document.addEventListener('click', (e) => {
    const nav = document.getElementById('merchantNav');
    if (! nav || ! nav.contains(e.target)) {
        return;
    }
    const link = e.target.closest('a[href]');
    if (! link) {
        return;
    }
    if (window.matchMedia('(max-width: 767px)').matches) {
        window.closeSidebar();
    }
});

const resetCachedMerchantUi = () => {
    // Keep data-bound / data-turbo-bound. Turbo restores the same nodes, so
    // those flags prevent duplicate listeners. Closing must match each layer's
    // real hide API — slide drawers use translate-x-full, never Tailwind hidden.
    closeMerchantLayers();
};

document.addEventListener('turbo:before-cache', () => {
    teardownCountryComboboxes(document);
    closeAllMerchantProfileMenus();
    closeStoreSwitcherMenu();
    clearMerchantTurboLoading();
    resetCachedMerchantUi();
    if (uiConfirmPending.form || uiConfirmPending.resolve || (uiConfirmModalEl() && ! uiConfirmModalEl().classList.contains('hidden'))) {
        finishUiConfirm(false);
    }
    document.querySelectorAll('[x-data]').forEach((el) => {
        if (typeof Alpine !== 'undefined' && typeof Alpine.destroyTree === 'function') {
            try {
                Alpine.destroyTree(el);
            } catch (e) {
                // Ignore nodes already torn down by the body swap.
            }
        }
    });
});

let merchantTurboReady = false;

const clearMerchantTurboLoading = () => {
    document.documentElement.classList.remove('turbo-loading', 'turbo-frame-loading');
    document.querySelectorAll('[data-filter-results].is-filtering').forEach((el) => {
        el.classList.remove('is-filtering');
    });
};

const turboClickStaysInFrame = (el) => {
    if (! (el instanceof Element)) {
        return false;
    }
    const withFrame = el.closest('[data-turbo-frame]');
    if (withFrame) {
        const frame = withFrame.getAttribute('data-turbo-frame');
        return frame !== null && frame !== '' && frame !== '_top';
    }

    return Boolean(el.closest('#customers-panel, #orders-panel'));
};

document.addEventListener('turbo:load', () => {
    if (merchantTurboReady && typeof Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
        Alpine.initTree(document.body);
    }
    merchantTurboReady = true;
    bootMerchantUi(document);
    clearMerchantTurboLoading();
});

document.addEventListener('turbo:render', () => {
    bootMerchantUi(document);
    clearMerchantTurboLoading();
});

document.addEventListener('click', (event) => {
    const target = event.target;
    if (! (target instanceof Element)) {
        return;
    }

    if (target.closest('.js-open-create-store-modal')) {
        if (typeof window.__openCreateStoreModal === 'function') {
            event.preventDefault();
            window.__openCreateStoreModal();
        }
        return;
    }

    const editStoreBtn = target.closest('.js-open-edit-store-modal');
    if (editStoreBtn && typeof window.__openEditStoreModal === 'function') {
        event.preventDefault();
        window.__openEditStoreModal(editStoreBtn);
        return;
    }

    if (target.closest('[data-lc-open-add]')) {
        event.preventDefault();
        window.__locationsOpenAdd?.();
        return;
    }
    const locationEdit = target.closest('[data-lc-edit]');
    if (locationEdit) {
        event.preventDefault();
        window.__locationsOpenEdit?.(locationEdit.getAttribute('data-lc-edit'));
        return;
    }
    if (target.closest('[data-lc-close-modal]') || target.id === 'locationEditorModal') {
        event.preventDefault();
        window.__locationsClose?.();
        return;
    }

    if (target.closest('[data-dc-open-add]')) {
        event.preventDefault();
        window.__couponsOpenAdd?.();
        return;
    }
    const couponEdit = target.closest('[data-dc-edit]');
    if (couponEdit) {
        event.preventDefault();
        window.__couponsOpenEdit?.(couponEdit.getAttribute('data-dc-edit'));
        return;
    }
    if (target.closest('[data-dc-close-drawer]') || target.id === 'discountDrawerOverlay') {
        event.preventDefault();
        window.__couponsCloseDrawer?.();
        return;
    }

    if (target.closest('[data-wc-open-replace-key]')) {
        event.preventDefault();
        const replaceModal = document.getElementById('websiteReplaceKeyModal');
        showMerchantLayer(replaceModal);
        if (replaceModal) {
            document.body.classList.add('overflow-hidden');
            replaceModal.querySelector('[data-wc-key-cancel]')?.focus();
        }
        return;
    }
    if (target.closest('[data-wc-open-remove-key]')) {
        event.preventDefault();
        const removeModal = document.getElementById('websiteRemoveKeyModal');
        showMerchantLayer(removeModal);
        if (removeModal) {
            document.body.classList.add('overflow-hidden');
            removeModal.querySelector('[data-wc-key-cancel]')?.focus();
        }
        return;
    }
    if (target.closest('[data-wc-key-cancel]') || target.id === 'websiteReplaceKeyModal' || target.id === 'websiteRemoveKeyModal') {
        const keyModal = target.id === 'websiteReplaceKeyModal' || target.id === 'websiteRemoveKeyModal'
            ? target
            : target.closest('#websiteReplaceKeyModal, #websiteRemoveKeyModal');
        if (keyModal) {
            event.preventDefault();
            closeMerchantLayer(keyModal);
            document.body.classList.remove('overflow-hidden');
        }
        return;
    }

    const closeTaxDialog = target.closest('[data-trb-close-dialog]');
    if (closeTaxDialog) {
        const dialog = document.getElementById(closeTaxDialog.getAttribute('data-trb-close-dialog') || '');
        if (dialog?.open) {
            dialog.close();
        }
        return;
    }

    if (target.id === 'notification-prefs-edit' || target.closest('#notification-prefs-edit')) {
        window.__notificationsUnlock?.();
    }
});

document.addEventListener('click', (e) => {
    const tab = e.target.closest('[data-filter-tab]');
    if (! tab) {
        return;
    }
    const group = tab.closest('[data-filter-tabs]');
    if (! group) {
        return;
    }
    group.querySelectorAll('[data-filter-tab]').forEach((el) => {
        el.classList.remove('bg-brand', 'text-white');
        el.classList.add('bg-surface-muted', 'text-ink-secondary');
    });
    tab.classList.add('bg-brand', 'text-white');
    tab.classList.remove('bg-surface-muted', 'text-ink-secondary');

    const panel = tab.closest('turbo-frame');
    const results = panel?.querySelector('[data-filter-results]');
    if (results) {
        results.classList.add('is-filtering');
    }
});

document.addEventListener('turbo:frame-load', () => {
    clearMerchantTurboLoading();
});

document.addEventListener('turbo:click', (event) => {
    if (! productCreateLeave.allowing && productCreateGuardEl()) {
        const url = event.detail && event.detail.url ? String(event.detail.url) : '';
        if (url !== '' && ! isProductCreateUrl(url)) {
            event.preventDefault();
            interceptProductCreateLeave({ type: 'href', href: url });
            return;
        }
    }
    const link = event.target instanceof Element ? event.target : null;
    if (! link) {
        return;
    }
    if (turboClickStaysInFrame(link)) {
        document.documentElement.classList.add('turbo-frame-loading');
        return;
    }
    document.documentElement.classList.add('turbo-loading');
});

document.addEventListener('turbo:before-visit', (event) => {
    if (productCreateLeave.allowing || ! productCreateGuardEl()) {
        return;
    }
    const url = event.detail && event.detail.url ? String(event.detail.url) : '';
    if (url === '' || isProductCreateUrl(url)) {
        return;
    }
    event.preventDefault();
    interceptProductCreateLeave({ type: 'href', href: url });
});

document.addEventListener('click', (event) => {
    if (! (event.target instanceof Element)) {
        return;
    }

    if (event.target.closest('[data-product-create-stay]')) {
        event.preventDefault();
        closeProductCreateLeaveModal();
        return;
    }

    if (event.target.closest('[data-product-create-leave]')) {
        if (productCreateLeave.allowing || (! productCreateGuardEl() && ! productCreateLeaveModalEl())) {
            return;
        }
        event.preventDefault();
        if (! productCreateLeave.pending) {
            productCreateLeave.pending = {
                type: 'href',
                href: isProductEditWizard() ? productCatalogUrl() : productCreateDraftsUrl(),
            };
        }
        if (isProductEditWizard()) {
            continuePendingProductWizardLeave();
            return;
        }
        saveProductCreateDraftAndLeave();
        return;
    }

    if (event.target.closest('[data-product-create-save-draft]')) {
        if (productCreateLeave.allowing || (! productCreateGuardEl() && ! productCreateLeaveModalEl())) {
            return;
        }
        event.preventDefault();
        if (! productCreateLeave.pending) {
            productCreateLeave.pending = { type: 'href', href: productCreateDraftsUrl() };
        }
        saveProductCreateDraftAndLeave();
        return;
    }

    if (productCreateLeave.allowing || ! productCreateGuardEl()) {
        return;
    }
    if (event.target.closest('#productCreateLeaveModal')) {
        if (event.target.id === 'productCreateLeaveModal') {
            closeProductCreateLeaveModal();
        }
        return;
    }

    const link = event.target.closest('a[href]');
    if (! link) {
        return;
    }
    if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || link.target === '_blank') {
        return;
    }
    if (link.hasAttribute('data-product-create-allow-leave')) {
        releaseProductCreateLeaveGuard();
        return;
    }
    const href = link.getAttribute('href') || '';
    if (href === '' || href.startsWith('#') || href.startsWith('javascript:')) {
        return;
    }
    if (isProductCreateUrl(link.href)) {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    interceptProductCreateLeave({ type: 'href', href: link.href });
}, true);

document.addEventListener('submit', (event) => {
    if (productCreateLeave.allowing || ! productCreateGuardEl()) {
        return;
    }
    const form = event.target;
    if (! (form instanceof HTMLFormElement)) {
        return;
    }
    if (form.id === 'editProductForm') {
        return;
    }
    if (form.id === 'deleteProductForm') {
        releaseProductCreateLeaveGuard();
        return;
    }
    if (form.hasAttribute('data-catalog-kind')) {
        return;
    }
    if (form.id === 'sidebar-store-switch-form') {
        return;
    }
    event.preventDefault();
    event.stopPropagation();
    interceptProductCreateLeave({ type: 'form', form });
}, true);

window.addEventListener('beforeunload', (event) => {
    if (productCreateLeave.allowing || ! productCreateGuardEl()) {
        return;
    }
    event.preventDefault();
    event.returnValue = '';
});

window.addEventListener('popstate', () => {
    if (productCreateLeave.allowing || ! productCreateGuardEl()) {
        return;
    }
    if (! window.history.state || ! window.history.state.productCreateGuard) {
        window.history.pushState({ productCreateGuard: true }, '', window.location.href);
    }
    interceptProductCreateLeave({ type: 'href', href: productCreateDraftsUrl() });
});

document.addEventListener('turbo:submit-start', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && turboClickStaysInFrame(form)) {
        document.documentElement.classList.add('turbo-frame-loading');
        return;
    }
    document.documentElement.classList.add('turbo-loading');
});

document.addEventListener('turbo:before-fetch-response', () => {
    document.documentElement.classList.remove('turbo-loading');
});

document.addEventListener('turbo:fetch-request-error', () => {
    clearMerchantTurboLoading();
});

document.addEventListener('click', (event) => {
    const target = event.target;
    if (! (target instanceof Element)) {
        return;
    }

    const toggle = target.closest('[data-password-toggle]');
    if (! (toggle instanceof HTMLElement)) {
        return;
    }

    const inputId = toggle.getAttribute('data-password-toggle');
    if (! inputId) {
        return;
    }

    const input = document.getElementById(inputId);
    if (! (input instanceof HTMLInputElement)) {
        return;
    }

    const showing = input.type === 'text';
    input.type = showing ? 'password' : 'text';
    toggle.setAttribute('aria-pressed', showing ? 'false' : 'true');
    toggle.setAttribute('aria-label', showing ? 'Show password' : 'Hide password');
    if (toggle.childElementCount === 0) {
        toggle.textContent = showing ? 'Show' : 'Hide';
    }
});

Alpine.start();
