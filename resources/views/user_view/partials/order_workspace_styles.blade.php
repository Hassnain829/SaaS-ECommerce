{{-- Scoped order workspace styles from merchant order-details preview. Does not restyle sidebar/topbar. --}}
<style>
.order-workspace {
    --ow-bg: #f5f6f7;
    --surface: #fff;
    --line: #dfe4e8;
    --line-dark: #cbd3da;
    --text: #17202e;
    --muted: #667085;
    --subtle: #8a94a4;
    --green: #087a61;
    --green-dark: #05624e;
    --green-soft: #eaf8f3;
    --amber: #a96200;
    --amber-soft: #fff3d5;
    --blue: #1268ce;
    --blue-soft: #edf5ff;
    --red: #bf2d27;
    --red-soft: #fff0ef;
    --shadow: 0 1px 2px rgba(15,23,42,.04);
    --radius: 12px;
    color: var(--text);
}
.order-workspace .tab,
.order-workspace .btn,
.order-workspace .method-switch button,
.order-workspace .subtabs button,
.order-workspace .menu button,
.order-workspace .close-button {
    cursor: pointer;
    font: inherit;
}
.order-workspace .panel[hidden] { display: none !important; }
.order-workspace [hidden] { display: none !important; }
.order-workspace .card-head select {
    width: 180px;
    min-height: 38px;
    padding: 6px 10px;
    border: 1px solid var(--line-dark);
    border-radius: 8px;
    background: white;
    color: var(--text);
}
.order-workspace .breadcrumb a {
    color: inherit;
    text-decoration: none;
}
.order-workspace .breadcrumb a:hover {
    color: var(--green-dark);
    text-decoration: underline;
}


