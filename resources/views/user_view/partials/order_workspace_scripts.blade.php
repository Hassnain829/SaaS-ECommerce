<script>
window.bootMerchantPage('order-workspace', function () {
    return document.querySelector('[data-order-workspace]');
}, function (root) {

    const $ = (selector, el = root) => el.querySelector(selector);
    const $$ = (selector, el = root) => [...el.querySelectorAll(selector)];

    const tabHashes = {
        overview: '',
        fulfillment: 'fulfillment',
        'after-sales': 'returns-refunds',
        activity: 'activity',
    };

    function activateTab(tabName) {
        if (!tabName) return;

        $$('.tab').forEach((tab) => {
            tab.classList.toggle('active', tab.dataset.tab === tabName);
        });

        root.querySelectorAll('.workspace > .panel').forEach((panel) => {
            panel.hidden = panel.id !== `panel-${tabName}`;
        });

        const hash = tabHashes[tabName] ?? '';
        const next = hash ? `#${hash}` : window.location.pathname + window.location.search;
        if (hash) {
            if (window.location.hash !== `#${hash}`) {
                history.replaceState(null, '', `#${hash}`);
            }
        } else if (window.location.hash && ['#fulfillment', '#returns-refunds', '#activity', '#status-manager'].includes(window.location.hash)) {
            history.replaceState(null, '', window.location.pathname + window.location.search);
        }

        return next;
    }

    function activateService(service) {
        $$('[data-service]').forEach((button) => {
            button.classList.toggle('active', button.dataset.service === service);
        });

        const map = {
            return: 'start-return-form',
            refund: 'issue-refund-form',
            exchange: 'start-exchange-form',
        };

        Object.entries(map).forEach(([key, id]) => {
            const form = document.getElementById(id);
            if (!form) return;
            form.classList.toggle('hidden', key !== service);
        });
    }

    function filterActivity() {
        const filter = $('#activityFilter')?.value || 'all';
        const items = $$('#panel-activity [data-activity-item]');
        let visible = 0;

        items.forEach((item) => {
            const match = filter === 'all' || item.dataset.activityType === filter;
            item.hidden = !match;
            if (match) visible += 1;
        });

        const empty = $('#activityFilterEmpty');
        if (empty) empty.hidden = visible > 0 || items.length === 0;
    }

    function openStatusDialog() {
        const dialog = $('#statusDialog');
        if (dialog && typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            activateTab('overview');
            document.getElementById('status-manager')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function closeDialogs() {
        $$('dialog[open]').forEach((dialog) => dialog.close());
    }

    function setMethod(method) {
        $$('[data-method]').forEach((button) => {
            button.classList.toggle('active', button.dataset.method === method);
        });

        const fedex = method === 'fedex';
        const fedexPanel = document.getElementById('fedex-ship-panel');
        const manualPanel = document.getElementById('manual-shipment-panel');
        if (fedexPanel) fedexPanel.hidden = !fedex;
        if (manualPanel) manualPanel.hidden = fedex;
    }

    let toastTimer = null;
    function showToast(message) {
        const toast = $('#orderWorkspaceToast');
        if (!toast) return;
        toast.textContent = message;
        toast.classList.add('show');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(() => toast.classList.remove('show'), 2800);
    }

    $$('.tab, [data-tab]').forEach((button) => {
        button.addEventListener('click', (event) => {
            if (!button.dataset.tab) return;
            event.preventDefault();
            activateTab(button.dataset.tab);
        });
    });

    $$('[data-service]').forEach((button) => {
        button.addEventListener('click', () => activateService(button.dataset.service));
    });

    $$('[data-method]').forEach((button) => {
        button.addEventListener('click', () => setMethod(button.dataset.method));
    });

    $('#activityFilter')?.addEventListener('change', filterActivity);

    const moreButton = $('#moreButton');
    const moreMenu = $('#moreMenu');
    moreButton?.addEventListener('click', (event) => {
        event.stopPropagation();
        if (!moreMenu) return;
        moreMenu.hidden = !moreMenu.hidden;
        moreButton.setAttribute('aria-expanded', String(!moreMenu.hidden));
    });

    window.__orderWorkspaceOnClick = (event) => {
        if (moreMenu && !event.target.closest('.head-actions')) {
            moreMenu.hidden = true;
            moreButton?.setAttribute('aria-expanded', 'false');
        }

        const trigger = event.target.closest('[data-action]');
        if (!trigger || !root.contains(trigger)) return;

        const action = trigger.dataset.action;

        if (action === 'print') {
            window.print();
        }

        if (action === 'fulfill' || action === 'fulfillment-details') {
            activateTab('fulfillment');
        }

        if (action === 'after-sales') {
            activateTab('after-sales');
        }

        if (action === 'refund') {
            activateTab('after-sales');
            activateService('refund');
        }

        if (action === 'return') {
            activateTab('after-sales');
            activateService('return');
        }

        if (action === 'exchange') {
            activateTab('after-sales');
            activateService('exchange');
        }

        if (action === 'status') {
            openStatusDialog();
        }

        if (action === 'copy-tracking') {
            const tracking = trigger.dataset.tracking || '';
            if (tracking && navigator.clipboard) {
                navigator.clipboard.writeText(tracking);
                showToast('Tracking number copied.');
            }
        }

        if (action === 'cancel-order') {
            $('#confirmDialog')?.showModal();
        }
    };

    $$('[data-close-dialog]').forEach((button) => {
        button.addEventListener('click', () => closeDialogs());
    });

    $('#confirmCancelOrder')?.addEventListener('click', () => {
        closeDialogs();
        document.getElementById('cancel-order-form')?.submit();
    });

    const hashMap = {
        fulfillment: 'fulfillment',
        'returns-refunds': 'after-sales',
        activity: 'activity',
        'status-manager': 'status',
    };

    const initialTab = root.dataset.initialTab || 'overview';
    const hash = window.location.hash.replace('#', '');
    if (hash === 'status-manager' || root.dataset.openStatus === '1') {
        activateTab(initialTab === 'after-sales' || initialTab === 'fulfillment' ? initialTab : 'overview');
        openStatusDialog();
    } else if (hashMap[hash] && hashMap[hash] !== 'status') {
        activateTab(hashMap[hash]);
    } else {
        activateTab(initialTab);
    }

    if (root.dataset.initialTab === 'after-sales' && root.dataset.initialService) {
        activateService(root.dataset.initialService);
    }

    filterActivity();
});
window.bindMerchantDocOnce('order-workspace:click', function () {
    document.addEventListener('click', function (event) {
        if (typeof window.__orderWorkspaceOnClick === 'function') {
            window.__orderWorkspaceOnClick(event);
        }
    });
});
</script>
