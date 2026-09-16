{{-- Scoped Delivery operations styles. Does not restyle sidebar or topbar. --}}
<style>
.delivery-ops-icons {
    position: absolute;
    width: 0;
    height: 0;
    overflow: visible;
    pointer-events: none;
}
.delivery-ops {
    --do-brand: #087a5b;
    --do-brand-dark: #056347;
    --do-brand-soft: #e6f6f0;
    --do-ink: #111827;
    --do-text: #334155;
    --do-muted: #64748b;
    --do-line: #dce3e8;
    --do-line-soft: #edf1f4;
    --do-white: #fff;
    --do-amber: #b45309;
    --do-amber-bg: #fff8e8;
    --do-danger: #b42318;
    --do-danger-bg: #fff1f0;
    --do-blue: #175cd3;
    --do-blue-bg: #eff8ff;
    --do-shadow: 0 1px 2px rgba(15, 23, 42, .05), 0 10px 28px rgba(15, 23, 42, .035);
    color: var(--do-ink);
    font-family: var(--font-sans);
    font-size: 14px;
    font-weight: 400;
    line-height: 1.45;
    font-synthesis: none;
}
.delivery-ops svg { display: block; }
.delivery-ops .do-icon { width: 18px; height: 18px; overflow: visible; }
.delivery-ops .do-sr {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
}
.delivery-ops .page-heading {
    margin: 0 0 12px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
}
.delivery-ops .page-heading h2 {
    margin: 0;
    font-size: 27px;
    font-weight: 600;
    letter-spacing: -.025em;
}
.delivery-ops .page-heading p {
    margin: 2px 0 0;
    color: var(--do-muted);
    font-size: 14px;
}
.delivery-ops .page-actions {
    display: flex;
    align-items: center;
    gap: 9px;
}
.delivery-ops .do-btn {
    min-height: 36px;
    padding: 0 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 1px solid #cfd8df;
    border-radius: 8px;
    background: #fff;
    color: var(--do-ink);
    font-family: inherit;
    font-weight: 600;
    font-size: 12px;
    text-decoration: none;
    cursor: pointer;
}
.delivery-ops .do-btn:hover { border-color: #9baab5; background: #fbfcfc; }
.delivery-ops .do-btn:focus-visible,
.delivery-ops .do-text-action:focus-visible,
.delivery-ops .dh-switch:focus-visible {
    outline: 3px solid rgba(8, 122, 91, .2);
    outline-offset: 2px;
}
.delivery-ops .do-btn-primary {
    color: #fff;
    border-color: var(--do-brand);
    background: var(--do-brand);
}
.delivery-ops .do-btn-primary:hover {
    color: #fff;
    border-color: var(--do-brand-dark);
    background: var(--do-brand-dark);
}
.delivery-ops .do-btn-danger {
    color: var(--do-danger);
    border-color: #f1b8b3;
    background: #fff;
}
.delivery-ops .do-btn:disabled { opacity: .48; cursor: not-allowed; }
.delivery-ops .do-btn .do-icon { width: 15px; height: 15px; }
.delivery-ops .do-text-action {
    padding: 0;
    border: 0;
    color: var(--do-brand);
    background: transparent;
    font-size: 12px;
    font-weight: 600;
    cursor: pointer;
    text-decoration: none;
}
.delivery-ops .do-text-action:hover { text-decoration: underline; }
.delivery-ops.dh {
    display: flex;
    flex-direction: column;
    gap: 0;
}
.delivery-ops .do-surface {
    border: 1px solid var(--do-line);
    border-radius: 11px;
    background: #fff;
    box-shadow: var(--do-shadow);
}
.delivery-ops .route-strip { padding: 0; overflow: hidden; }
.delivery-ops .route-header {
    min-height: 62px;
    padding: 13px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-bottom: 1px solid var(--do-line-soft);
}
.delivery-ops .title-row { display: flex; align-items: center; gap: 10px; }
.delivery-ops .title-icon {
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    flex: 0 0 34px;
    border-radius: 9px;
    color: var(--do-brand);
    background: var(--do-brand-soft);
}
.delivery-ops .title-icon svg {
    width: 17px;
    height: 17px;
    display: block;
    overflow: visible;
}
.delivery-ops .route-header h3 { margin: 0; font-size: 16px; letter-spacing: -.01em; }
.delivery-ops .route-header p { margin: 2px 0 0; color: var(--do-muted); font-size: 11px; }
.delivery-ops .header-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
.delivery-ops .route-health {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-shrink: 0;
}
.delivery-ops .health-copy {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    justify-content: center;
    gap: 1px;
    min-width: 0;
}
.delivery-ops .health-copy strong {
    color: var(--do-brand-dark);
    font-size: 12px;
    line-height: 1.2;
    text-align: right;
}
.delivery-ops .health-copy span {
    color: var(--do-muted);
    font-size: 10px;
    line-height: 1.2;
    text-align: right;
    white-space: nowrap;
}
.delivery-ops .health-icon {
    width: 30px;
    height: 30px;
    flex: 0 0 30px;
    display: grid;
    place-items: center;
    border-radius: 50%;
    color: #fff;
    background: var(--do-brand);
}
.delivery-ops .health-icon svg { width: 16px; height: 16px; display: block; }
.delivery-ops .route-health.is-blocked .health-copy strong { color: var(--do-danger); }
.delivery-ops .route-health.is-blocked .health-icon { background: var(--do-danger); }
.delivery-ops .route-health.is-progress .health-copy strong { color: var(--do-amber); }
.delivery-ops .flow-shell { padding: 18px; }
.delivery-ops .flow-shell [id$="route-detail-region"]:empty {
    display: none;
}
.delivery-ops .flow {
    display: grid;
    grid-template-columns: minmax(180px, 1fr) 44px minmax(180px, 1fr) 44px minmax(180px, 1fr) 44px minmax(180px, 1fr);
    align-items: stretch;
    gap: 0;
    width: 100%;
    min-width: 0;
}
.delivery-ops .stage {
    min-width: 0;
    min-height: 78px;
    padding: 10px 12px;
    display: grid;
    grid-template-columns: 44px minmax(0, 1fr);
    align-items: center;
    gap: 10px;
    border: 1px solid var(--do-line);
    border-radius: 10px;
    background: #fff;
    text-align: left;
    color: inherit;
    cursor: pointer;
    appearance: none;
    -webkit-appearance: none;
    font: inherit;
    line-height: inherit;
    transition: border-color .16s ease, background .16s ease, box-shadow .16s ease;
}
.delivery-ops .stage > span:last-child { min-width: 0; }
.delivery-ops .stage:hover { border-color: #9fcbbb; background: #fbfdfc; }
.delivery-ops .stage[aria-expanded="true"] {
    border-color: #74bea4;
    background: #f4fbf8;
    box-shadow: 0 0 0 1px rgba(8, 122, 91, .08);
}
.delivery-ops a.stage {
    text-decoration: none;
    color: inherit;
}
.delivery-ops .stage.is-current {
    border-color: #74bea4;
    background: #f4fbf8;
    box-shadow: 0 0 0 1px rgba(8, 122, 91, .08);
}
.delivery-ops .stage.is-current .stage-status { color: var(--do-brand); }
.delivery-ops .stage.is-static { cursor: default; }
.delivery-ops .stage.is-static.is-muted:hover {
    border-color: #dce4e8;
    background: #fafbfc;
}
.delivery-ops.dh-setup-progress {
    overflow: hidden;
    border: 1px solid var(--do-line);
    border-radius: 13px;
    background: #fff;
    box-shadow: var(--do-shadow);
}
.delivery-ops.dh-setup-progress .flow-shell { padding: 16px 18px; }
.delivery-ops .stage.is-warning { border-color: #ecd79b; background: #fffdf8; }
.delivery-ops .stage.is-blocked { border-color: #ebb7b2; background: #fff9f8; }
.delivery-ops .stage.is-muted { border-color: #dce4e8; background: #fafbfc; }
.delivery-ops .stage-icon {
    position: relative;
    width: 44px;
    height: 44px;
    display: grid;
    place-items: center;
    border-radius: 12px;
    color: var(--do-brand);
    background: var(--do-brand-soft);
}
.delivery-ops .stage-icon .do-icon { width: 20px; height: 20px; }
.delivery-ops .stage-icon::after {
    content: "";
    position: absolute;
    right: -2px;
    bottom: -2px;
    width: 11px;
    height: 11px;
    border: 2px solid #fff;
    border-radius: 50%;
    background: var(--do-brand);
}
.delivery-ops .stage.is-warning .stage-icon { color: #a15c05; background: #fff2cc; }
.delivery-ops .stage.is-warning .stage-icon::after { background: #a15c05; }
.delivery-ops .stage.is-blocked .stage-icon { color: var(--do-danger); background: #fee9e7; }
.delivery-ops .stage.is-blocked .stage-icon::after { background: var(--do-danger); }
.delivery-ops .stage.is-muted .stage-icon { color: #687889; background: #eff3f5; }
.delivery-ops .stage.is-muted .stage-icon::after { background: #9aa7b3; }
.delivery-ops .stage-label { color: var(--do-muted); font-size: 10px; font-weight: 600; }
.delivery-ops .stage-value {
    display: block;
    overflow: hidden;
    margin-top: 1px;
    text-overflow: ellipsis;
    white-space: nowrap;
    font-size: 12px;
    font-weight: 600;
}
.delivery-ops .stage-status {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    margin-top: 4px;
    color: var(--do-brand);
    font-size: 10px;
    font-weight: 600;
}
.delivery-ops .stage-status::before {
    content: "";
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: currentColor;
}
.delivery-ops .stage.is-warning .stage-status { color: #a15c05; }
.delivery-ops .stage.is-blocked .stage-status { color: var(--do-danger); }
.delivery-ops .stage.is-muted .stage-status { color: #677789; }
.delivery-ops .optional-label {
    margin-left: 5px;
    padding: 1px 5px;
    border-radius: 99px;
    color: #5f7080;
    background: #edf1f4;
    font-size: 8px;
    font-weight: 600;
    letter-spacing: .03em;
    text-transform: uppercase;
}
.delivery-ops .connector {
    position: relative;
    height: 78px;
    display: flex;
    align-items: center;
    padding: 0 6px;
}
.delivery-ops .connector-line {
    position: relative;
    width: 100%;
    height: 2px;
    border-radius: 99px;
    background: #b7d8cc;
}
.delivery-ops .connector-line::after {
    content: "";
    position: absolute;
    top: 50%;
    right: 0;
    width: 8px;
    height: 8px;
    border-top: 2px solid var(--do-brand);
    border-right: 2px solid var(--do-brand);
    transform: translateY(-50%) rotate(45deg);
}
.delivery-ops .connector-pulse {
    position: absolute;
    top: 50%;
    left: 7px;
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: var(--do-brand);
    box-shadow: 0 0 0 3px rgba(8, 122, 91, .1);
    transform: translateY(-50%);
    animation: do-flow-pulse 2.8s ease-in-out infinite;
}
.delivery-ops .flow > .connector:nth-child(4) .connector-pulse { animation-delay: .35s; }
.delivery-ops .flow > .connector:nth-child(6) .connector-pulse { animation-delay: .7s; }
.delivery-ops .connector.is-muted .connector-line {
    height: 0;
    border-top: 2px dashed #bec8cf;
    background: transparent;
}
.delivery-ops .connector.is-muted .connector-line::after { border-color: #96a4af; }
.delivery-ops .connector.is-muted .connector-pulse { display: none; }
.delivery-ops .connector.is-blocked .connector-line { background: #e9aaa5; }
.delivery-ops .connector.is-blocked .connector-line::after { border-color: var(--do-danger); }
.delivery-ops .connector.is-blocked .connector-pulse {
    background: var(--do-danger);
    box-shadow: 0 0 0 3px rgba(180, 35, 24, .1);
}
@keyframes do-flow-pulse {
    0%, 15% { left: 7px; opacity: 0; }
    25% { opacity: 1; }
    75% { opacity: 1; }
    90%, 100% { left: calc(100% - 8px); opacity: 0; }
}
@media (prefers-reduced-motion: reduce) {
    .delivery-ops .connector-pulse { display: none; }
    .delivery-ops .stage { transition: none; }
}
.delivery-ops .detail-tray {
    margin: 10px 0 0;
    padding: 12px 14px;
    display: grid;
    grid-template-columns: 34px minmax(0, 1fr) auto;
    align-items: center;
    gap: 11px;
    border: 1px solid #cce3da;
    border-radius: 9px;
    background: #f7fbf9;
    animation: do-tray-in .16s ease both;
}
.delivery-ops .detail-icon {
    width: 32px;
    height: 32px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    color: var(--do-brand);
    background: #e1f3ec;
}
.delivery-ops .detail-icon .do-icon { width: 15px; height: 15px; }
.delivery-ops .detail-tray strong { display: block; font-size: 12px; }
.delivery-ops .detail-tray p { margin: 2px 0 0; color: var(--do-muted); font-size: 11px; }
.delivery-ops .tray-actions { display: flex; align-items: center; gap: 8px; }
@keyframes do-tray-in {
    from { opacity: 0; transform: translateY(-5px); }
}
.delivery-ops .route-legend {
    min-height: 35px;
    padding: 8px 18px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-top: 1px solid var(--do-line-soft);
    color: var(--do-muted);
    background: #fbfcfc;
    font-size: 11px;
}
.delivery-ops .legend-items { display: flex; align-items: center; gap: 14px; }
.delivery-ops .legend-item { display: flex; align-items: center; gap: 6px; }
.delivery-ops .legend-line {
    width: 26px;
    height: 2px;
    border-radius: 99px;
    background: #b7d8cc;
}
.delivery-ops .legend-line.is-optional {
    height: 0;
    border-top: 2px dashed #bec8cf;
    background: transparent;
}
.delivery-ops .do-pill {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    min-height: 22px;
    padding: 2px 9px;
    border-radius: 999px;
    font-size: 10px;
    font-weight: 600;
}
.delivery-ops .do-pill::before {
    content: "";
    width: 6px;
    height: 6px;
    border-radius: 999px;
    background: currentColor;
}
.delivery-ops .do-pill-ready { color: #06734f; background: #dff7ed; }
.delivery-ops .do-pill-muted { color: #526070; background: #edf1f4; }
.delivery-ops .do-pill-warn { color: #a15c05; background: #fff0c7; }
.delivery-ops .do-pill-danger { color: #b42318; background: #fee4e2; }
.delivery-ops .do-pill-blue { color: #175cd3; background: #e8f1ff; }
.delivery-ops .do-pill.no-dot::before { display: none; }
.delivery-ops .do-count {
    min-width: 23px;
    height: 23px;
    display: inline-grid;
    place-items: center;
    border-radius: 999px;
    color: #475569;
    background: #edf1f4;
    font-size: 11px;
    font-weight: 600;
}
.delivery-ops .health-banner {
    margin: 0;
    min-height: 48px;
    padding: 10px 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
    border: 1px solid #f2d087;
    border-radius: 10px;
    background: var(--do-amber-bg);
    color: #7a4510;
}
.delivery-ops .health-banner.is-danger {
    border-color: #f2b8b5;
    background: var(--do-danger-bg);
    color: #912018;
}
.delivery-ops .health-banner strong { display: block; font-size: 12px; }
.delivery-ops .health-banner span { color: inherit; font-size: 11px; }
.delivery-ops #health-region { margin: 8px 0 0; }
.delivery-ops .management-grid {
    margin: 8px 0 0;
    display: grid;
    grid-template-columns: minmax(0, 2fr) minmax(320px, 1fr);
    gap: 14px;
    align-items: stretch;
}
.delivery-ops .panel-head {
    min-height: 66px;
    padding: 14px 16px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    border-bottom: 1px solid var(--do-line-soft);
}
.delivery-ops .panel-head h3 { margin: 0; font-size: 17px; }
.delivery-ops .panel-head p { margin: 2px 0 0; color: var(--do-muted); font-size: 11px; }
.delivery-ops .panel-title-row {
    display: flex;
    align-items: center;
    gap: 8px;
}
.delivery-ops .area-list { padding: 10px; }
.delivery-ops .area {
    overflow: hidden;
    border: 1px solid var(--do-line);
    border-radius: 10px;
}
.delivery-ops .area + .area { margin-top: 10px; }
.delivery-ops .area-top {
    min-height: 72px;
    padding: 11px 12px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    background: #fff;
}
.delivery-ops .area.is-inactive { opacity: .72; }
.delivery-ops .area-main { min-width: 0; display: flex; align-items: center; gap: 11px; }
.delivery-ops .flag-box {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    overflow: hidden;
    border: 1px solid var(--do-line-soft);
    border-radius: 8px;
    background: #f8fafb;
    font-size: 21px;
}
.delivery-ops .flag-svg {
    width: 26px;
    height: 18px;
    border-radius: 2px;
    box-shadow: 0 0 0 1px rgba(15, 23, 42, .08);
}
.delivery-ops .flag-letters {
    color: var(--do-muted);
    font-size: 11px;
    font-weight: 600;
    letter-spacing: .04em;
}
.delivery-ops .area-main strong { font-size: 14px; }
.delivery-ops .area-main small { display: block; color: var(--do-muted); font-size: 10px; }
.delivery-ops .area-title-row { display: flex; align-items: center; gap: 8px; }
.delivery-ops .area-actions,
.delivery-ops .option-actions { display: flex; align-items: center; gap: 8px; flex-shrink: 0; }
.delivery-ops .compact-btn { min-height: 31px; padding: 0 10px; }
.delivery-ops .dh-menu-trigger {
    list-style: none;
    width: 32px;
    height: 32px;
    border: 0;
    background: transparent;
    color: #64748b;
}
.delivery-ops .dh-menu-trigger::-webkit-details-marker { display: none; }
.delivery-ops .dh-menu-trigger:hover { background: #f3f5f6; }
.delivery-ops .dh-menu-panel {
    top: 36px;
    right: 0;
    width: 155px;
    min-width: 155px;
    margin-top: 0;
    padding: 5px;
    border-radius: 9px;
    z-index: 8;
}
.delivery-ops .dh-switch {
    position: relative;
    width: 38px;
    height: 22px;
    padding: 0;
    border: 0;
    border-radius: 999px;
    background: #c9d2d9;
    box-shadow: none;
}
.delivery-ops .dh-switch::after {
    top: 3px;
    left: 3px;
    width: 16px;
    height: 16px;
    border: 0;
    background: #fff;
    box-shadow: 0 1px 2px rgba(0, 0, 0, .22);
}
.delivery-ops .dh-switch.is-on { background: var(--do-brand); }
.delivery-ops .dh-switch.is-on::after {
    left: 19px;
    transform: none;
}
.delivery-ops .options-table { border-top: 1px solid var(--do-line-soft); }
.delivery-ops div.dh-option-row {
    display: block;
    width: 100%;
    min-width: 0;
    margin: 0;
    padding: 0;
    border: 0;
    background: transparent;
    grid-template-columns: none;
    gap: 0;
}
.delivery-ops .options-head,
.delivery-ops .option-row {
    display: grid;
    grid-template-columns: minmax(140px, 1.25fr) minmax(88px, .7fr) minmax(118px, .85fr) minmax(220px, 1.2fr);
    align-items: center;
    gap: 12px;
    width: 100%;
}
.delivery-ops .options-head {
    min-height: 38px;
    padding: 0 12px;
    color: #64748b;
    background: #f8fafb;
    font-size: 9px;
    font-weight: 600;
    letter-spacing: .045em;
    text-transform: uppercase;
}
.delivery-ops .option-row {
    min-height: 70px;
    padding: 11px 12px;
    border-top: 1px solid var(--do-line-soft);
}
.delivery-ops .option-row:first-of-type { border-top: 0; }
.delivery-ops .dh-option-row + .dh-option-row .option-row { border-top: 1px solid var(--do-line-soft); }
.delivery-ops .options-head + .dh-option-row .option-row { border-top: 0; }
.delivery-ops .option-row.is-off { color: #657384; background: #fbfcfc; }
.delivery-ops .option-name { font-size: 13px; font-weight: 600; }
.delivery-ops .option-sub { color: var(--do-muted); font-size: 10px; }
.delivery-ops .metric-label { display: none; color: var(--do-muted); font-size: 9px; text-transform: uppercase; }
.delivery-ops .metric-value { font-size: 11px; font-weight: 500; white-space: nowrap; }
.delivery-ops .option-availability {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 9px;
    min-width: 0;
    flex-wrap: nowrap;
}
.delivery-ops .option-availability .switch-label {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #536273;
    font-size: 10px;
    white-space: nowrap;
}
.delivery-ops .add-inline {
    width: 100%;
    padding: 11px 13px;
    border: 0;
    border-top: 1px solid var(--do-line-soft);
    color: var(--do-brand);
    background: #fff;
    text-align: left;
    font-weight: 600;
    font-size: 11px;
    cursor: pointer;
}
.delivery-ops .add-inline:hover { background: #fbfdfc; }
.delivery-ops .empty-state { padding: 54px 24px; text-align: center; }
.delivery-ops .empty-state .empty-icon {
    width: 48px;
    height: 48px;
    margin: auto;
    display: grid;
    place-items: center;
    border-radius: 999px;
    color: var(--do-brand);
    background: var(--do-brand-soft);
}
.delivery-ops .empty-state h4 { margin: 12px 0 4px; }
.delivery-ops .empty-state p {
    max-width: 370px;
    margin: 0 auto 14px;
    color: var(--do-muted);
    font-size: 12px;
}
.delivery-ops .config-panel { overflow: hidden; }
.delivery-ops .config-section { position: relative; padding: 14px 16px; border-top: 1px solid var(--do-line-soft); }
.delivery-ops .config-section:first-of-type { border-top: 0; }
.delivery-ops .config-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 10px;
}
.delivery-ops .config-id { display: flex; align-items: center; gap: 10px; min-width: 0; }
.delivery-ops .panel-head .do-text-action { white-space: nowrap; font-size: 12px; }
.delivery-ops .round-icon {
    flex: 0 0 auto;
    width: 34px;
    height: 34px;
    display: grid;
    place-items: center;
    border-radius: 999px;
    color: #42536a;
    background: #f1f4f6;
}
.delivery-ops .round-icon .do-icon { width: 16px; height: 16px; }
.delivery-ops .round-icon.fedex-mark,
.delivery-ops .fedex-mark {
    flex: 0 0 auto;
    width: auto;
    min-width: 46px;
    height: 22px;
    padding: 0 2px;
    border-radius: 4px;
    overflow: visible;
    color: #4d148c;
    background: transparent;
    font-size: 13px;
    font-weight: 600;
    letter-spacing: -.04em;
    line-height: 1;
    white-space: nowrap;
}
.delivery-ops .fedex-mark span { color: #ff6200; }
.delivery-ops .fedex-mark img {
    display: block;
    width: 44px;
    height: 16px;
    max-width: none;
    object-fit: contain;
}
.delivery-ops .config-section h4 { margin: 1px 0 7px; font-size: 12px; }
.delivery-ops .config-main { margin-left: 44px; }
.delivery-ops #delivery-fedex .config-main { margin-left: 56px; }
.delivery-ops .config-main strong { display: block; font-size: 12px; }
.delivery-ops .config-main p { margin: 2px 0; color: var(--do-muted); font-size: 10px; }
.delivery-ops .chips { margin-top: 7px; display: flex; flex-wrap: wrap; gap: 6px; }
.delivery-ops .cap-line {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 7px;
    color: var(--do-brand-dark);
    font-size: 10px;
    font-weight: 600;
}
.delivery-ops .cap-line .do-icon { width: 14px; height: 14px; }
.delivery-ops .dh-cap-grid {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border: 0;
    display: block;
    grid-template-columns: none;
    gap: 0;
}
.delivery-ops .dh-cap {
    padding: 8px 9px;
    border: 1px solid var(--do-line-soft);
    border-radius: 8px;
    background: #f8fafb;
}
.delivery-ops .dh-cap-label { margin: 0; color: var(--do-muted); font-size: 9px; text-transform: uppercase; letter-spacing: .04em; }
.delivery-ops .dh-cap-value { margin: 3px 0 0; font-size: 11px; font-weight: 600; }
.delivery-ops .recommendation {
    margin-top: 11px;
    padding: 10px 11px;
    display: flex;
    align-items: flex-start;
    gap: 8px;
    border: 1px solid #f2d087;
    border-radius: 8px;
    color: #93470a;
    background: var(--do-amber-bg);
}
.delivery-ops .recommendation .do-icon { flex: 0 0 auto; width: 15px; height: 15px; margin-top: 1px; }
.delivery-ops .recommendation p { margin: 0 0 3px; color: inherit; }
.delivery-ops .provider-state {
    padding: 11px;
    border: 1px dashed var(--do-line);
    border-radius: 9px;
    background: #fafcfc;
}
.delivery-ops .provider-state strong { font-size: 12px; }
.delivery-ops .provider-state p { margin: 3px 0 10px; }
.delivery-ops .provider-state.is-danger {
    border-color: #efb9b4;
    background: #fff8f7;
}
.delivery-ops .do-spinner {
    width: 17px;
    height: 17px;
    border: 2px solid #bcded3;
    border-top-color: var(--do-brand);
    border-radius: 999px;
    animation: do-spin .8s linear infinite;
}
@keyframes do-spin { to { transform: rotate(360deg); } }
.delivery-ops .preview-bar {
    margin: 8px 0 0;
    min-height: 66px;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
}
.delivery-ops .preview-copy { display: flex; align-items: center; gap: 11px; }
.delivery-ops .preview-copy h3 { margin: 0; font-size: 13px; }
.delivery-ops .preview-copy p { margin: 2px 0 0; color: var(--do-muted); font-size: 10px; }
.delivery-ops .preview-actions { display: flex; align-items: center; gap: 12px; }
.delivery-ops .trouble-link { padding-left: 12px; border-left: 1px solid var(--do-line); }
.delivery-ops .do-orphan {
    margin: 0 0 12px;
    padding: 11px 13px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    border: 1px solid var(--do-line);
    border-radius: 10px;
    background: #fff;
    color: var(--do-text);
    font-size: 12px;
}
.delivery-ops .dh-setup-hero {
    padding: 0;
    overflow: hidden;
    border: 1px solid var(--do-line);
    border-radius: 12px;
    background: #fff;
    box-shadow: var(--do-shadow);
}
.delivery-ops .dh-setup-intro {
    min-height: 62px;
    padding: 16px 18px 14px;
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 16px;
    border-bottom: 1px solid var(--do-line-soft);
}
.delivery-ops .dh-setup-title { margin: 0; font-size: 27px; letter-spacing: -.025em; }
.delivery-ops .dh-setup-lead { margin: 6px 0 0; color: var(--do-muted); font-size: 14px; max-width: 46rem; }
.delivery-ops .dh-setup-hero .flow-shell { padding: 16px 18px 8px; }
.delivery-ops .dh-setup-hero .do-setup-origin { margin: 0 18px 4px; }
.delivery-ops .dh-setup-hero .dh-setup-foot {
    margin: 0;
    padding: 12px 18px 14px;
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}
.delivery-ops .dh-setup-hero .route-legend {
    margin: 0;
    border-radius: 0;
}
.delivery-ops .dh-step-grid { margin-top: 18px; }
.delivery-ops .dh-setup-note { margin: 0; color: var(--do-muted); max-width: 42rem; }
.delivery-ops .do-setup-origin {
    margin-top: 16px;
    padding: 12px 14px;
    border: 1px solid var(--do-line-soft);
    border-radius: 10px;
    background: #f8fafb;
}
.delivery-ops .do-setup-origin strong { display: block; font-size: 13px; }
.delivery-ops .do-setup-origin p { margin: 0 0 6px; color: var(--do-muted); font-size: 11px; }
.delivery-ops-check-row {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 8px 0;
}
.delivery-ops-check-row .do-icon { width: 16px; height: 16px; margin-top: 2px; color: var(--do-brand); }
.delivery-ops-check-row.is-alert .do-icon { color: var(--do-amber); }
.delivery-ops-check-row strong { display: block; font-size: 12px; }
.delivery-ops-check-row small { color: #64748b; font-size: 11px; }
.delivery-ops-note {
    padding: 10px;
    border-radius: 8px;
    color: #365b91;
    background: #eff8ff;
    font-size: 12px;
}
@media (max-width: 1100px) {
    .delivery-ops .flow {
        grid-template-columns: minmax(130px, 1fr) 34px minmax(130px, 1fr) 34px minmax(130px, 1fr) 34px minmax(130px, 1fr);
    }
    .delivery-ops .stage { grid-template-columns: 38px minmax(0, 1fr); gap: 8px; }
    .delivery-ops .stage-icon { width: 38px; height: 38px; }
    .delivery-ops .connector { padding: 0 5px; }
    .delivery-ops .management-grid { grid-template-columns: 1fr; }
}
@media (max-width: 780px) {
    .delivery-ops .page-heading { align-items: flex-start; flex-direction: column; }
    .delivery-ops .page-actions { width: 100%; }
    .delivery-ops .page-actions .do-btn { flex: 1; }
    .delivery-ops .route-header,
    .delivery-ops .dh-setup-intro { align-items: flex-start; flex-direction: column; }
    .delivery-ops .header-actions { width: 100%; justify-content: space-between; flex-wrap: wrap; }
    .delivery-ops .health-copy strong,
    .delivery-ops .health-copy span { text-align: left; }
    .delivery-ops .flow { grid-template-columns: 1fr; }
    .delivery-ops .stage { min-height: 70px; }
    .delivery-ops .connector {
        width: 38px;
        height: 34px;
        padding: 0;
        justify-self: start;
        margin-left: 12px;
    }
    .delivery-ops .connector-line { width: 2px; height: 100%; margin: 0 auto; }
    .delivery-ops .connector-line::after {
        top: auto;
        right: 50%;
        bottom: 0;
        transform: translateX(50%) rotate(135deg);
    }
    .delivery-ops .connector-pulse {
        top: 5px;
        left: 50%;
        transform: translateX(-50%);
        animation: do-flow-pulse-vertical 2.8s ease-in-out infinite;
    }
    .delivery-ops .connector.is-muted .connector-line {
        width: 0;
        height: 100%;
        border-top: 0;
        border-left: 2px dashed #bec8cf;
    }
    @keyframes do-flow-pulse-vertical {
        0%, 15% { top: 5px; opacity: 0; }
        25% { opacity: 1; }
        75% { opacity: 1; }
        90%, 100% { top: calc(100% - 7px); opacity: 0; }
    }
    .delivery-ops .detail-tray { grid-template-columns: 32px minmax(0, 1fr); }
    .delivery-ops .tray-actions { grid-column: 1 / -1; padding-left: 43px; }
    .delivery-ops .route-legend { align-items: flex-start; flex-direction: column; }
    .delivery-ops .options-head { display: none; }
    .delivery-ops .option-row { grid-template-columns: 1fr 1fr; }
    .delivery-ops .option-row > :first-child { grid-column: 1 / -1; }
    .delivery-ops .metric-label { display: block; }
    .delivery-ops .option-availability { grid-column: 1 / -1; }
    .delivery-ops .preview-bar { align-items: flex-start; flex-direction: column; }
    .delivery-ops .preview-actions { width: 100%; justify-content: space-between; }
}
</style>