.order-workspace .page-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 22px;
            margin-bottom: 20px;
        }.order-workspace .breadcrumb {
            margin-bottom: 5px;
            color: var(--muted);
            font-size: 13px;
        }.order-workspace .order-title-row {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 9px;
        }.order-workspace .order-title-row h2 {
            margin: 0;
            font-size: clamp(24px, 2vw, 31px);
            line-height: 1.15;
            letter-spacing: -.035em;
        }.order-workspace .order-meta {
            margin: 5px 0 0;
            color: var(--muted);
        }.order-workspace .head-actions {
            position: relative;
            display: flex;
            gap: 8px;
        }.order-workspace .btn {
            display: inline-flex;
            min-height: 38px;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 8px 13px;
            border: 1px solid var(--line-dark);
            border-radius: 8px;
            background: white;
            color: #263244;
            font-weight: 700;
            text-decoration: none;
        }.order-workspace .btn:hover { background: #f7f9f8; }.order-workspace .btn:disabled { cursor: not-allowed; opacity: .55; }.order-workspace .btn-primary {
            border-color: var(--green);
            background: var(--green);
            color: white;
        }.order-workspace .btn-primary:hover {
            background: var(--green-dark);
        }.order-workspace .btn-danger {
            border-color: #efbbb7;
            background: white;
            color: var(--red);
        }.order-workspace .btn-danger:hover { background: var(--red-soft); }.order-workspace .btn-link {
            min-height: auto;
            padding: 2px;
            border: 0;
            background: transparent;
            color: var(--green-dark);
        }.order-workspace .btn-link:hover {
            background: transparent;
            text-decoration: underline;
            text-underline-offset: 3px;
        }.order-workspace .btn-block { width: 100%; }.order-workspace .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 9px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 750;
        }.order-workspace .badge::before {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: currentColor;
            content: "";
        }.order-workspace .badge-green {
            background: var(--green-soft);
            color: var(--green-dark);
        }.order-workspace .badge-amber {
            background: var(--amber-soft);
            color: var(--amber);
        }.order-workspace .badge-blue {
            background: var(--blue-soft);
            color: var(--blue);
        }.order-workspace .badge-gray {
            background: #edf0f2;
            color: #667085;
        }.order-workspace .badge-red {
            background: var(--red-soft);
            color: var(--red);
        }.order-workspace .menu {
            position: absolute;
            top: 45px;
            right: 0;
            z-index: 30;
            width: 205px;
            padding: 6px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: white;
            box-shadow: 0 14px 36px rgba(15,23,42,.14);
        }.order-workspace .menu button {
            width: 100%;
            padding: 9px 10px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: var(--text);
            text-align: left;
        }.order-workspace .menu button:hover { background: #f4f6f5; }.order-workspace .menu button.danger { color: var(--red); }.order-workspace .lifecycle {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            margin-bottom: 12px;
            padding: 0 28px;
        }.order-workspace .life-step {
            position: relative;
            min-width: 0;
            color: var(--muted);
            text-align: center;
        }.order-workspace .life-step:not(:last-child)::after {
            position: absolute;
            top: 10px;
            left: calc(50% + 13px);
            width: calc(100% - 26px);
            height: 2px;
            background: #d8dde1;
            content: "";
        }.order-workspace .life-step.complete:not(:last-child)::after {
            background: var(--green);
        }.order-workspace .life-dot {
            position: relative;
            z-index: 2;
            display: grid;
            width: 22px;
            height: 22px;
            margin: 0 auto 6px;
            place-items: center;
            border: 2px solid #d3d9de;
            border-radius: 50%;
            background: var(--bg);
            color: white;
            font-size: 11px;
        }.order-workspace .life-step.complete .life-dot {
            border-color: var(--green);
            background: var(--green);
        }.order-workspace .life-step.current .life-dot {
            border: 3px solid var(--green);
            background: white;
            box-shadow: 0 0 0 4px #d9f2e9;
        }.order-workspace .life-step strong {
            display: block;
            color: #465266;
            font-size: 12px;
            font-weight: 650;
        }.order-workspace .life-step.current strong {
            color: var(--green-dark);
            font-weight: 800;
        }.order-workspace .life-step small {
            display: block;
            margin-top: 1px;
            color: var(--subtle);
            font-size: 10px;
        }.order-workspace .workspace {
            border-top: 1px solid var(--line);
        }.order-workspace .tabs {
            display: flex;
            gap: 5px;
            overflow-x: auto;
            overflow-y: hidden;
            border-bottom: 1px solid var(--line);
            scrollbar-width: none;
        }.order-workspace .tabs::-webkit-scrollbar {
            display: none;
        }.order-workspace .tab {
            position: relative;
            min-height: 49px;
            padding: 0 14px;
            border: 0;
            background: transparent;
            color: #536176;
            font-weight: 700;
            white-space: nowrap;
        }.order-workspace .tab:hover { color: var(--green-dark); }.order-workspace .tab.active {
            color: var(--green-dark);
        }.order-workspace .tab.active::after {
            position: absolute;
            left: 9px;
            right: 9px;
            bottom: 0;
            height: 2px;
            background: var(--green);
            content: "";
        }.order-workspace .tab-count {
            margin-left: 5px;
            padding: 2px 6px;
            border-radius: 999px;
            background: #e9edef;
            font-size: 11px;
        }.order-workspace .panel {
            padding-top: 14px;
        }.order-workspace .overview-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.85fr) minmax(300px, .95fr);
            gap: 14px;
        }.order-workspace .two-column {
            display: grid;
            grid-template-columns: minmax(0, 1.55fr) minmax(320px, .85fr);
            gap: 14px;
        }.order-workspace .stack {
            display: grid;
            align-content: start;
            gap: 14px;
        }.order-workspace .card {
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: var(--surface);
            box-shadow: var(--shadow);
        }.order-workspace .card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 14px;
            padding: 15px 17px;
            border-bottom: 1px solid var(--line);
        }.order-workspace .card-head.no-border { border-bottom: 0; }.order-workspace .card-head h3,
.order-workspace .card-head h4 {
            margin: 0;
            letter-spacing: -.015em;
        }.order-workspace .card-head h3 { font-size: 16px; }.order-workspace .card-head h4 { font-size: 14px; }.order-workspace .card-head p {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 12px;
        }.order-workspace .card-body { padding: 16px 17px; }.order-workspace .order-table {
            width: 100%;
            border-collapse: collapse;
        }.order-workspace .order-table th {
            padding: 8px 10px;
            background: #f4f6f7;
            color: #566277;
            font-size: 11px;
            text-align: left;
            text-transform: uppercase;
        }.order-workspace .order-table td {
            padding: 12px 10px;
            border-bottom: 1px solid var(--line);
            vertical-align: middle;
        }.order-workspace .order-table th:not(:first-child),
