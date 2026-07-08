@extends('layouts.user.user-sidebar')

@section('title', 'Connect USPS | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Connect USPS</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">{{ $wizard->stepLabel($step) }} — {{ $account->display_name }}</p>
        </div>
        <a href="{{ route('shippingAutomation', ['tab' => 'advanced']) }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back</a>
    </header>
@endsection

@section('content')
    <div class="mx-auto max-w-[820px] space-y-6">
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
