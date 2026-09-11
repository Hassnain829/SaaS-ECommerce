/**
 * Merchant dashboard: SVG revenue chart, hover tooltips, and display-only layout.
 * Range changes are real GET links. Initialization is idempotent for Turbo.
 */
(function () {
    const DEFAULT_VISIBLE = {
        attention: true,
        orderFlow: true,
        recentOrders: true,
        inventory: true,
        systems: true,
    };

    const storageKey = (root) => {
        const userId = root.getAttribute('data-user-id') || '0';
        const storeId = root.getAttribute('data-store-id') || '0';
        return `merchant.dashboard.visible.${userId}.${storeId}`;
    };

    const readVisible = (root) => {
        try {
            const raw = localStorage.getItem(storageKey(root));
            if (! raw) {
                return { ...DEFAULT_VISIBLE };
            }
            const parsed = JSON.parse(raw);
            return { ...DEFAULT_VISIBLE, ...parsed };
        } catch (e) {
            return { ...DEFAULT_VISIBLE };
        }
    };

    const writeVisible = (root, visible) => {
        try {
            localStorage.setItem(storageKey(root), JSON.stringify(visible));
        } catch (e) {
            // Ignore private-mode storage failures.
        }
    };

    const applyVisibility = (root, visible) => {
        const panels = {
            attention: root.querySelector('[data-dashboard-panel="attention"]'),
            orderFlow: root.querySelector('[data-dashboard-panel="orderFlow"]'),
            recentOrders: root.querySelector('[data-dashboard-panel="recentOrders"]'),
            inventory: root.querySelector('[data-dashboard-panel="inventory"]'),
            systems: root.querySelector('[data-dashboard-panel="systems"]'),
        };
        Object.keys(panels).forEach((key) => {
            if (panels[key]) {
                panels[key].hidden = visible[key] === false;
            }
        });
        const analytics = root.querySelector('#analyticsGrid');
        const lower = root.querySelector('#lowerGrid');
        if (analytics) {
            analytics.classList.toggle('is-single', visible.orderFlow === false);
        }
        if (lower) {
            lower.classList.toggle('is-single', visible.inventory === false);
        }
    };

    const syncCustomizeForm = (root, visible) => {
        const form = document.getElementById('dashboardCustomizeForm');
        if (! form) {
            return;
        }
        Object.keys(DEFAULT_VISIBLE).forEach((key) => {
            const input = form.querySelector(`input[name="${key}"]`);
            if (input) {
                input.checked = visible[key] !== false;
            }
        });
    };

    const CHART = {
        brand: '#0f6e56',
        previous: '#9aa6ad',
        grid: '#e7ebed',
        gridStrong: '#b6c0c5',
        label: '#667085',
        width: 900,
        height: 220,
        left: 64,
        right: 20,
        top: 18,
        bottom: 36,
    };

    const escapeXml = (value) => String(value).replace(/[&<>"']/g, (char) => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
    }[char]));

    const niceCeiling = (value) => {
        if (! Number.isFinite(value) || value <= 0) {
            return 1;
        }
        const padded = value * 1.12;
        const exp = Math.floor(Math.log10(padded));
        const mag = 10 ** exp;
        const residual = padded / mag;
        const nice = residual <= 1 ? 1 : residual <= 2 ? 2 : residual <= 2.5 ? 2.5 : residual <= 5 ? 5 : 10;

        return nice * mag;
    };

    const formatAxis = (value, currency) => {
        if (value === 0) {
            return currency === 'USD' ? '$0' : `0`;
        }
        if (Math.abs(value) >= 1000) {
            const compact = (value / 1000).toFixed(Math.abs(value) >= 10000 || value % 1000 === 0 ? 0 : 1).replace(/\.0$/, '');
            return currency === 'USD' ? `$${compact}k` : `${compact}k`;
        }
        const rounded = Math.abs(value) >= 10 || Number.isInteger(value) ? Math.round(value) : Math.round(value * 10) / 10;
        if (currency === 'USD') {
            return `$${rounded}`;
        }

        return String(rounded);
    };

    const linePath = (values, x, y) => values
        .map((value, index) => `${index ? 'L' : 'M'}${x(index).toFixed(2)},${y(value).toFixed(2)}`)
        .join(' ');

    const renderChart = (root) => {
        const dataEl = document.getElementById('merchant-dashboard-chart-data');
        const wrap = root.querySelector('#chartWrap');
        const svg = root.querySelector('#revenueChart');
        const tooltip = root.querySelector('#chartTooltip');
        if (! dataEl || ! wrap || ! svg || ! tooltip) {
            return;
        }
        let data;
        try {
            data = JSON.parse(dataEl.textContent || '{}');
        } catch (e) {
            return;
        }
        if (! data || data.empty || ! Array.isArray(data.current) || data.current.length === 0) {
            svg.innerHTML = '';
            tooltip.hidden = true;
            return;
        }

        const currency = root.getAttribute('data-currency') || 'USD';
        const W = CHART.width;
        const H = CHART.height;
        const left = CHART.left;
        const right = CHART.right;
        const top = CHART.top;
        const bottom = CHART.bottom;
        const plotBottom = H - bottom;
        const current = data.current.map((value) => Number(value) || 0);
        const previous = (data.previous || []).map((value) => Number(value) || 0);
        const labels = data.labels || [];
        const formatted = data.current_formatted || [];
        const maxRaw = Math.max(0, ...current, ...previous);
        const yMax = niceCeiling(maxRaw);
        const lastIndex = Math.max(1, current.length - 1);
        const previousHasData = previous.some((value) => value > 0);
        const x = (index) => left + (index * (W - left - right) / lastIndex);
        const y = (value) => top + (plotBottom - top) * (1 - (value / yMax));
        const currentLine = linePath(current, x, y);
        const area = `${currentLine} L${x(current.length - 1).toFixed(2)},${plotBottom} L${x(0).toFixed(2)},${plotBottom} Z`;
        const fillId = `mdashChartFill-${root.getAttribute('data-store-id') || '0'}`;
        const labelFont = `font-family="Inter, ui-sans-serif, system-ui, sans-serif"`;

        let grid = '';
        for (let i = 0; i <= 4; i += 1) {
            const value = yMax * (1 - i / 4);
            const yy = top + ((plotBottom - top) * i / 4);
            grid += `<line x1="${left}" y1="${yy.toFixed(2)}" x2="${W - right}" y2="${yy.toFixed(2)}" stroke="${CHART.grid}" stroke-width="1"/><text x="${left - 8}" y="${(yy + 3.5).toFixed(2)}" text-anchor="end" fill="${CHART.label}" font-size="10" ${labelFont}>${escapeXml(formatAxis(value, currency))}</text>`;
        }

        const labelStep = Math.max(1, Math.ceil(labels.length / 7));
        const endIndex = labels.length - 1;
        let axisLabels = '';
        labels.forEach((label, index) => {
            const isEdge = index === 0 || index === endIndex;
            const tooCloseToEnd = index !== endIndex && (endIndex - index) < Math.max(2, Math.floor(labelStep / 2) + 1);
            if (! isEdge && (index % labelStep !== 0 || tooCloseToEnd)) {
                return;
            }
            axisLabels += `<text x="${x(index).toFixed(2)}" y="${H - 12}" text-anchor="middle" fill="${CHART.label}" font-size="9.5" ${labelFont}>${escapeXml(label)}</text>`;
        });

        const previousPath = previousHasData
            ? `<path d="${linePath(previous, x, y)}" fill="none" stroke="${CHART.previous}" stroke-width="1.5" stroke-dasharray="5 5" stroke-linejoin="round" stroke-linecap="round"/>`
            : '';

        svg.setAttribute('viewBox', `0 0 ${W} ${H}`);
        svg.setAttribute('preserveAspectRatio', 'none');
        svg.removeAttribute('hidden');
        svg.innerHTML = `<defs><linearGradient id="${fillId}" x1="0" x2="0" y1="0" y2="1"><stop offset="0%" stop-color="${CHART.brand}" stop-opacity="0.22"/><stop offset="100%" stop-color="${CHART.brand}" stop-opacity="0.02"/></linearGradient></defs>${grid}${axisLabels}<path d="${area}" fill="url(#${fillId})"/>${previousPath}<path d="${currentLine}" fill="none" stroke="${CHART.brand}" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/><g data-chart-hover></g>`;

        const hover = svg.querySelector('[data-chart-hover]');
        if (! hover) {
            return;
        }

        const showIndex = (index) => {
            const safeIndex = Math.max(0, Math.min(current.length - 1, index));
            const cx = x(safeIndex);
            const cy = y(current[safeIndex]);
            hover.innerHTML = `<line x1="${cx.toFixed(2)}" y1="${top}" x2="${cx.toFixed(2)}" y2="${plotBottom}" stroke="${CHART.gridStrong}" stroke-dasharray="3 3"/><circle cx="${cx.toFixed(2)}" cy="${cy.toFixed(2)}" r="4.5" fill="${CHART.brand}" stroke="#fff" stroke-width="2"/>`;
            const labelEl = tooltip.querySelector('small');
            const valueEl = tooltip.querySelector('strong');
            if (labelEl) {
                labelEl.textContent = labels[safeIndex] || '';
            }
            if (valueEl) {
                valueEl.textContent = formatted[safeIndex] || formatAxis(current[safeIndex], currency);
            }
            tooltip.hidden = false;
            const leftPct = Math.max(12, Math.min(88, (cx / W) * 100));
            tooltip.style.left = `${leftPct}%`;
            tooltip.style.top = `${(cy / H) * 100}%`;
            tooltip.classList.toggle('is-below', cy < top + 36);
        };

        const onMove = (event) => {
            const rect = svg.getBoundingClientRect();
            if (! rect.width) {
                return;
            }
            const px = ((event.clientX - rect.left) / rect.width) * W;
            const index = Math.round((px - left) / (W - left - right) * lastIndex);
            showIndex(index);
        };
        const onLeave = () => {
            const peak = current.reduce((best, value, index) => (value >= current[best] ? index : best), 0);
            showIndex(peak);
        };

        if (wrap._mdashChartMove) {
            wrap.removeEventListener('pointermove', wrap._mdashChartMove);
            wrap.removeEventListener('pointerleave', wrap._mdashChartLeave);
        }
        wrap._mdashChartMove = onMove;
        wrap._mdashChartLeave = onLeave;
        wrap.addEventListener('pointermove', onMove);
        wrap.addEventListener('pointerleave', onLeave);
        onLeave();
    };

    const boot = () => {
        const root = document.querySelector('[data-merchant-dashboard]');
        if (! root) {
            return;
        }
        const visible = readVisible(root);
        applyVisibility(root, visible);
        syncCustomizeForm(root, visible);
        renderChart(root);
        root.dataset.dashboardReady = '1';
    };

    const bindOnce = () => {
        if (window.__merchantDashboardBound) {
            return;
        }
        window.__merchantDashboardBound = true;

        document.addEventListener('click', (event) => {
            const openBtn = event.target.closest('[data-dashboard-customize]');
            if (openBtn) {
                const dialog = document.getElementById('dashboardCustomizeDialog');
                if (dialog && typeof dialog.showModal === 'function') {
                    dialog.showModal();
                }
                return;
            }
            const closeBtn = event.target.closest('[data-dashboard-customize-close]');
            if (closeBtn) {
                const dialog = document.getElementById('dashboardCustomizeDialog');
                dialog?.close();
                return;
            }
            const resetBtn = event.target.closest('[data-dashboard-customize-reset]');
            if (resetBtn) {
                const root = document.querySelector('[data-merchant-dashboard]');
                if (! root) {
                    return;
                }
                writeVisible(root, { ...DEFAULT_VISIBLE });
                applyVisibility(root, DEFAULT_VISIBLE);
                syncCustomizeForm(root, DEFAULT_VISIBLE);
            }
        });

        document.addEventListener('submit', (event) => {
            const form = event.target.closest('#dashboardCustomizeForm');
            if (! form) {
                return;
            }
            event.preventDefault();
            const root = document.querySelector('[data-merchant-dashboard]');
            if (! root) {
                return;
            }
            const visible = { ...DEFAULT_VISIBLE };
            Object.keys(DEFAULT_VISIBLE).forEach((key) => {
                const input = form.querySelector(`input[name="${key}"]`);
                visible[key] = ! input || input.checked;
            });
            writeVisible(root, visible);
            applyVisibility(root, visible);
            document.getElementById('dashboardCustomizeDialog')?.close();
        });

        document.addEventListener('turbo:before-cache', () => {
            document.querySelectorAll('[data-merchant-dashboard]').forEach((el) => {
                delete el.dataset.dashboardReady;
            });
            const dialog = document.getElementById('dashboardCustomizeDialog');
            if (dialog && typeof dialog.close === 'function') {
                dialog.close();
            }
        });
    };

    bindOnce();
    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);
})();
