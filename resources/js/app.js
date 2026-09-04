import './bootstrap';
import * as Turbo from '@hotwired/turbo';
import Alpine from 'alpinejs';

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

const closeStoreSwitcherMenu = () => {
    const menu = document.getElementById('sidebar-store-switch-menu');
    const trigger = document.getElementById('sidebar-store-switch-trigger');
    if (menu) menu.classList.add('hidden');
    if (trigger) trigger.setAttribute('aria-expanded', 'false');
};

const initStoreSwitcher = () => {
    const root = document.querySelector('[data-store-switcher]');
    const form = document.getElementById('sidebar-store-switch-form');
    const storeIdInput = document.getElementById('sidebar-store-switch-id');
    const trigger = document.getElementById('sidebar-store-switch-trigger');
    const menu = document.getElementById('sidebar-store-switch-menu');
    const modal = document.getElementById('storeSwitchConfirmModal');
    if (!root || !form || !storeIdInput || !trigger || !menu || !modal) {
        return;
    }
    if (root.dataset.bound === 'true') {
        return;
    }
    root.dataset.bound = 'true';

    let pendingStoreId = '';
    let pendingStoreName = '';

    const openMenu = () => {
        closeAllMerchantProfileMenus();
        menu.classList.toggle('hidden');
        trigger.setAttribute('aria-expanded', menu.classList.contains('hidden') ? 'false' : 'true');
    };

    const openModal = () => {
        const nameEl = document.getElementById('storeSwitchConfirmName');
        const createWarning = document.getElementById('storeSwitchCreateWarning');
        if (nameEl) nameEl.textContent = pendingStoreName;
        createWarning?.classList.toggle('hidden', ! document.querySelector('[data-product-create-guard]'));
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        modal.querySelector('[data-store-switch-cancel]')?.focus();
    };

    const closeModal = () => {
        pendingStoreId = '';
        pendingStoreName = '';
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    };

    trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        openMenu();
    });

    menu.querySelectorAll('[data-store-switch-option]').forEach((option) => {
        option.addEventListener('click', (event) => {
            event.preventDefault();
            event.stopPropagation();
            closeStoreSwitcherMenu();
            const nextId = String(option.getAttribute('data-store-id') || '');
            const currentId = String(storeIdInput.value || '');
            if (nextId === '' || nextId === currentId) {
                return;
            }
            pendingStoreId = nextId;
            pendingStoreName = option.getAttribute('data-store-name') || 'this store';
            openModal();
        });
    });

    modal.querySelector('[data-store-switch-cancel]')?.addEventListener('click', (event) => {
        event.preventDefault();
        closeModal();
    });

    modal.querySelector('[data-store-switch-confirm]')?.addEventListener('click', (event) => {
        event.preventDefault();
        if (pendingStoreId === '') {
            closeModal();
            return;
        }
        storeIdInput.value = pendingStoreId;
        window.__releaseProductCreateGuard?.();
        form.submit();
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) {
            closeModal();
        }
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
    initStoreSwitcher();
    disableTurboOnMultipartForms(root);
    syncMerchantSidebarActive();
};

document.addEventListener('click', () => {
    closeAllMerchantProfileMenus();
    closeStoreSwitcherMenu();
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
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
    closeStoreSwitcherMenu();
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
