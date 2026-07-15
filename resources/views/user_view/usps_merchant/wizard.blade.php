@extends('layouts.user.user-sidebar')

@section('title', 'Connect USPS | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[color:var(--color-border)] bg-white/95 px-4 backdrop-blur md:px-8">
        <div>
            <h1 class="font-heading text-lg font-semibold text-[color:var(--color-ink)] md:text-xl">Connect USPS</h1>
            <p class="hidden text-xs text-[color:var(--color-ink-muted)] sm:block">{{ $wizard->stepLabel($step) }} — {{ $account->display_name }}</p>
        </div>
        <x-ui.button variant="secondary" :href="route('shippingAutomation', ['tab' => 'advanced'])">Back to Delivery</x-ui.button>
    </header>
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
