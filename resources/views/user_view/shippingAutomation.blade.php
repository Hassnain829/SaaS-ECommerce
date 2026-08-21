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
@endphp

@section('topbar')
    <x-ui.merchant-topbar title="Delivery" lead="Ship-from, delivery areas, checkout options, and FedEx." />
@endsection

@section('content')
    <div
        class="settings-workspace-fluid settings-hub ui-page-enter"
        id="shipping-page"
        data-zone-store-url="{{ route('settings.shipping.zones.store') }}"
        data-method-store-url="{{ route('settings.shipping.methods.store') }}"
    >
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @include('user_view.shipping.tabs.overview')
    </div>
@endsection

@push('overlays')
    @if ($canManageShipping ?? false)
        @include('user_view.shipping.partials.drawers')
    @endif
@endpush

@push('scripts')
    @vite(['resources/js/delivery/hub.js'])
@endpush
