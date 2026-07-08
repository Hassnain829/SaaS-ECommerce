@extends('layouts.user.user-sidebar')

@section('title', 'Manage USPS | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Manage USPS connection</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">{{ $account->display_name }}</p>
        </div>
        <a href="{{ route('shippingAutomation', ['tab' => 'advanced']) }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back</a>
    </header>
@endsection

@section('content')
    <div class="mx-auto max-w-[920px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @include('user_view.usps_merchant.partials.wizard_progress', ['progress' => $progress])

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">USPS Merchant Account</p>
                    <h2 class="mt-1 text-2xl font-semibold text-[#0F172A]">{{ $account->display_name }}</h2>
                    <p class="mt-2 text-sm leading-6 text-[#64748B]">{{ $presenter->merchantSummary() }}</p>
                </div>
                <span class="inline-flex h-fit rounded-full px-3 py-1 text-xs font-bold {{ $presenter->badgeClass() }}">{{ $presenter->authorizationStatusLabel() }}</span>
            </div>
        </section>

        @if ($account->usps_authorization_status === \App\Models\CarrierAccount::USPS_AUTH_VERIFYING)
            <section class="rounded-2xl border border-[#FEF3C7] bg-[#FFFBEB] px-5 py-4">
                @if ($context->hasOAuthAuthorizationVerified())
                    <h3 class="text-sm font-semibold text-[#047857]">Label Provider authorization verified</h3>
                    <p class="mt-2 text-sm text-[#065F46]">
                        USPS confirmed your Label Provider authorization. Ship enrollment and postage account verification are the next steps before rates and labels can be enabled.
                    </p>
                    @if ($context->oauthAuthorizationVerifiedAt())
                        <p class="mt-2 text-xs text-[#047857]">Verified {{ $context->oauthAuthorizationVerifiedAt() }}</p>
                    @endif
                @else
                    <h3 class="text-sm font-semibold text-[#92400E]">Waiting for authorization verification</h3>
                    <p class="mt-2 text-sm text-[#92400E]">
                        @if ($merchantOAuthAvailable ?? false)
                            Use Verify with USPS below after completing USPS authorization, or authorize again with the secure OAuth button from the authorization step.
                        @else
                            Your Label Provider authorization is recorded. Automated verification will be available once BmyBrand platform Label Provider OAuth is enabled.
                        @endif
                    </p>
                    @if ($context->authorizationAcknowledgedAt())
                        <p class="mt-2 text-xs text-[#B45309]">Authorization acknowledged {{ $context->authorizationAcknowledgedAt() }}</p>
                    @endif
                @endif
            </section>
        @endif

        <div class="grid gap-6 lg:grid-cols-2">
            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Connection overview</h3>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">Authorization</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $presenter->authorizationStatusLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">USPS Ship enrollment</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $presenter->enrollmentStatusLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">Postage account</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $presenter->paymentStatusLabel() }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">Billing</dt>
                        <dd class="font-medium text-[#0F172A]">Your USPS payment account</dd>
                    </div>
                    @if ($account->defaultOriginLocation)
                        <div class="flex justify-between gap-4">
                            <dt class="text-[#64748B]">Ship-from</dt>
                            <dd class="font-medium text-[#0F172A]">{{ $account->defaultOriginLocation->name }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <h3 class="text-lg font-semibold text-[#0F172A]">Account identifiers</h3>
                <p class="mt-2 text-sm text-[#64748B]">Masked for security. Full values are encrypted and used only for USPS verification later.</p>
                <dl class="mt-4 space-y-3 text-sm">
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">CRID</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $context->merchantCridMasked() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">Mailer ID</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $context->merchantMidMasked() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4 border-b border-[#F1F5F9] pb-3">
                        <dt class="text-[#64748B]">EPA</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $context->merchantEpaMasked() ?? '—' }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-[#64748B]">Manifest MID</dt>
                        <dd class="font-medium text-[#0F172A]">{{ $context->manifestMidMasked() ?? '—' }}</dd>
                    </div>
                </dl>
            </section>
        </div>

        <section class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Capabilities</h3>
            <ul class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Rates: not enabled yet</li>
                <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Labels: not enabled yet</li>
                <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Tracking: not enabled yet</li>
                <li class="rounded-lg border border-[#E2E8F0] bg-[#F8FAFC] px-3 py-2 text-[#64748B]">Pickup: not enabled yet</li>
            </ul>
        </section>

        @if ($canManageShipping ?? false)
            <section class="rounded-2xl border border-[#E2E8F0] bg-[#F8FAFC] p-5">
                <h3 class="text-sm font-semibold text-[#0F172A]">Connection actions</h3>
                <div class="mt-3 flex flex-wrap gap-3">
                    <a href="{{ route('settings.shipping.usps-merchant.wizard', ['carrierAccount' => $account, 'step' => 'identifiers']) }}" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Update USPS account details</a>
                    <a href="{{ route('settings.shipping.usps-merchant.wizard', ['carrierAccount' => $account, 'step' => 'origin']) }}" class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">Change ship-from location</a>
                    <form method="POST" action="{{ route('settings.shipping.usps-merchant.reauthorize', $account) }}">
                        @csrf
                        <button type="submit" class="inline-flex h-10 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Reauthorize in USPS portal</button>
                    </form>
                    @if ($merchantOAuthAvailable ?? false)
                        <form method="POST" action="{{ route('settings.shipping.usps-merchant.verify', $account) }}">
                            @csrf
                            <button type="submit" class="inline-flex h-10 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">Verify with USPS</button>
                        </form>
                        @if (! $context->hasOAuthAuthorizationVerified())
                            <a href="{{ route('settings.shipping.usps-merchant.oauth.start', $account) }}" class="inline-flex h-10 items-center rounded-lg border border-[#BFDBFE] bg-[#EFF6FF] px-4 text-sm font-semibold text-[#1D4ED8]">Authorize with USPS</a>
                        @endif
                    @else
                        <span class="inline-flex h-10 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#64748B]">Verify with USPS — available after platform OAuth approval</span>
                    @endif
                </div>

                <details class="mt-5">
                    <summary class="cursor-pointer text-sm font-semibold text-[#0F172A]">Diagnostics and activity</summary>
                    <div class="mt-4 space-y-3">
                        @forelse ($account->apiEvents as $event)
                            <div class="rounded-lg border border-[#E2E8F0] bg-white px-3 py-2 text-xs text-[#475569]">
                                <span class="font-semibold text-[#0F172A]">{{ str($event->action)->replace('_', ' ')->title() }}</span>
                                — {{ str($event->status)->replace('_', ' ')->title() }}
                                @if ($event->created_at)
                                    <span class="text-[#94A3B8]">· {{ $event->created_at->diffForHumans() }}</span>
                                @endif
                            </div>
                        @empty
                            <p class="text-sm text-[#64748B]">No USPS API activity logged yet.</p>
                        @endforelse
                    </div>
                </details>
            </section>

            <form method="POST" action="{{ route('settings.shipping.usps-merchant.disconnect', $account) }}" onsubmit="return confirm('Disconnect this USPS merchant account?');">
                @csrf
                <button type="submit" class="rounded-lg border border-red-200 bg-white px-4 py-2 text-sm font-semibold text-red-700">Disconnect USPS account</button>
            </form>
        @endif
    </div>
@endsection
