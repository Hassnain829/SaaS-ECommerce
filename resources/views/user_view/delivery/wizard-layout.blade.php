@extends('layouts.user.user-sidebar')

@section('title', 'Delivery setup | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-poppins font-semibold">Delivery setup</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">Guided setup for ship-from, delivery areas, and checkout options.</p>
        </div>
        <a href="{{ route('shippingAutomation') }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569] hover:bg-[#F8FAFC]">Back to Delivery</a>
    </header>
@endsection

@section('content')
    <div class="settings-workspace max-w-[1280px] space-y-6">
        @include('user_view.partials.flash_success')

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @include('user_view.delivery.partials.wizard-steps', ['step' => $step ?? 1])

        @yield('wizard-content')
    </div>
@endsection
