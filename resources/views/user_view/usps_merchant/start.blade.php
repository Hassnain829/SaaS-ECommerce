@extends('layouts.user.user-sidebar')

@section('title', 'Connect USPS | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Connect USPS" lead="Review requirements and choose a ship-from location.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation', ['tab' => 'advanced']) }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    <div class="mx-auto max-w-[820px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] px-5 py-4">
            <h2 class="text-lg font-semibold text-[#0F172A]">Merchant-owned USPS connection</h2>
            <p class="mt-2 text-sm leading-6 text-[#475569]">
                Postage for labels is charged to your Enterprise Payment Account (EPA).
                {{ $labelProviderName }} is your label provider only — we do not pay for your labels.
                You never paste API keys, passwords, or payment login credentials here.
            </p>
        </section>

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-[#0F172A]">What you need from USPS</h2>
            <ol class="mt-4 space-y-3">
                @foreach ($requirements as $index => $requirement)
                    <li class="flex gap-3 rounded-xl border border-[#E2E8F0] bg-[#F8FAFC] px-4 py-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand text-xs font-bold text-white">{{ $index + 1 }}</span>
                        <div>
                            <p class="font-semibold text-[#0F172A]">{{ $requirement['label'] }}</p>
                            <p class="mt-1 text-sm text-[#64748B]">{{ $requirement['description'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
            <p class="mt-4 text-sm text-[#64748B]">
                <a href="{{ $businessPortalUrl }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#1D4ED8] underline">Manage USPS Business Account</a>
            </p>
        </section>

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-[#0F172A]">Continue setup</h2>
            <p class="mt-2 text-sm text-[#64748B]">Choose your ship-from fulfillment location to begin the connection wizard.</p>

            @if ($canManageShipping ?? false)
                <form method="POST" action="{{ route('settings.shipping.usps-merchant.origin') }}" class="mt-5 space-y-4">
                    @csrf
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Display name (optional)</span>
                        <input type="text" name="display_name" maxlength="120" placeholder="{{ $selectedStore->name }} USPS account" class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                    </label>
                    <label class="block space-y-1">
                        <span class="text-xs font-semibold text-[#64748B]">Ship-from location</span>
                        <select name="origin_location_id" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                            <option value="">Select origin</option>
                            @foreach ($locations as $entry)
                                <option value="{{ $entry['location']->id }}" @disabled(! ($entry['readiness']->ready ?? false))>{{ $entry['location']->name }}</option>
                            @endforeach
                        </select>
                    </label>
                    @if ($locations->every(fn ($entry) => ! ($entry['readiness']->ready ?? false)))
                        <p class="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Set up a carrier-ready US fulfillment origin first.
                            <a href="{{ route('settings.locations.index') }}" class="font-semibold underline">Manage locations</a>
                        </p>
                    @endif
                    <button type="submit" class="rounded-lg bg-brand px-4 py-2 text-sm font-bold text-white" @disabled($locations->every(fn ($entry) => ! ($entry['readiness']->ready ?? false)))>Continue to USPS account details</button>
                </form>
            @endif
        </section>
    </div>
@endsection
