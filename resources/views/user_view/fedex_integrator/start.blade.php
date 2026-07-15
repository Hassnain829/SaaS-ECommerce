@extends('layouts.user.user-sidebar')

@section('title', 'Connect FedEx | BaaS Core')

@section('topbar')
    <header class="sticky top-0 z-30 flex h-16 items-center justify-between gap-3 border-b border-[color:var(--color-border)] bg-white/95 px-4 backdrop-blur md:px-8">
        <div>
            <h1 class="font-heading text-lg font-semibold text-[color:var(--color-ink)] md:text-xl">Connect FedEx</h1>
            <p class="hidden text-xs text-[color:var(--color-ink-muted)] sm:block">Connect your merchant-owned FedEx account for this store.</p>
        </div>
        <x-ui.button variant="secondary" :href="route('shippingAutomation', ['tab' => 'advanced'])">Back to Delivery</x-ui.button>
    </header>
@endsection

@section('content')
    <div class="ui-page-enter mx-auto max-w-[760px] space-y-6">
        @include('user_view.partials.flash_success')
        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        <x-ui.stepper
            :current="'origin'"
            :steps="[
                ['key' => 'origin', 'label' => 'Ship-from'],
                ['key' => 'agreement', 'label' => 'Agreement'],
                ['key' => 'account', 'label' => 'Account details'],
                ['key' => 'verify', 'label' => 'Verify'],
                ['key' => 'done', 'label' => 'Done'],
            ]"
        />

        <x-ui.panel>
            <h2 class="text-xl font-semibold text-[color:var(--color-ink)]">Choose where orders ship from</h2>
            <p class="mt-2 text-sm text-[color:var(--color-ink-muted)]">FedEx uses this location as your default ship-from address. It must be complete and ready for carriers.</p>
            @unless ($productionEnabled)
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Live FedEx shipping opens after platform approval. Sandbox connection is available for testing.</p>
            @endunless

            <form method="POST" action="{{ route('settings.shipping.fedex-integrator.origin') }}" class="mt-5 space-y-4">
                @csrf
                <label class="block space-y-1">
                    <span class="text-xs font-semibold text-[color:var(--color-ink-muted)]">Ship-from location</span>
                    <select name="origin_location_id" required class="h-10 w-full rounded-lg border border-[color:var(--color-border-strong)] px-3 text-sm">
                        <option value="">Select location</option>
                        @foreach ($locations as $entry)
                            <option value="{{ $entry['location']->id }}" @disabled(! ($entry['readiness']->ready ?? false))>{{ $entry['location']->name }}</option>
                        @endforeach
                    </select>
                </label>
                <input type="hidden" name="environment" value="sandbox">
                <x-ui.button type="submit">Continue to agreement</x-ui.button>
            </form>
        </x-ui.panel>
    </div>
@endsection
