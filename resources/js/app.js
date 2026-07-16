import './bootstrap';
import Alpine from 'alpinejs';

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

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', portalMerchantLayers, { once: true });
} else {
    portalMerchantLayers();
}

Alpine.start();