.order-workspace .order-table td:not(:first-child) {
            text-align: right;
        }.order-workspace .product-cell {
            display: flex;
            align-items: center;
            gap: 11px;
            text-align: left;
        }.order-workspace .product-thumb {
            position: relative;
            display: grid;
            width: 49px;
            height: 58px;
            flex: 0 0 auto;
            place-items: center;
            overflow: hidden;
            border: 1px solid #d4bf7e;
            border-radius: 7px;
            background: #f7c93e;
            color: #7d2900;
            font-size: 10px;
            font-weight: 900;
            text-align: center;
        }.order-workspace .product-thumb::after {
            position: absolute;
            inset: auto 6px 7px;
            height: 4px;
            border-radius: 10px;
            background: #c84116;
            content: "";
        }.order-workspace .product-name strong,
.order-workspace .product-name span {
            display: block;
        }.order-workspace .product-name span {
            color: var(--muted);
            font-size: 12px;
        }.order-workspace .totals {
            width: min(330px, 100%);
            margin: 10px 0 0 auto;
        }.order-workspace .total-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
            padding: 4px 0;
            color: var(--muted);
        }.order-workspace .total-row.grand {
            margin-top: 5px;
            padding-top: 10px;
            border-top: 1px solid var(--line);
            color: var(--text);
            font-size: 16px;
            font-weight: 800;
        }.order-workspace .total-row.grand span:last-child {
            color: var(--green-dark);
        }.order-workspace .ready-strip {
            display: flex;
            align-items: center;
            gap: 11px;
            margin-bottom: 15px;
            padding: 11px 13px;
            border: 1px solid #c8eadf;
            border-radius: 9px;
            background: var(--green-soft);
            color: var(--green-dark);
        }.order-workspace .ready-strip strong,
.order-workspace .ready-strip span {
            display: block;
        }.order-workspace .ready-strip span {
            color: #55776d;
            font-size: 12px;
        }.order-workspace .fulfillment-facts {
            display: grid;
            grid-template-columns: repeat(3, minmax(0,1fr));
            gap: 12px;
            margin-bottom: 14px;
        }.order-workspace .fact {
            display: flex;
            gap: 9px;
        }.order-workspace .fact strong,
.order-workspace .fact span {
            display: block;
        }.order-workspace .fact strong {
            color: var(--muted);
            font-size: 11px;
        }.order-workspace .fact span {
            margin-top: 2px;
            font-size: 12px;
        }.order-workspace .fulfillment-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }.order-workspace .timeline {
            margin: 0;
            padding: 2px 0 2px 8px;
            list-style: none;
        }.order-workspace .timeline li {
            position: relative;
            display: grid;
            grid-template-columns: minmax(0,1fr) auto;
            gap: 14px;
            padding: 0 0 15px 26px;
        }.order-workspace .timeline li:not(:last-child)::before {
            position: absolute;
            top: 18px;
            bottom: 0;
            left: 8px;
            width: 1px;
            background: #cfe3dc;
            content: "";
        }.order-workspace .timeline-dot {
            position: absolute;
            top: 1px;
            left: 0;
            display: grid;
            width: 17px;
            height: 17px;
            place-items: center;
            border-radius: 50%;
            background: var(--green);
            color: white;
            font-size: 10px;
        }.order-workspace .timeline strong,
.order-workspace .timeline span {
            display: block;
        }.order-workspace .timeline span,
.order-workspace .timeline time {
            color: var(--muted);
            font-size: 11px;
        }.order-workspace .customer-row {
            display: flex;
            align-items: center;
            gap: 12px;
        }.order-workspace .customer-avatar {
            display: grid;
            width: 52px;
            height: 52px;
            place-items: center;
            border-radius: 50%;
            background: #dbf2e9;
            color: var(--green-dark);
            font-size: 17px;
            font-weight: 800;
        }.order-workspace .customer-row strong,
