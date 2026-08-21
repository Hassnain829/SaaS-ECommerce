@extends('layouts.user.user-sidebar')

@section('title', 'Delivery — '.config('app.name'))

@php
    $connectionStatusLabels = [
        'not_connected' => 'Not connected',
        'setup_required' => 'Setup required',
        'pending_validation' => 'Pending verification',
        'connected' => 'Connected',
        'failed' => 'Failed',
        'blocked_by_fedex' => 'Carrier support required',
        'sandbox_platform_fallback' => 'Connected for testing',
        'disabled' => 'Disabled',
    ];
    $connectionStatusBadge = fn (string $status) => match ($status) {
        'connected' => 'bg-[#ECFDF5] text-[#047857]',
        'sandbox_platform_fallback' => 'bg-[#FFF7ED] text-[#C2410C]',
        'blocked_by_fedex' => 'bg-[#FEF2F2] text-[#991B1B]',
        'failed' => 'bg-[#FEF2F2] text-[#991B1B]',
        'disabled' => 'bg-[#F1F5F9] text-[#64748B]',
        default => 'bg-[#FEF3C7] text-[#92400E]',
    };
    $rateLabels = [
        'flat' => 'Fixed price',
        'free' => 'Free',
        'manual' => 'Manual price',
        'carrier_calculated_later' => 'Live carrier rates',
    ];
    $statusBadge = fn (bool $active) => $active ? 'bg-[#ECFDF5] text-[#047857]' : 'bg-[#F1F5F9] text-[#64748B]';
    $deliverySetup = $deliverySetup ?? [];
    $showSupportAdvanced = (string) request('support') === '1';
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="Delivery" lead="Ship-from, delivery areas, checkout options, and FedEx." />
@endsection

@section('content')
    <div
        class="w-full max-w-none settings-workspace-fluid settings-hub ui-page-enter"
        id="shipping-page"
        data-zone-store-url="{{ route('settings.shipping.zones.store') }}"
        data-method-store-url="{{ route('settings.shipping.methods.store') }}"
    >
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @include('user_view.shipping.tabs.overview')

        @if ($showSupportAdvanced)
            <details id="delivery-advanced-panel" class="settings-hub-details mt-6" open>
                <summary class="cursor-pointer list-none rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                    <p class="font-semibold text-[#0F172A]">Support delivery tools</p>
                    <p class="mt-0.5 text-xs text-[#64748B]">Opened for this visit only — not part of normal Delivery setup.</p>
                </summary>
                <div class="settings-hub-details-body !border-0 !bg-transparent !p-0 !shadow-none mt-3">
                    @include('user_view.shipping.tabs.advanced')
                </div>
            </details>
        @endif

        @if (! ($deliverySetup['is_ready'] ?? false)
            && ! optional($selectedStore ?? $currentStore ?? null)->delivery_setup_completed_at
            && ($canManageShipping ?? false))
            @php
                $next = collect($deliverySetup['health_items'] ?? [])->first();
                $nextHref = $next['action_href'] ?? route('settings.delivery.setup');
                $nextLabel = $next['action_label'] ?? 'Continue setup';
                $nextMessage = $next['message'] ?? 'Finish delivery setup so customers can see delivery options at checkout.';
            @endphp
            <x-ui.sticky-next :message="$nextMessage" :action-label="$nextLabel" :action-href="$nextHref" />
        @endif
    </div>
    @if ($canManageShipping ?? false)
        @include('user_view.shipping.partials.drawers')
    @endif

    @vite(['resources/js/delivery/hub.js'])
@endsection
