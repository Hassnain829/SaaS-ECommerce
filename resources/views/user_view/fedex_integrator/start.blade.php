@extends('layouts.user.user-sidebar')

@section('title', 'Connect FedEx | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 h-16 bg-white border-b border-[#E2E8F0] px-4 md:px-8 flex items-center justify-between gap-3">
        <div>
            <h1 class="text-lg md:text-xl font-poppins font-semibold text-[#0F172A]">Connect FedEx</h1>
            <p class="hidden text-xs text-[#64748B] sm:block">FedEx Integrator Provider — merchant-owned account through platform registration.</p>
        </div>
        <a href="{{ route('shippingAutomation', ['tab' => 'carriers']) }}" class="ml-auto inline-flex h-10 items-center rounded-lg border border-[#E2E8F0] bg-white px-4 text-sm font-semibold text-[#475569]">Back</a>
    </header>
@endsection

@section('content')
    <div class="mx-auto max-w-[760px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
            <h2 class="text-xl font-semibold text-[#0F172A]">Step 1 — Choose ship-from fulfillment origin</h2>
            <p class="mt-2 text-sm text-[#64748B]">FedEx registration uses this origin as your default ship-from location. The address must be carrier-ready.</p>
            @unless ($productionEnabled)
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">FedEx production onboarding is pending platform validation approval. Sandbox validation mode is available for development.</p>
            @endunless

            <form method="POST" action="{{ route('settings.shipping.fedex-integrator.origin') }}" class="mt-5 space-y-4">
                @csrf
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[#64748B]">Origin location</span>
                    <select name="origin_location_id" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                        <option value="">Select origin</option>
                        @foreach ($locations as $entry)
                            <option value="{{ $entry['location']->id }}" @disabled(! ($entry['readiness']->ready ?? false))>{{ $entry['location']->name }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="environment" value="sandbox">
                <button type="submit" class="rounded-lg bg-[#0052CC] px-4 py-2 text-sm font-bold text-white">Continue to FedEx EULA</button>
            </form>
        </section>
    </div>
@endsection
