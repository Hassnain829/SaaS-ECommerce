@extends('layouts.user.user-sidebar')

@section('title', 'Delivery setup | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Delivery setup" lead="Guided setup for ship-from, delivery areas, and checkout options.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation') }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to Delivery</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
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
