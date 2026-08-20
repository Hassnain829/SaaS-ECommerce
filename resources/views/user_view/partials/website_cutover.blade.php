@php
    $cutover = is_array($cutover ?? null) ? $cutover : [];
    $stages = is_array($cutover['stages'] ?? null) ? $cutover['stages'] : [];
    $overall = (string) ($cutover['overall'] ?? 'blocked');
    $canActivate = (bool) ($cutover['can_activate'] ?? false);
    $blocking = is_array($cutover['blocking_labels'] ?? null) ? $cutover['blocking_labels'] : [];
    $cutoverRecord = $cutover['cutover'] ?? null;
@endphp

<section class="website-connect-card" aria-labelledby="website-cutover-heading">
    <h2 id="website-cutover-heading" class="website-connect-card-title">Go live checklist</h2>
    <p class="website-connect-help">
        Use this list after the WordPress plugin is connected. A checkbox cannot override a missing Stripe account, a broken connection, or failed import rows. This portal never turns off WordPress plugins or deletes WooCommerce data.
    </p>

    @if ($overall === 'activated')
        <p class="website-cutover-banner is-live">This store is marked live for the connected WordPress website.</p>
    @elseif ($overall === 'rolled_back')
        <p class="website-cutover-banner is-warning">Go-live is marked rolled back in this portal. WordPress was not changed automatically.</p>
    @elseif ($blocking !== [])
        <p class="website-cutover-banner is-blocked">Not ready yet: {{ implode(', ', array_slice($blocking, 0, 4)) }}{{ count($blocking) > 4 ? '…' : '' }}</p>
    @endif

    <ol class="website-cutover-stages">
        @foreach ($stages as $stage)
            <li class="website-cutover-stage is-{{ $stage['status'] }}">
                <h3>{{ $stage['title'] }}</h3>
                <ul class="website-cutover-gates">
                    @foreach ($stage['gates'] as $gate)
                        <li class="website-cutover-gate is-{{ $gate['status'] }}">
                            <p class="website-cutover-gate-label">
                                <span class="website-cutover-status">{{ $gate['status'] === 'completed' ? 'Ready' : ($gate['status'] === 'warning' ? 'Needs your confirmation' : 'Blocked') }}</span>
                                {{ $gate['label'] }}
                            </p>
                            <p>{{ $gate['message'] }}</p>
                            <div class="website-cutover-gate-actions">
                                @if (!empty($gate['action_href']) && !empty($gate['action_label']))
                                    <a href="{{ $gate['action_href'] }}">{{ $gate['action_label'] }}</a>
                                @endif
                                @if ($canManageKey && !empty($gate['ack']) && $gate['status'] !== 'completed' && $gate['status'] !== 'blocked')
                                    <form method="post" action="{{ route('developer-storefront.cutover.acknowledge') }}">
                                        @csrf
                                        <input type="hidden" name="acknowledgement" value="{{ $gate['ack'] }}">
                                        <button type="submit" class="website-connect-btn website-connect-btn-secondary">{{ $gate['ack_label'] }}</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </li>
        @endforeach
    </ol>

    @if ($canManageKey)
        <div class="website-cutover-final">
            @if ($canActivate)
                <form method="post" action="{{ route('developer-storefront.cutover.activate') }}" onsubmit="return confirm('Mark this WordPress website live for the current store? Checkout stays in this portal.');">
                    @csrf
                    <button type="submit" class="website-connect-btn website-connect-btn-primary">Mark website live</button>
                </form>
            @else
                <p class="text-sm text-[#64748B]">Mark website live becomes available when every blocked item is fixed and the confirmations above are saved.</p>
            @endif

            @if ($cutoverRecord && $cutoverRecord->status === \App\Models\ConnectedSiteCutover::STATUS_ACTIVATED)
                <form method="post" action="{{ route('developer-storefront.cutover.rollback') }}" onsubmit="return confirm('Mark go-live as rolled back in this portal? This does not delete WordPress, WooCommerce, orders, or backups.');">
                    @csrf
                    <button type="submit" class="website-connect-btn website-connect-btn-danger">Mark rolled back</button>
                </form>
            @endif
        </div>
    @else
        <p class="text-sm text-[#64748B]">Only a store owner can confirm backup steps or mark the website live.</p>
    @endif
</section>
