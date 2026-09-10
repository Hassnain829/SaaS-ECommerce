@php
    $rate = $entry['model'];
    $summary = $entry['summary'];
    $selected = (bool) ($selected ?? false);
    $canManage = (bool) ($canManageTax ?? false);
    $effective = (bool) ($taxSetting->enabled && $rate->is_active);
    $regionDisplay = $rate->region_code
        ? ($summary['region_label']
            ? $summary['region_label'].' ('.$rate->region_code.')'
            : strtoupper((string) $rate->region_code))
        : 'Country-wide';
@endphp
<article class="trb-inspector" data-trb-inspector="{{ $rate->id }}" @if (! $selected) hidden @endif>
    <button class="trb-mobile-back" type="button" data-trb-back>
        @include('user_view.taxes.partials.icon', ['name' => 'back', 'size' => 14])
        All jurisdictions
    </button>
    <p class="trb-breadcrumb">
        <span>Jurisdictions</span><span>/</span>
        <span>{{ $summary['country_name'] }}</span><span>/</span>
        <span>{{ $entry['scopeLabel'] }}</span>
    </p>
    <div class="trb-inspector-title">
        <div class="trb-title-group">
            <h2>{{ $rate->name }}</h2>
            <span class="trb-pill {{ $rate->is_active ? '' : 'is-neutral' }}">{{ $rate->is_active ? 'Active' : 'Inactive' }}</span>
        </div>
        @if ($canManage)
            <div class="trb-inspector-actions">
                <a class="trb-btn" href="{{ route('settings.taxes.index', ['edit_rate' => $rate->id]) }}">Edit rate</a>
                <button
                    class="trb-btn trb-btn-square"
                    type="button"
                    data-trb-rate-menu="{{ $rate->id }}"
                    data-trb-rate-name="{{ $rate->name }}"
                    data-trb-rate-active="{{ $rate->is_active ? '1' : '0' }}"
                    data-trb-update="{{ route('settings.taxes.rates.update', $rate) }}"
                    data-trb-country="{{ $rate->country_code }}"
                    data-trb-region="{{ $rate->region_code }}"
                    data-trb-percent="{{ $rate->rate_percent }}"
                    data-trb-priority="{{ $rate->priority }}"
                    aria-label="Actions for {{ $rate->name }}"
                    aria-haspopup="menu"
                    aria-expanded="false"
                >
                    @include('user_view.taxes.partials.icon', ['name' => 'more', 'size' => 16])
                </button>
            </div>
        @endif
    </div>
    <div class="trb-rate-hero">
        <p class="trb-eyebrow">Tax rate</p>
        <p class="trb-rate-number">{{ $rate->rate_percent }}%</p>
    </div>
    <dl class="trb-definition">
        <div><dt>Jurisdiction</dt><dd>{{ $summary['country_name'] }}</dd></div>
        <div>
            <dt>Coverage</dt>
            <dd>
                {{ $regionDisplay }}
                @if ($summary['scope'] === 'region-specific')
                    <span class="sr-only">Region-specific</span>
                @endif
            </dd>
        </div>
        <div><dt>Priority</dt><dd>{{ $rate->priority }}</dd></div>
        <div><dt>Calculation address</dt><dd>Customer shipping address</dd></div>
    </dl>
    <section class="trb-eligibility">
        <h3>{{ $effective ? 'Applied at checkout' : 'Not currently applied' }}</h3>
        <ul class="trb-checks">
            <li>
                <span class="trb-check-icon {{ $effective ? '' : 'is-off' }}">
                    @include('user_view.taxes.partials.icon', ['name' => $effective ? 'check' : 'minus', 'size' => 15])
                </span>
                <span>Eligible products</span>
            </li>
            <li>
                <span class="trb-check-icon {{ $effective && $taxSetting->shipping_taxable ? '' : 'is-off' }}">
                    @include('user_view.taxes.partials.icon', ['name' => $effective && $taxSetting->shipping_taxable ? 'check' : 'minus', 'size' => 15])
                </span>
                <span>{{ $taxSetting->shipping_taxable ? 'Shipping charges' : 'Shipping charges are not taxable' }}</span>
            </li>
            <li>
                <span class="trb-check-icon {{ $effective ? '' : 'is-off' }}">
                    @include('user_view.taxes.partials.icon', ['name' => $effective ? 'check' : 'minus', 'size' => 15])
                </span>
                <span>Future platform checkouts</span>
            </li>
        </ul>
    </section>
    @if (! $effective)
        <div class="trb-note is-warning">
            @include('user_view.taxes.partials.icon', ['name' => 'info', 'size' => 17])
            <span>
                @if (! $taxSetting->enabled)
                    Platform tax calculation is disabled. Enable it in Behavior to use active rates.
                @else
                    This rate is inactive. Activate it from the rate actions to use it for matching addresses.
                @endif
            </span>
        </div>
    @else
        <div class="trb-note">
            @include('user_view.taxes.partials.icon', ['name' => 'info', 'size' => 17])
            <span>Historical orders keep their original tax snapshots.</span>
        </div>
    @endif
    @if ($canManage)
        <div class="trb-delete-zone">
            <button class="trb-text-danger" type="submit" form="trb-delete-{{ $rate->id }}" data-trb-delete="{{ $rate->id }}">Delete tax rate</button>
            <p>Existing order snapshots are not changed.</p>
        </div>
    @endif
</article>