.order-workspace .customer-row span {
            display: block;
        }.order-workspace .customer-row span {
            color: var(--muted);
            font-size: 12px;
        }.order-workspace .divider {
            height: 1px;
            margin: 15px 0;
            background: var(--line);
        }.order-workspace .address {
            display: flex;
            gap: 10px;
        }.order-workspace .address p { margin: 0; }.order-workspace .button-pair {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            margin-top: 14px;
        }.order-workspace .detail-list {
            display: grid;
            gap: 10px;
            margin: 0;
        }.order-workspace .detail-row {
            display: flex;
            justify-content: space-between;
            gap: 18px;
        }.order-workspace .detail-row dt { color: var(--muted); }.order-workspace .detail-row dd {
            margin: 0;
            text-align: right;
            font-weight: 650;
        }.order-workspace .detail-row.total {
            padding-top: 10px;
            border-top: 1px solid var(--line);
            font-size: 16px;
            font-weight: 800;
        }.order-workspace .detail-row.total dd { color: var(--green-dark); }.order-workspace .card details {
            margin-top: 12px;
            border-top: 1px solid var(--line);
        }.order-workspace .card summary {
            padding-top: 11px;
            color: var(--green-dark);
            cursor: pointer;
            font-weight: 700;
        }.order-workspace .technical-details {
            display: grid;
            gap: 8px;
            margin-top: 10px;
            padding: 12px;
            border-radius: 8px;
            background: #f5f7f8;
            color: var(--muted);
            font-size: 11px;
        }.order-workspace .technical-details code {
            overflow-wrap: anywhere;
            color: #344054;
        }.order-workspace .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0,1fr));
            gap: 12px;
        }.order-workspace .form-grid.three {
            grid-template-columns: repeat(3, minmax(0,1fr));
        }.order-workspace .span-2 { grid-column: 1 / -1; }.order-workspace .section-title {
            margin: 0 0 12px;
            font-size: 14px;
        }.order-workspace .method-switch,
.order-workspace .subtabs {
            display: inline-flex;
            gap: 3px;
            padding: 3px;
            border: 1px solid var(--line);
            border-radius: 9px;
            background: #f3f5f6;
        }.order-workspace .method-switch button,
.order-workspace .subtabs button {
            min-height: 34px;
            padding: 6px 11px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            color: var(--muted);
            font-weight: 700;
        }.order-workspace .method-switch button.active,
.order-workspace .subtabs button.active {
            background: white;
            color: var(--green-dark);
            box-shadow: 0 1px 3px rgba(15,23,42,.1);
        }.order-workspace .info-box {
            margin-bottom: 14px;
            padding: 11px 13px;
            border: 1px solid #cbdff8;
            border-radius: 8px;
            background: var(--blue-soft);
            color: #315f99;
            font-size: 12px;
        }.order-workspace .warning-box {
            padding: 11px 13px;
            border: 1px solid #f2d58b;
            border-radius: 8px;
            background: #fff9e9;
            color: #8d5a10;
            font-size: 12px;
        }.order-workspace .rate-list {
            display: grid;
            gap: 8px;
            margin-top: 14px;
        }.order-workspace .rate-option {
            display: grid;
            grid-template-columns: auto minmax(0,1fr) auto;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 9px;
        }.order-workspace .rate-option:has(input:checked) {
            border-color: var(--green);
            background: var(--green-soft);
        }.order-workspace .rate-option input {
            width: 16px;
            min-height: 16px;
            margin: 0;
        }.order-workspace .rate-option strong,
.order-workspace .rate-option span {
            display: block;
        }.order-workspace .rate-option span {
            color: var(--muted);
            font-size: 11px;
        }.order-workspace .shipment-card {
            padding: 14px;
            border: 1px solid var(--line);
            border-radius: 9px;
        }.order-workspace .shipment-top {
            display: flex;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 10px;
        }.order-workspace .shipment-card p {
            margin: 3px 0;
            color: var(--muted);
            font-size: 12px;
        }.order-workspace .empty {
            padding: 34px 18px;
            color: var(--muted);
            text-align: center;
        }.order-workspace .after-sales-banner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            padding: 13px 15px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: white;
        }.order-workspace .after-sales-banner strong,
