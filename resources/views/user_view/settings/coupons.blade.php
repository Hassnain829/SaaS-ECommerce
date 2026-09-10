@extends('layouts.user.user-sidebar')

@section('title', 'Discounts — '.config('app.name'))

@php
    $currencyCode = strtoupper((string) ($currencyCode ?? 'USD'));
    $couponMetrics = $couponMetrics ?? [
        'total' => 0,
        'available' => 0,
        'status_counts' => ['active' => 0, 'scheduled' => 0, 'expired' => 0, 'inactive' => 0],
        'total_redemptions' => 0,
        'top_code' => null,
        'top_uses' => 0,
        'top_share' => 0,
        'spotlight' => null,
    ];
    $statusCounts = $couponMetrics['status_counts'];
    $statusTotal = max(1, (int) $couponMetrics['total']);
    $openCouponDrawer = $errors->any();
    $couponFormMode = old('coupon_form', 'add') === 'edit' ? 'edit' : 'add';
    $couponEditorPayload = $couponEditorPayload ?? [];
    $storeTimezone = (string) ($storeTimezone ?? 'UTC');
    $couponEditorId = (string) old('coupon_id', '');
    $couponEditorAction = route('settings.coupons.store');
    if ($couponFormMode === 'edit' && $couponEditorId !== '' && isset($couponEditorPayload[$couponEditorId]['update_url'])) {
        $couponEditorAction = $couponEditorPayload[$couponEditorId]['update_url'];
    }

    $formatMoney = function ($amount) use ($currencyCode): string {
        $value = (float) $amount;
        $decimals = fmod($value, 1.0) === 0.0 ? 0 : 2;

        return $currencyCode.' '.number_format($value, $decimals);
    };
    $formatPercent = function ($value): string {
        $trimmed = rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');

        return $trimmed.'% off';
    };
    $statusLabel = [
        'active' => 'Active',
        'scheduled' => 'Scheduled',
        'expired' => 'Expired',
        'inactive' => 'Inactive',
    ];
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="Discounts" lead="Create simple coupon codes for platform checkout.">
        @if ($canManageCoupons)
            <x-slot:actions>
                <button type="button" class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-hover" data-dc-open-add>
                    <span aria-hidden="true">+</span>
                    <span class="hidden sm:inline">Create discount</span>
                </button>
            </x-slot:actions>
        @endif
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="discounts-console settings-workspace-fluid">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="mb-4 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" role="alert">
                <ul class="list-disc space-y-1 pl-4">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @unless ($canManageCoupons)
            <div class="mb-4 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3 text-sm text-[#64748B]">
                You can view coupon codes, but you do not have permission to change them.
            </div>
        @endunless

        <div class="dc-intro">
            <h2>Discount overview</h2>
            <p>Manage promotional codes, eligibility, and usage in one place.</p>
        </div>

        <section class="dc-ribbon" aria-label="Coupon overview">
            <div class="dc-portfolio">
                <div class="dc-portfolio-main">
                    <div class="dc-portfolio-icon" aria-hidden="true">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none"><path d="M20 13 13 20 4 11V4h7z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/><circle cx="8" cy="8" r="1.3" fill="currentColor"/></svg>
                    </div>
                    <div class="dc-portfolio-copy">
                        <div class="dc-eyebrow">Coupon portfolio</div>
                        <div class="dc-portfolio-value">
                            <span class="dc-portfolio-count">{{ $couponMetrics['total'] }}</span>
                            <span class="dc-portfolio-unit">{{ \Illuminate\Support\Str::plural('code', $couponMetrics['total']) }}</span>
                        </div>
                        <div class="dc-portfolio-note">{{ $couponMetrics['available'] }} available now</div>
                    </div>
                </div>
                @php $spotlight = $couponMetrics['spotlight'] ?? null; @endphp
                @if (is_array($spotlight))
                    <div class="dc-portfolio-aside">
                        <div class="dc-eyebrow">{{ $spotlight['label'] }}</div>
                        @if (! empty($spotlight['code']))
                            <button class="dc-portfolio-code" type="button" data-copy-code="{{ $spotlight['code'] }}" aria-label="Copy {{ $spotlight['code'] }}">{{ $spotlight['code'] }}</button>
                        @endif
                        <div class="dc-portfolio-aside-detail">{{ $spotlight['detail'] }}</div>
                    </div>
                @endif
            </div>
            <div class="dc-status-block">
                <div class="dc-eyebrow">Status distribution</div>
                <div class="dc-status-track" aria-label="Coupon status distribution">
                    @foreach (['active', 'scheduled', 'expired', 'inactive'] as $statusKey)
                        <span class="dc-status-segment is-{{ $statusKey }}" style="width: {{ round(($statusCounts[$statusKey] / $statusTotal) * 100, 2) }}%"></span>
                    @endforeach
                </div>
                <div class="dc-legend">
                    @foreach (['active', 'scheduled', 'expired', 'inactive'] as $statusKey)
                        @if ($statusKey !== 'inactive' || $statusCounts['inactive'] > 0)
                            <span class="dc-legend-item">
                                <span class="dc-legend-dot is-{{ $statusKey }}"></span>
                                {{ $statusLabel[$statusKey] }}
                                <strong>{{ $statusCounts[$statusKey] }}</strong>
                            </span>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="dc-top-code">
                <div class="dc-eyebrow">Most redeemed</div>
                <div class="dc-top-row">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M8 21h8M12 17v4M7 4h10v5a5 5 0 0 1-10 0V4Z" stroke="#087f5b" stroke-width="1.8"/><path d="M7 4H4a3 3 0 0 0 3 3M17 4h3a3 3 0 0 1-3 3" stroke="#087f5b" stroke-width="1.8"/></svg>
                    @if ($couponMetrics['top_code'])
                        <button class="dc-code-chip" type="button" data-copy-code="{{ $couponMetrics['top_code'] }}" aria-label="Copy most redeemed coupon code">{{ $couponMetrics['top_code'] }}</button>
                    @else
                        <span class="dc-code-chip">—</span>
                    @endif
                    <span class="dc-top-uses">{{ $couponMetrics['top_uses'] }} uses</span>
                </div>
                <div class="dc-redemption-track" role="progressbar" aria-label="Share of all redemptions" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $couponMetrics['top_share'] }}">
                    <div class="dc-redemption-fill" style="width: {{ $couponMetrics['top_share'] }}%"></div>
                </div>
                <div class="dc-redemption-caption">of {{ $couponMetrics['total_redemptions'] }} total redemptions</div>
            </div>
        </section>

        <div class="dc-info">
            <div class="dc-info-copy">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="12" cy="12" r="9" stroke="#0874d8" stroke-width="1.8"/><path d="M12 11v6M12 7h.01" stroke="#0874d8" stroke-width="2"/></svg>
                <span>One coupon can be applied per platform checkout.</span>
            </div>
            <button class="dc-info-link" type="button" data-dc-help>Learn about coupons →</button>
        </div>

        <section class="dc-panel" aria-labelledby="couponTableTitle">
            <div class="dc-header">
                <div class="dc-toolbar">
                    <div class="dc-section-title">
                        <h3 id="couponTableTitle">Coupon codes</h3>
                        <span class="dc-count" data-dc-count>{{ $coupons->count() }}</span>
                    </div>
                    <div class="dc-filters">
                        <label class="dc-search">
                            <span class="sr-only">Search coupons</span>
                            <svg viewBox="0 0 24 24" fill="none" aria-hidden="true"><circle cx="11" cy="11" r="6.5" stroke="currentColor" stroke-width="2"/><path d="m16 16 4 4" stroke="currentColor" stroke-width="2"/></svg>
                            <input class="dc-control" id="couponSearch" type="search" placeholder="Search by code or name…" autocomplete="off">
                        </label>
                        <select class="dc-control" id="statusFilter" aria-label="Filter by status">
                            <option value="all">All statuses</option>
                            <option value="active">Active</option>
                            <option value="scheduled">Scheduled</option>
                            <option value="expired">Expired</option>
                            <option value="inactive">Inactive</option>
                        </select>
                        <select class="dc-control" id="typeFilter" aria-label="Filter by discount type">
                            <option value="all">All types</option>
                            <option value="percentage">Percentage</option>
                            <option value="fixed">Fixed amount</option>
                        </select>
                        <div class="dc-filter-wrap">
                            <button class="dc-filter-btn" id="advancedFilterButton" type="button" aria-label="More filters" aria-expanded="false" aria-controls="advancedFilterMenu">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M4 6h16M7 12h10M10 18h4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                            </button>
                            <div class="dc-advanced" id="advancedFilterMenu" hidden>
                                <label for="appliesFilter">Applies to</label>
                                <select class="dc-control" id="appliesFilter">
                                    <option value="all">Any eligibility</option>
                                    <option value="all-products">All products</option>
                                    <option value="products">Selected products</option>
                                    <option value="categories">Selected categories</option>
                                </select>
                                <div class="dc-advanced-actions">
                                    <button class="inline-flex h-8 items-center rounded-lg border border-[#CFD5DC] bg-white px-3 text-sm font-semibold text-[#344054]" id="clearFiltersButton" type="button">Clear</button>
                                    <button class="inline-flex h-8 items-center rounded-lg bg-brand px-3 text-sm font-semibold text-white" id="applyFiltersButton" type="button">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dc-tabs" role="tablist" aria-label="Coupon status tabs">
                    <button class="dc-tab is-active" type="button" role="tab" aria-selected="true" data-status-tab="all">All <span>{{ $couponMetrics['total'] }}</span></button>
                    <button class="dc-tab" type="button" role="tab" aria-selected="false" data-status-tab="active">Active <span>{{ $statusCounts['active'] }}</span></button>
                    <button class="dc-tab" type="button" role="tab" aria-selected="false" data-status-tab="scheduled">Scheduled <span>{{ $statusCounts['scheduled'] }}</span></button>
                    <button class="dc-tab" type="button" role="tab" aria-selected="false" data-status-tab="expired">Expired <span>{{ $statusCounts['expired'] }}</span></button>
                    <button class="dc-tab" id="inactiveTab" type="button" role="tab" aria-selected="false" data-status-tab="inactive" @if ($statusCounts['inactive'] === 0) hidden @endif>Inactive <span>{{ $statusCounts['inactive'] }}</span></button>
                </div>
            </div>

            <div class="dc-table-wrap" id="couponTableWrap" @if ($coupons->isEmpty()) hidden @endif>
                <table class="dc-table">
                    <thead>
                        <tr>
                            <th>Code &amp; name</th>
                            <th>Discount</th>
                            <th>Applies to</th>
                            <th>Usage</th>
                            <th>Schedule</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="couponTableBody">
                        @foreach ($coupons as $coupon)
                            @php
                                $status = $coupon->merchantStatus(null, $storeTimezone);
                                $startsLocal = $coupon->scheduleInStoreTimezone($coupon->starts_at, $storeTimezone);
                                $expiresLocal = $coupon->scheduleInStoreTimezone($coupon->expires_at, $storeTimezone);
                                $productCount = $coupon->products->count();
                                $categoryCount = $coupon->categories->count();
                                $applies = $productCount > 0 ? 'products' : ($categoryCount > 0 ? 'categories' : 'all-products');
                                $discountMain = $coupon->type === 'percentage'
                                    ? $formatPercent($coupon->value)
                                    : $formatMoney($coupon->value).' off';
                                $discountRules = [];
                                if ((float) $coupon->minimum_order_amount > 0) {
                                    $discountRules[] = 'Min '.$formatMoney($coupon->minimum_order_amount);
                                }
                                if ($coupon->type === 'percentage' && $coupon->maximum_discount_amount) {
                                    $discountRules[] = 'Max '.$formatMoney($coupon->maximum_discount_amount);
                                }
                                if ($applies === 'products') {
                                    $appliesMain = 'Selected products';
                                    $appliesSub = $productCount.' '.($productCount === 1 ? 'SKU' : 'SKUs');
                                } elseif ($applies === 'categories') {
                                    $appliesMain = 'Selected categories';
                                    $appliesSub = $categoryCount.' '.($categoryCount === 1 ? 'category' : 'categories');
                                } else {
                                    $appliesMain = 'All products';
                                    $appliesSub = '';
                                }
                                $usage = $coupon->total_usage_limit
                                    ? $coupon->redeemed_count.' of '.$coupon->total_usage_limit
                                    : $coupon->redeemed_count.' redeemed';
                                if ($status === 'scheduled') {
                                    $schedule = 'Starts '.$startsLocal->format('M j, Y, g:i A');
                                } elseif ($status === 'expired') {
                                    $schedule = 'Ended '.$expiresLocal->format('M j, Y, g:i A');
                                } elseif ($expiresLocal) {
                                    $schedule = 'Ends '.$expiresLocal->format('M j, Y, g:i A');
                                } elseif ($startsLocal) {
                                    $schedule = 'Started '.$startsLocal->format('M j, Y, g:i A');
                                } else {
                                    $schedule = 'No expiry';
                                }
                            @endphp
                            <tr
                                data-coupon-row
                                data-id="{{ $coupon->id }}"
                                data-status="{{ $status }}"
                                data-type="{{ $coupon->type }}"
                                data-applies="{{ $applies }}"
                                data-search="{{ strtolower($coupon->code.' '.$coupon->name) }}"
                            >
                                <td class="dc-code-cell">
                                    <button class="dc-code-chip" type="button" data-copy-code="{{ $coupon->code }}">{{ $coupon->code }}</button>
                                    <small>{{ $coupon->name }}</small>
                                </td>
                                <td>
                                    <span class="dc-discount-main">{{ $discountMain }}</span>
                                    @if ($discountRules !== [])
                                        <span class="dc-subline">{{ implode(' · ', $discountRules) }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $appliesMain }}
                                    @if ($appliesSub !== '')
                                        <span class="dc-subline">{{ $appliesSub }}</span>
                                    @endif
                                </td>
                                <td>
                                    {{ $usage }}
                                    @if ($coupon->per_customer_usage_limit)
                                        <span class="dc-subline">{{ $coupon->per_customer_usage_limit }} per customer</span>
                                    @endif
                                </td>
                                <td>{{ $schedule }}</td>
                                <td><span class="dc-pill is-{{ $status }}">{{ $statusLabel[$status] }}</span></td>
                                <td class="dc-actions">
                                    @if ($canManageCoupons)
                                        <button class="dc-edit" type="button" data-dc-edit="{{ $coupon->id }}">Edit</button>
                                        <button class="dc-more" type="button" data-dc-menu="{{ $coupon->id }}" aria-label="More actions for {{ $coupon->code }}">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.7"/><circle cx="12" cy="12" r="1.7"/><circle cx="12" cy="19" r="1.7"/></svg>
                                        </button>
                                        <form method="POST" action="{{ route('settings.coupons.toggle', $coupon) }}" class="hidden" data-dc-toggle-form="{{ $coupon->id }}">
                                            @csrf
                                            @method('PATCH')
                                        </form>
                                        <form
                                            method="POST"
                                            action="{{ route('settings.coupons.destroy', $coupon) }}"
                                            class="hidden"
                                            data-dc-delete-form="{{ $coupon->id }}"
                                            data-ui-confirm="{{ $coupon->code }} will no longer be available at checkout. Historical orders and redemption records remain unchanged."
                                            data-ui-confirm-title="Delete this coupon?"
                                            data-ui-confirm-action="Delete coupon"
                                            data-ui-confirm-cancel="Keep coupon"
                                        >
                                            @csrf
                                            @method('DELETE')
                                        </form>
                                    @else
                                        <span class="dc-subline">View only</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="dc-empty" id="couponEmptyState" @if ($coupons->isNotEmpty()) hidden @endif>
                <div class="dc-empty-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 13 13 20 4 11V4h7z" stroke="currentColor" stroke-width="1.7"/><circle cx="8" cy="8" r="1.3" fill="currentColor"/></svg>
                </div>
                <h4>{{ $coupons->isEmpty() ? 'No coupons yet' : 'No matching coupon codes' }}</h4>
                <p>{{ $coupons->isEmpty() ? 'Create a fixed or percentage discount code when you are ready.' : 'Adjust the current filters or create a new discount.' }}</p>
                @if ($canManageCoupons)
                    <button class="inline-flex h-9 items-center gap-1.5 rounded-lg bg-brand px-3 text-sm font-semibold text-white hover:bg-brand-hover" type="button" data-dc-open-add>
                        <span aria-hidden="true">+</span> Create discount
                    </button>
                @endif
            </div>

            <div class="dc-footer">
                <span id="resultSummary">Showing {{ $coupons->isEmpty() ? '0 results' : '1–'.$coupons->count().' of '.$coupons->count() }}</span>
                <div class="dc-pagination" id="couponPagination" aria-label="Pagination"></div>
            </div>
        </section>
    </div>
