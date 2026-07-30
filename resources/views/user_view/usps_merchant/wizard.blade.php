@extends('layouts.user.user-sidebar')

@section('title', 'Connect USPS | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Connect USPS" lead="Complete merchant authorization for this store.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation', ['tab' => 'advanced']) }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="ui-page-enter mx-auto max-w-[820px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <ul class="list-disc space-y-1 pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @include('user_view.usps_merchant.partials.wizard_progress', ['progress' => $progress])

        @if ($step === \App\Services\Carriers\USPS\Support\USPSMerchantWizard::STEP_ORIGIN)
            @include('user_view.usps_merchant.steps.origin')
        @elseif ($step === \App\Services\Carriers\USPS\Support\USPSMerchantWizard::STEP_IDENTIFIERS)
            @include('user_view.usps_merchant.steps.identifiers')
        @elseif ($step === \App\Services\Carriers\USPS\Support\USPSMerchantWizard::STEP_AUTHORIZATION)
            @include('user_view.usps_merchant.steps.authorization')
        @endif
    </div>
@endsection