.order-workspace .after-sales-banner span {
            display: block;
        }.order-workspace .after-sales-banner span {
            color: var(--muted);
            font-size: 12px;
        }.order-workspace .summary-cards {
            display: grid;
            grid-template-columns: repeat(3,1fr);
            gap: 12px;
            margin-bottom: 14px;
        }.order-workspace .summary-card {
            padding: 15px;
            border: 1px solid var(--line);
            border-radius: 10px;
            background: white;
        }.order-workspace .summary-card span {
            display: block;
            color: var(--muted);
            font-size: 11px;
        }.order-workspace .summary-card strong {
            display: block;
            margin-top: 4px;
            font-size: 21px;
        }.order-workspace .history-list {
            display: grid;
            gap: 9px;
        }.order-workspace .history-record {
            display: flex;
            justify-content: space-between;
            gap: 14px;
            padding: 12px;
            border: 1px solid var(--line);
            border-radius: 8px;
        }.order-workspace .history-record p {
            margin: 2px 0 0;
            color: var(--muted);
            font-size: 12px;
        }.order-workspace dialog {
            width: min(500px, calc(100% - 28px));
            padding: 0;
            border: 1px solid var(--line);
            border-radius: 14px;
            color: var(--text);
            box-shadow: 0 24px 70px rgba(15,23,42,.23);
        }.order-workspace dialog::backdrop {
            background: rgba(15,23,42,.5);
            backdrop-filter: blur(2px);
        }.order-workspace .dialog-head {
            display: flex;
            justify-content: space-between;
            gap: 15px;
            padding: 18px 20px;
            border-bottom: 1px solid var(--line);
        }.order-workspace .dialog-head h3 { margin: 0; }.order-workspace .dialog-body { padding: 20px; }.order-workspace .dialog-actions {
            display: flex;
            justify-content: flex-end;
            gap: 8px;
            padding: 14px 20px;
            border-top: 1px solid var(--line);
            background: #f8faf9;
        }.order-workspace .close-button {
            width: 32px;
            height: 32px;
            border: 0;
            border-radius: 7px;
            background: transparent;
            font-size: 20px;
        }.order-workspace .toast {
            position: fixed;
            right: 22px;
            bottom: 22px;
            z-index: 100;
            max-width: 390px;
            padding: 12px 15px;
            border: 1px solid #bde2d4;
            border-radius: 9px;
            background: #effaf6;
            color: var(--green-dark);
            font-weight: 700;
            box-shadow: 0 15px 40px rgba(15,23,42,.15);
            opacity: 0;
            pointer-events: none;
            transform: translateY(8px);
            transition: .2s ease;
        }.order-workspace .toast.show {
            opacity: 1;
            transform: translateY(0);
        }
.order-workspace .product-thumb {
    border: 1px solid var(--line);
    background: #f3f5f6;
    color: var(--muted);
    font-weight: 700;
    font-size: 11px;
}
.order-workspace .product-thumb::after { content: none; display: none; }
.order-workspace .product-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.order-workspace .ow-form label { display: grid; gap: 5px; color: #344054; font-size: 12px; font-weight: 700; }
.order-workspace .ow-form textarea,
.order-workspace .ow-form input,
.order-workspace .ow-form select {
    width: 100%;
    min-height: 40px;
    padding: 9px 11px;
    border: 1px solid var(--line-dark);
    border-radius: 8px;
    background: white;
    color: var(--text);
}
.order-workspace .ow-form textarea { min-height: 92px; resize: vertical; }
.order-workspace dialog {
    width: min(500px, calc(100% - 28px));
    padding: 0;
    border: 1px solid var(--line);
    border-radius: 14px;
    color: var(--text);
    box-shadow: 0 24px 70px rgba(15,23,42,.23);
}
.order-workspace dialog::backdrop {
    background: rgba(15,23,42,.5);
    backdrop-filter: blur(2px);
}
@media (max-width: 1080px) {
    .order-workspace .overview-grid,
    .order-workspace .two-column { grid-template-columns: 1fr; }
    .order-workspace .overview-grid > aside { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .order-workspace .overview-grid > aside .after-sales-banner { grid-column: 1 / -1; }
}
@media (max-width: 780px) {
    .order-workspace .page-head { display: block; }
    .order-workspace .head-actions { margin-top: 14px; }
    .order-workspace .lifecycle { overflow-x: auto; overflow-y: hidden; grid-template-columns: repeat(5, 145px); padding: 0; }
    .order-workspace .overview-grid > aside,
    .order-workspace .fulfillment-facts,
    .order-workspace .summary-cards,
    .order-workspace .form-grid,
    .order-workspace .form-grid.three { grid-template-columns: 1fr; }
    .order-workspace .order-table th:nth-child(3),
    .order-workspace .order-table td:nth-child(3) { display: none; }
}
</style>