@endsection

@if ($canManageCoupons)
    @push('overlays')
        <div class="discounts-console-drawer-overlay" id="discountDrawerOverlay"></div>
        <aside class="discounts-console-drawer" id="discountDrawer" aria-hidden="true" aria-labelledby="drawerTitle">
            <div class="dc-drawer-head">
                <div>
                    <div class="dc-eyebrow" id="drawerEyebrow">{{ $couponFormMode === 'edit' ? 'Edit coupon' : 'New coupon' }}</div>
                    <h2 id="drawerTitle">{{ $couponFormMode === 'edit' ? 'Edit discount' : 'Create discount' }}</h2>
                    <p>Add the essentials first. Optional rules can be configured below.</p>
                </div>
                <button class="dc-drawer-close" type="button" data-dc-close-drawer aria-label="Close">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
                </button>
            </div>
            <form id="discountForm" method="POST" action="{{ $couponEditorAction }}">
                @csrf
                <input type="hidden" name="_method" id="couponEditorMethod" value="PATCH" @disabled($couponFormMode !== 'edit')>
                <input type="hidden" name="coupon_form" id="couponEditorMode" value="{{ $couponFormMode }}">
                <input type="hidden" name="coupon_id" id="couponEditorId" value="{{ old('coupon_id') }}">
                <div class="dc-drawer-body">
                    <div class="dc-live">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M20 13 13 20 4 11V4h7z" stroke="currentColor" stroke-width="1.7"/><circle cx="8" cy="8" r="1.3" fill="currentColor"/></svg>
                        <span id="liveDiscountSummary">NEWCODE · 10% off all products</span>
                    </div>

                    <section class="dc-form-section">
                        <h3>Discount details</h3>
                        <div class="dc-field-grid">
                            <div class="dc-field">
                                <label for="couponCode">Coupon code</label>
                                <input id="couponCode" name="code" value="{{ old('code') }}" maxlength="100" placeholder="WELCOME10" autocomplete="off" required>
                            </div>
                            <div class="dc-field">
                                <label for="internalName">Internal name</label>
                                <input id="internalName" name="name" value="{{ old('name') }}" maxlength="255" placeholder="Welcome offer" required>
                            </div>
                        </div>
                    </section>

                    <section class="dc-form-section">
                        <h3>Discount type</h3>
                        <div class="dc-choice-grid">
                            <label class="dc-choice">
                                <input type="radio" name="type" value="percentage" @checked(old('type', 'percentage') === 'percentage')>
                                <span><strong>Percentage</strong><small>Reduce by a percentage</small></span>
                            </label>
                            <label class="dc-choice">
                                <input type="radio" name="type" value="fixed" @checked(old('type') === 'fixed')>
                                <span><strong>Fixed amount</strong><small>Reduce by a set amount</small></span>
                            </label>
                        </div>
                        <div class="dc-field" style="margin-top:12px">
                            <label for="discountValue">Value</label>
                            <div class="dc-affix">
                                <input id="discountValue" name="value" type="number" min="0.0001" step="0.0001" value="{{ old('value', 10) }}" required>
                                <span id="valueAffix">%</span>
                            </div>
                        </div>
                    </section>

                    <section class="dc-form-section">
                        <h3>Applies to</h3>
                        <div class="dc-applies">
                            <label class="dc-choice">
                                <input type="radio" name="applies" value="all-products" @checked(old('applies', 'all-products') === 'all-products')>
                                <span><strong>All products</strong><small>Every product in this store</small></span>
                            </label>
                            <label class="dc-choice">
                                <input type="radio" name="applies" value="products" @checked(old('applies') === 'products')>
                                <span><strong>Specific products</strong><small>Choose by product SKU</small></span>
                            </label>
                            <label class="dc-choice">
                                <input type="radio" name="applies" value="categories" @checked(old('applies') === 'categories')>
                                <span><strong>Categories</strong><small>Choose one or more categories</small></span>
                            </label>
                        </div>
                        <div class="dc-conditional" id="productSkuFields" hidden>
                            <div class="dc-field">
                                <label for="eligibleSkus">Eligible product SKUs</label>
                                <textarea id="eligibleSkus" name="product_skus" placeholder="SKU-100, SKU-200">{{ old('product_skus') }}</textarea>
                            </div>
                        </div>
                        @if ($categories->isNotEmpty())
                            <div class="dc-conditional" id="categoryFields" hidden>
                                <div class="dc-field">
                                    <span>Eligible categories</span>
                                    <div class="dc-check-grid">
                                        @foreach ($categories as $category)
                                            <label class="dc-check-option">
                                                <input type="checkbox" name="category_ids[]" value="{{ $category->id }}" @checked(in_array($category->id, old('category_ids', []), false))>
                                                {{ $category->name }}
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @else
                            <div class="dc-conditional" id="categoryFields" hidden>
                                <p class="text-sm text-[#64748B]">This store does not have categories yet.</p>
                            </div>
                        @endif
                    </section>

                    <section class="dc-form-section">
                        <h3>Optional rules</h3>
                        <p class="dc-optional-hint">Leave these blank unless you need a minimum order, a discount cap, usage limits, or a schedule.</p>
                        <div class="dc-accordion">
                            <button class="dc-accordion-trigger" type="button" data-accordion="orderRuleFields" aria-expanded="false">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M5 4h14v16H5zM8 8h8M8 12h8M8 16h5" stroke="currentColor" stroke-width="1.8"/></svg>
                                <strong>Order requirements</strong>
                                <span class="dc-accordion-summary" id="orderRulesSummary">No minimum · No maximum discount</span>
                            </button>
                            <div class="dc-accordion-body" id="orderRuleFields" hidden>
                                <div class="dc-field-grid" style="margin-top:12px">
                                    <div class="dc-field">
                                        <label for="minimumOrder">Minimum order ({{ $currencyCode }})</label>
                                        <input id="minimumOrder" name="minimum_order_amount" type="number" min="0" step="0.01" value="{{ old('minimum_order_amount') }}" placeholder="None">
                                    </div>
                                    <div class="dc-field" id="maximumDiscountField">
                                        <label for="maximumDiscount">Maximum discount ({{ $currencyCode }})</label>
                                        <input id="maximumDiscount" name="maximum_discount_amount" type="number" min="0.01" step="0.01" value="{{ old('maximum_discount_amount') }}" placeholder="No maximum">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dc-accordion">
                            <button class="dc-accordion-trigger" type="button" data-accordion="usageLimitFields" aria-expanded="false">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M16 20v-2a4 4 0 0 0-4-4H7a4 4 0 0 0-4 4v2M9.5 10a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.7"/></svg>
                                <strong>Usage limits</strong>
                                <span class="dc-accordion-summary" id="usageRulesSummary">Unlimited</span>
                            </button>
                            <div class="dc-accordion-body" id="usageLimitFields" hidden>
                                <div class="dc-field-grid" style="margin-top:12px">
                                    <div class="dc-field">
                                        <label for="totalUses">Total uses</label>
                                        <input id="totalUses" name="total_usage_limit" type="number" min="1" step="1" value="{{ old('total_usage_limit') }}" placeholder="Unlimited">
                                    </div>
                                    <div class="dc-field">
                                        <label for="usesPerCustomer">Uses per customer</label>
                                        <input id="usesPerCustomer" name="per_customer_usage_limit" type="number" min="1" step="1" value="{{ old('per_customer_usage_limit') }}" placeholder="Unlimited">
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="dc-accordion">
                            <button class="dc-accordion-trigger" type="button" data-accordion="scheduleFields" aria-expanded="false">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><rect x="3" y="5" width="18" height="16" rx="2" stroke="currentColor" stroke-width="1.8"/><path d="M3 10h18M8 3v4M16 3v4" stroke="currentColor" stroke-width="1.8"/></svg>
                                <strong>Schedule</strong>
                                <span class="dc-accordion-summary" id="scheduleSummary">Starts immediately · No expiry</span>
                            </button>
                            <div class="dc-accordion-body" id="scheduleFields" hidden>
                                <div class="dc-field-grid" style="margin-top:12px">
                                    <div class="dc-field">
                                        <label for="startsAt">Starts</label>
                                        <input id="startsAt" name="starts_at" type="datetime-local" value="{{ old('starts_at') }}">
                                    </div>
                                    <div class="dc-field">
                                        <label for="expiresAt">Expires</label>
                                        <input id="expiresAt" name="expires_at" type="datetime-local" value="{{ old('expires_at') }}">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </section>

                    <label class="dc-toggle-row">
                        <span class="dc-switch">
                            <input type="hidden" name="is_active" value="0">
                            <input id="couponActive" type="checkbox" name="is_active" value="1" @checked((string) old('is_active', '1') === '1')>
                            <span class="dc-switch-track"></span>
                        </span>
                        <span class="dc-toggle-copy">
                            <strong id="activeToggleTitle">Active immediately</strong>
                            <span>Customers can use this code after it is created.</span>
                        </span>
                    </label>
                </div>
                <div class="dc-drawer-foot">
                    <button class="inline-flex h-9 items-center rounded-lg border border-[#FFD8D3] bg-[#FFF1EF] px-3 text-sm font-semibold text-[#C9362B]" id="drawerDeleteButton" type="button" hidden>Delete coupon</button>
                    <div class="dc-foot-actions">
                        <button class="inline-flex h-9 items-center rounded-lg border border-[#CFD5DC] bg-white px-4 text-sm font-semibold text-[#344054]" type="button" data-dc-close-drawer>Cancel</button>
                        <button class="inline-flex h-9 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white" id="submitDiscountButton" type="submit">{{ $couponFormMode === 'edit' ? 'Save changes' : 'Create discount' }}</button>
                    </div>
                </div>
            </form>
        </aside>
    @endpush
