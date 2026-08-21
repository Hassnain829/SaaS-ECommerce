@extends('layouts.user.user-sidebar')

@section('title', 'Packages — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Packages" lead="Default box sizes used for live carrier rates and labels.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation') }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back to Delivery</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="settings-workspace max-w-[960px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @include('user_view.shipping.tabs.packages', ['hideShippingDefaults' => true])
    </div>
@endsection
