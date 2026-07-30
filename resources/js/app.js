import './bootstrap';
import * as Turbo from '@hotwired/turbo';
import Alpine from 'alpinejs';

window.Turbo = Turbo;
window.Alpine = Alpine;

window.paymentsConsole = (initialPanel = 'test', storeId = 0, canManage = false, liveReady = false) => ({
    stripePanel: initialPanel,
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
});

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

/**
 * Fixed overlays must live directly under <body>.
 *
 * The merchant page wrapper is animated and scrollable. Browsers treat an
 * animated ancestor as the containing block for fixed descendants, which can
 * clip a modal to the workspace and position it relative to the current page
 * scroll. Portaling every fixed merchant layer to <body> keeps it centered in
 * the viewport on every page, including nested product and catalog dialogs.
 */
const portalMerchantLayers = () => {
    const selector = [
        '.ui-modal-shell',
        '.ui-modal-overlay',
        '.ui-drawer-panel',
        '.shipping-drawer-modal',
    ].join(',');

    document.querySelectorAll(selector).forEach((layer) => {
        if (layer.parentElement !== document.body) {
            document.body.appendChild(layer);
        }
        layer.dataset.uiPortalReady = 'true';
    });
};

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

const bootMerchantUi = (root = document) => {
    portalMerchantLayers();
    initMerchantProfileMenus();
    disableTurboOnMultipartForms(root);
    syncMerchantSidebarActive();
};

document.addEventListener('click', () => {
    closeAllMerchantProfileMenus();
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

document.addEventListener('DOMContentLoaded', () => {
    const merchantNav = document.getElementById('merchantNav');
    if (merchantNav) {
        merchantNav.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');
            if (! link) {
                return;
            }
            if (window.matchMedia('(max-width: 767px)').matches) {
                window.closeSidebar();
            }
        });
    }
});

document.addEventListener('turbo:before-cache', () => {
    closeAllMerchantProfileMenus();
    document.querySelectorAll('[data-ui-portal-ready="true"]').forEach((layer) => {
        if (layer.parentElement === document.body && ! layer.classList.contains('hidden')) {
            layer.classList.add('hidden');
        }
    });
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

document.addEventListener('turbo:load', () => {
    if (merchantTurboReady && typeof Alpine !== 'undefined' && typeof Alpine.initTree === 'function') {
        Alpine.initTree(document.body);
    }
    merchantTurboReady = true;
    bootMerchantUi(document);
    document.documentElement.classList.remove('turbo-loading');
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
    document.documentElement.classList.remove('turbo-frame-loading', 'turbo-loading');
    document.querySelectorAll('[data-filter-results].is-filtering').forEach((el) => {
        el.classList.remove('is-filtering');
    });
});

document.addEventListener('turbo:click', (event) => {
    const link = event.target;
    if (! (link instanceof Element)) {
        return;
    }
    if (link.closest('#customers-panel, #orders-panel')) {
        document.documentElement.classList.add('turbo-frame-loading');
        return;
    }
    document.documentElement.classList.add('turbo-loading');
});

document.addEventListener('turbo:submit-start', (event) => {
    const form = event.target;
    if (form instanceof HTMLFormElement && form.closest('#customers-panel, #orders-panel')) {
        document.documentElement.classList.add('turbo-frame-loading');
        return;
    }
    document.documentElement.classList.add('turbo-loading');
});

document.addEventListener('turbo:before-fetch-response', () => {
    document.documentElement.classList.remove('turbo-loading');
});

document.addEventListener('turbo:fetch-request-error', () => {
    document.documentElement.classList.remove('turbo-loading', 'turbo-frame-loading');
});

Alpine.start();