@endif

@push('overlays')
    <dialog class="dc-help-dialog" id="couponHelpDialog">
        <h3>How coupons work</h3>
        <div class="dc-help-body">
            <p>Coupons apply to platform checkout and are scoped to the active store.</p>
            <ul>
                <li>Only one coupon can be applied per checkout.</li>
                <li>Codes can use a percentage or a fixed amount.</li>
                <li>Leave eligibility blank to cover all products, or limit by SKU or category.</li>
                <li>Usage limits and schedules are optional.</li>
            </ul>
        </div>
        <div class="dc-help-foot">
            <button class="inline-flex h-9 items-center rounded-lg bg-brand px-4 text-sm font-semibold text-white" type="button" data-dc-close-help>Got it</button>
        </div>
    </dialog>
    <div class="dc-toast-region" id="discountToastRegion" aria-live="polite"></div>
    @if ($canManageCoupons)
        <div class="dc-row-menu" id="rowContextMenu" hidden>
            <button type="button" data-row-action="edit">Edit coupon</button>
            <button type="button" data-row-action="toggle"><span id="toggleCouponLabel">Deactivate coupon</span></button>
            <button class="is-danger" type="button" data-row-action="delete">Delete coupon</button>
        </div>
    @endif
@endpush

@push('scripts')
    <script type="application/json" id="coupon-editor-payload">@json($couponEditorPayload)</script>
    <script>
    (function () {
        var payload = {};
        try {
            var payloadEl = document.getElementById('coupon-editor-payload');
            if (payloadEl) payload = JSON.parse(payloadEl.textContent || '{}');
        } catch (e) {}

        var currency = @json($currencyCode);
        var canManage = @json((bool) $canManageCoupons);
        var pageSize = 10;
        var state = { status: 'all', type: 'all', applies: 'all', search: '', page: 1, contextId: null };
        var storeUrl = @json(route('settings.coupons.store'));
        var form = document.getElementById('discountForm');
        var drawer = document.getElementById('discountDrawer');
        var overlay = document.getElementById('discountDrawerOverlay');
        var methodInput = document.getElementById('couponEditorMethod');
        var modeInput = document.getElementById('couponEditorMode');
        var idInput = document.getElementById('couponEditorId');

        function toast(message) {
            var region = document.getElementById('discountToastRegion');
            if (! region) return;
            var item = document.createElement('div');
            item.className = 'dc-toast';
            item.textContent = message;
            region.appendChild(item);
            setTimeout(function () { item.remove(); }, 3200);
        }

        function formatMoney(value) {
            var amount = Number(value || 0);
            var decimals = amount % 1 === 0 ? 0 : 2;
            return currency + ' ' + amount.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: 2 });
        }

        function rows() {
            return Array.prototype.slice.call(document.querySelectorAll('[data-coupon-row]'));
        }

        function matches(row) {
            var query = state.search.trim().toLowerCase();
            var hay = row.getAttribute('data-search') || '';
            return (state.status === 'all' || row.getAttribute('data-status') === state.status)
                && (state.type === 'all' || row.getAttribute('data-type') === state.type)
                && (state.applies === 'all' || row.getAttribute('data-applies') === state.applies)
                && (! query || hay.indexOf(query) !== -1);
        }

        function filteredRows() {
            return rows().filter(matches);
        }

        function renderTable() {
            var matched = filteredRows();
            var pages = Math.max(1, Math.ceil(matched.length / pageSize));
            if (state.page > pages) state.page = pages;
            var start = (state.page - 1) * pageSize;
            var visible = matched.slice(start, start + pageSize);
            rows().forEach(function (row) { row.hidden = true; });
            visible.forEach(function (row) { row.hidden = false; });

            var wrap = document.getElementById('couponTableWrap');
            var empty = document.getElementById('couponEmptyState');
            var summary = document.getElementById('resultSummary');
            var hasAny = rows().length > 0;
            if (wrap) wrap.hidden = matched.length === 0;
            if (empty) {
                empty.hidden = matched.length !== 0;
                if (matched.length === 0) {
                    var title = empty.querySelector('h4');
                    var copy = empty.querySelector('p');
                    if (title) title.textContent = hasAny ? 'No matching coupon codes' : 'No coupons yet';
                    if (copy) copy.textContent = hasAny
                        ? 'Adjust the current filters or create a new discount.'
                        : 'Create a fixed or percentage discount code when you are ready.';
                }
            }
            if (summary) {
                summary.textContent = matched.length
                    ? ('Showing ' + (start + 1) + '–' + (start + visible.length) + ' of ' + matched.length)
                    : 'Showing 0 results';
            }

            var pager = document.getElementById('couponPagination');
            if (pager) {
                pager.innerHTML = '';
                var prev = document.createElement('button');
                prev.type = 'button';
                prev.className = 'dc-page-btn';
                prev.textContent = '‹';
                prev.disabled = state.page <= 1;
                prev.addEventListener('click', function () { state.page -= 1; renderTable(); });
                pager.appendChild(prev);
                var current = document.createElement('button');
                current.type = 'button';
                current.className = 'dc-page-btn is-current';
                current.textContent = String(state.page);
                pager.appendChild(current);
                var next = document.createElement('button');
                next.type = 'button';
                next.className = 'dc-page-btn';
                next.textContent = '›';
                next.disabled = state.page >= pages || matched.length === 0;
                next.addEventListener('click', function () { state.page += 1; renderTable(); });
                pager.appendChild(next);
            }
        }

        function setActiveTab(status) {
            state.status = status;
            state.page = 1;
            var filter = document.getElementById('statusFilter');
            if (filter) filter.value = status;
            document.querySelectorAll('.dc-tab').forEach(function (button) {
                var active = button.getAttribute('data-status-tab') === status;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-selected', String(active));
            });
            renderTable();
        }

        function copyCode(code) {
            if (! code) return;
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(code).then(function () { toast(code + ' copied.'); }).catch(function () { toast('Coupon code: ' + code); });
            } else {
                toast('Coupon code: ' + code);
            }
        }

        function checkedType() {
            var el = document.querySelector('input[name="type"]:checked');
            return el ? el.value : 'percentage';
        }

        function checkedApplies() {
            var el = document.querySelector('input[name="applies"]:checked');
            return el ? el.value : 'all-products';
        }

        function updateTypeUI() {
            var type = checkedType();
            var affix = document.getElementById('valueAffix');
            var maxField = document.getElementById('maximumDiscountField');
            if (affix) affix.textContent = type === 'percentage' ? '%' : currency;
            if (maxField) maxField.hidden = type !== 'percentage';
            updateFormSummaries();
        }

        function updateAppliesUI() {
            var applies = checkedApplies();
            var sku = document.getElementById('productSkuFields');
            var cats = document.getElementById('categoryFields');
            if (sku) sku.hidden = applies !== 'products';
            if (cats) cats.hidden = applies !== 'categories';
            updateFormSummaries();
        }

        function updateFormSummaries() {
            var codeEl = document.getElementById('couponCode');
            var valueEl = document.getElementById('discountValue');
            var live = document.getElementById('liveDiscountSummary');
            var code = ((codeEl && codeEl.value) || 'NEWCODE').trim().toUpperCase() || 'NEWCODE';
            var type = checkedType();
            var value = Number((valueEl && valueEl.value) || 0);
            var applies = checkedApplies();
            var appliesLabel = applies === 'products' ? 'selected products' : applies === 'categories' ? 'selected categories' : 'all products';
            if (live) live.textContent = code + ' · ' + (type === 'percentage' ? value + '%' : formatMoney(value)) + ' off ' + appliesLabel;

            var minEl = document.getElementById('minimumOrder');
            var maxEl = document.getElementById('maximumDiscount');
            var order = document.getElementById('orderRulesSummary');
            var minimum = Number((minEl && minEl.value) || 0);
            var maximum = Number((maxEl && maxEl.value) || 0);
            var orderParts = [minimum > 0 ? 'Min ' + formatMoney(minimum) : 'No minimum'];
            if (type === 'percentage') orderParts.push(maximum ? 'Max ' + formatMoney(maximum) : 'No maximum discount');
            if (order) order.textContent = orderParts.join(' · ');

            var total = (document.getElementById('totalUses') || {}).value;
            var perCustomer = (document.getElementById('usesPerCustomer') || {}).value;
            var usage = document.getElementById('usageRulesSummary');
            if (usage) {
                usage.textContent = total || perCustomer
                    ? ((total ? total + ' total' : 'Unlimited total') + ' · ' + (perCustomer ? perCustomer + ' per customer' : 'Unlimited per customer'))
                    : 'Unlimited';
            }

            var starts = (document.getElementById('startsAt') || {}).value;
            var expires = (document.getElementById('expiresAt') || {}).value;
            var schedule = document.getElementById('scheduleSummary');
            if (schedule) {
                schedule.textContent = (starts ? 'Has start date' : 'Starts immediately') + ' · ' + (expires ? 'Has expiry' : 'No expiry');
            }
        }

        function setRadio(name, value) {
            var el = document.querySelector('input[name="' + name + '"][value="' + value + '"]');
            if (el) el.checked = true;
        }

        function setValue(id, value) {
            var el = document.getElementById(id);
            if (el) el.value = value == null ? '' : value;
        }

        function revealDrawer(focusCode) {
            if (! drawer) return;
            if (overlay) overlay.classList.add('is-open');
            drawer.classList.add('is-open');
            drawer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('overflow-hidden');
            if (focusCode) {
                var code = document.getElementById('couponCode');
                if (code) code.focus();
            }
        }

        function openOptionalAccordionsFromFields() {
            ['orderRuleFields', 'usageLimitFields', 'scheduleFields'].forEach(function (sectionId) {
                var shouldOpen = false;
                if (sectionId === 'orderRuleFields') {
                    var minVal = Number((document.getElementById('minimumOrder') || {}).value || 0);
                    var maxVal = Number((document.getElementById('maximumDiscount') || {}).value || 0);
                    shouldOpen = minVal > 0 || maxVal > 0;
                }
                if (sectionId === 'usageLimitFields') {
                    shouldOpen = Boolean((document.getElementById('totalUses') || {}).value || (document.getElementById('usesPerCustomer') || {}).value);
                }
                if (sectionId === 'scheduleFields') {
                    shouldOpen = Boolean((document.getElementById('startsAt') || {}).value || (document.getElementById('expiresAt') || {}).value);
                }
                if (! shouldOpen) return;
                var trigger = document.querySelector('[data-accordion="' + sectionId + '"]');
                var body = document.getElementById(sectionId);
                if (trigger) trigger.setAttribute('aria-expanded', 'true');
                if (body) body.hidden = false;
            });
        }

        function restoreDrawerFromServer() {
            if (! drawer || ! form) return;
            var editing = modeInput && modeInput.value === 'edit';
            var id = idInput ? String(idInput.value || '') : '';
            if (editing && payload[id]) {
                form.action = payload[id].update_url;
                if (methodInput) {
                    methodInput.disabled = false;
                    methodInput.value = 'PATCH';
                }
                var del = document.getElementById('drawerDeleteButton');
                if (del) del.hidden = false;
            } else {
                form.action = storeUrl;
                if (methodInput) methodInput.disabled = true;
            }
            openOptionalAccordionsFromFields();
            updateTypeUI();
            updateAppliesUI();
            revealDrawer(false);
        }

        function openDrawer(id) {
            if (! drawer || ! form) return;
            var data = id ? payload[String(id)] : null;
            var editing = Boolean(data);
            form.action = editing ? data.update_url : storeUrl;
            if (methodInput) {
                methodInput.disabled = ! editing;
                methodInput.value = 'PATCH';
            }
            if (modeInput) modeInput.value = editing ? 'edit' : 'add';
            if (idInput) idInput.value = editing ? String(data.id) : '';
            var title = document.getElementById('drawerTitle');
            var eyebrow = document.getElementById('drawerEyebrow');
            var submit = document.getElementById('submitDiscountButton');
            var del = document.getElementById('drawerDeleteButton');
            var activeTitle = document.getElementById('activeToggleTitle');
            if (eyebrow) eyebrow.textContent = editing ? 'Edit coupon' : 'New coupon';
            if (title) title.textContent = editing ? 'Edit discount' : 'Create discount';
            if (submit) submit.textContent = editing ? 'Save changes' : 'Create discount';
            if (del) del.hidden = ! editing;
            if (activeTitle) activeTitle.textContent = editing ? 'Coupon enabled' : 'Active immediately';

            setValue('couponCode', editing ? data.code : '');
            setValue('internalName', editing ? data.name : '');
            setRadio('type', editing ? data.type : 'percentage');
            setValue('discountValue', editing ? data.value : 10);
            setRadio('applies', editing ? data.applies : 'all-products');
            setValue('eligibleSkus', editing ? (data.skus || []).join(', ') : '');
            document.querySelectorAll('input[name="category_ids[]"]').forEach(function (input) {
                input.checked = editing && (data.category_ids || []).indexOf(Number(input.value)) !== -1;
            });
            setValue('minimumOrder', editing && data.min_order ? data.min_order : '');
            setValue('maximumDiscount', editing && data.max_discount ? data.max_discount : '');
            setValue('totalUses', editing && data.total_limit ? data.total_limit : '');
            setValue('usesPerCustomer', editing && data.per_customer_limit ? data.per_customer_limit : '');
            setValue('startsAt', editing ? data.starts_at : '');
            setValue('expiresAt', editing ? data.expires_at : '');
            var active = document.getElementById('couponActive');
            if (active) active.checked = editing ? Boolean(data.active) : true;

            document.querySelectorAll('.dc-accordion-trigger').forEach(function (button) {
                button.setAttribute('aria-expanded', 'false');
                var body = document.getElementById(button.getAttribute('data-accordion'));
                if (body) body.hidden = true;
            });
            if (editing && (data.min_order || data.max_discount || data.total_limit || data.per_customer_limit || data.starts_at || data.expires_at)) {
                ['orderRuleFields', 'usageLimitFields', 'scheduleFields'].forEach(function (sectionId) {
                    var shouldOpen = (sectionId === 'orderRuleFields' && (data.min_order || data.max_discount))
                        || (sectionId === 'usageLimitFields' && (data.total_limit || data.per_customer_limit))
                        || (sectionId === 'scheduleFields' && (data.starts_at || data.expires_at));
                    if (! shouldOpen) return;
                    var trigger = document.querySelector('[data-accordion="' + sectionId + '"]');
                    var body = document.getElementById(sectionId);
                    if (trigger) trigger.setAttribute('aria-expanded', 'true');
                    if (body) body.hidden = false;
                });
            }

            updateTypeUI();
            updateAppliesUI();
            revealDrawer(true);
        }

        function closeDrawer() {
            if (overlay) overlay.classList.remove('is-open');
            if (drawer) {
                drawer.classList.remove('is-open');
                drawer.setAttribute('aria-hidden', 'true');
            }
            document.body.classList.remove('overflow-hidden');
        }

        function closeMenus() {
            var menu = document.getElementById('rowContextMenu');
            var advanced = document.getElementById('advancedFilterMenu');
            var filterBtn = document.getElementById('advancedFilterButton');
            if (menu) menu.hidden = true;
            if (advanced) advanced.hidden = true;
            if (filterBtn) filterBtn.setAttribute('aria-expanded', 'false');
        }

        function openContextMenu(button, id) {
            var data = payload[String(id)];
            var menu = document.getElementById('rowContextMenu');
            var label = document.getElementById('toggleCouponLabel');
            if (! menu || ! data) return;
            state.contextId = id;
            if (label) label.textContent = data.active ? 'Deactivate coupon' : 'Activate coupon';
            menu.hidden = false;
            var rect = button.getBoundingClientRect();
            menu.style.left = Math.min(window.innerWidth - 200, Math.max(10, rect.right - 190)) + 'px';
            menu.style.top = Math.min(window.innerHeight - 150, rect.bottom + 6) + 'px';
        }

        document.querySelectorAll('[data-dc-open-add]').forEach(function (btn) {
            btn.addEventListener('click', function () { openDrawer(null); });
        });
        document.querySelectorAll('[data-dc-close-drawer]').forEach(function (btn) {
            btn.addEventListener('click', closeDrawer);
        });
        if (overlay) overlay.addEventListener('click', closeDrawer);

        var search = document.getElementById('couponSearch');
        var statusFilter = document.getElementById('statusFilter');
        var typeFilter = document.getElementById('typeFilter');
        var appliesFilter = document.getElementById('appliesFilter');
        if (search) search.addEventListener('input', function (event) { state.search = event.target.value; state.page = 1; renderTable(); });
        if (statusFilter) statusFilter.addEventListener('change', function (event) { setActiveTab(event.target.value); });
        if (typeFilter) typeFilter.addEventListener('change', function (event) { state.type = event.target.value; state.page = 1; renderTable(); });
        document.querySelectorAll('.dc-tab').forEach(function (button) {
            button.addEventListener('click', function () { setActiveTab(button.getAttribute('data-status-tab')); });
        });

        var filterBtn = document.getElementById('advancedFilterButton');
        var advanced = document.getElementById('advancedFilterMenu');
        if (filterBtn && advanced) {
            filterBtn.addEventListener('click', function (event) {
                event.stopPropagation();
                var open = advanced.hidden;
                closeMenus();
                advanced.hidden = ! open;
                filterBtn.setAttribute('aria-expanded', String(open));
            });
        }
        var applyFilters = document.getElementById('applyFiltersButton');
        var clearFilters = document.getElementById('clearFiltersButton');
        if (applyFilters) applyFilters.addEventListener('click', function () {
            state.applies = appliesFilter ? appliesFilter.value : 'all';
            state.page = 1;
            if (filterBtn) filterBtn.classList.toggle('is-active', state.applies !== 'all');
            closeMenus();
            renderTable();
        });
        if (clearFilters) clearFilters.addEventListener('click', function () {
            state.type = 'all';
            state.applies = 'all';
            state.search = '';
            state.page = 1;
            if (typeFilter) typeFilter.value = 'all';
            if (appliesFilter) appliesFilter.value = 'all';
            if (search) search.value = '';
            if (filterBtn) filterBtn.classList.remove('is-active');
            setActiveTab('all');
            closeMenus();
        });

        document.querySelectorAll('[data-copy-code]').forEach(function (btn) {
            btn.addEventListener('click', function () { copyCode(btn.getAttribute('data-copy-code')); });
        });
        document.querySelectorAll('[data-dc-edit]').forEach(function (btn) {
            btn.addEventListener('click', function () { openDrawer(btn.getAttribute('data-dc-edit')); });
        });
        document.querySelectorAll('[data-dc-menu]').forEach(function (btn) {
            btn.addEventListener('click', function (event) {
                event.stopPropagation();
                closeMenus();
                openContextMenu(btn, btn.getAttribute('data-dc-menu'));
            });
        });

        var contextMenu = document.getElementById('rowContextMenu');
        if (contextMenu) {
            contextMenu.addEventListener('click', function (event) {
                var action = event.target.closest('[data-row-action]');
                if (! action || ! state.contextId) return;
                var id = state.contextId;
                var kind = action.getAttribute('data-row-action');
                closeMenus();
                if (kind === 'edit') openDrawer(id);
                if (kind === 'toggle') {
                    var toggleForm = document.querySelector('[data-dc-toggle-form="' + id + '"]');
                    if (toggleForm) toggleForm.submit();
                }
                if (kind === 'delete') {
                    var deleteForm = document.querySelector('[data-dc-delete-form="' + id + '"]');
                    if (deleteForm) deleteForm.requestSubmit();
                }
            });
        }

        var drawerDelete = document.getElementById('drawerDeleteButton');
        if (drawerDelete) {
            drawerDelete.addEventListener('click', function () {
                var id = idInput ? idInput.value : '';
                var deleteForm = document.querySelector('[data-dc-delete-form="' + id + '"]');
                if (deleteForm) deleteForm.requestSubmit();
            });
        }

        document.querySelectorAll('.dc-accordion-trigger').forEach(function (button) {
            button.addEventListener('click', function () {
                var open = button.getAttribute('aria-expanded') === 'true';
                button.setAttribute('aria-expanded', String(! open));
                var body = document.getElementById(button.getAttribute('data-accordion'));
                if (body) body.hidden = open;
            });
        });

        var codeInput = document.getElementById('couponCode');
        if (codeInput) {
            codeInput.addEventListener('input', function (event) {
                event.target.value = event.target.value.toUpperCase().replace(/\s+/g, '-');
                updateFormSummaries();
            });
        }
        ['internalName', 'discountValue', 'minimumOrder', 'maximumDiscount', 'totalUses', 'usesPerCustomer', 'startsAt', 'expiresAt'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', updateFormSummaries);
        });
        document.querySelectorAll('input[name="type"]').forEach(function (input) {
            input.addEventListener('change', updateTypeUI);
        });
        document.querySelectorAll('input[name="applies"]').forEach(function (input) {
            input.addEventListener('change', updateAppliesUI);
        });

        if (form) {
            form.addEventListener('submit', function () {
                var applies = checkedApplies();
                var sku = document.getElementById('eligibleSkus');
                if (applies !== 'products' && sku) sku.value = '';
                if (applies !== 'categories') {
                    document.querySelectorAll('input[name="category_ids[]"]').forEach(function (input) { input.checked = false; });
                }
                if (checkedType() !== 'percentage') {
                    var max = document.getElementById('maximumDiscount');
                    if (max) max.value = '';
                }
            });
        }

        var help = document.getElementById('couponHelpDialog');
        document.querySelectorAll('[data-dc-help]').forEach(function (btn) {
            btn.addEventListener('click', function () { if (help && help.showModal) help.showModal(); });
        });
        document.querySelectorAll('[data-dc-close-help]').forEach(function (btn) {
            btn.addEventListener('click', function () { if (help) help.close(); });
        });

        document.addEventListener('click', function (event) {
            if (! event.target.closest('.dc-filter-wrap') && ! event.target.closest('#rowContextMenu')) closeMenus();
        });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenus();
                if (drawer && drawer.classList.contains('is-open')) closeDrawer();
            }
        });
        window.addEventListener('resize', closeMenus);
        window.addEventListener('scroll', closeMenus, true);

        updateTypeUI();
        updateAppliesUI();
        renderTable();

        @if ($openCouponDrawer && $canManageCoupons)
            restoreDrawerFromServer();
        @endif
    })();
    </script>
@endpush
