@extends('layouts.user.user-sidebar')

@section('title', 'Connect FedEx — '.config('app.name'))

@section('topbar')
    <x-ui.merchant-topbar title="Connect FedEx" lead="Connect a merchant-owned FedEx account for this store.">
        <x-slot:actions>
            <a href="{{ route('shippingAutomation', ['tab' => 'advanced']) }}" class="inline-flex h-9 items-center rounded-lg border border-stone-200 bg-white px-3 text-xs font-semibold text-stone-700">Back</a>
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@section('content')
    @php
        $productionEnabled = (bool) ($productionEnabled ?? false);
        $defaultEnvironment = $defaultEnvironment ?? 'sandbox';
    @endphp
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
            <p class="mt-3 rounded-lg border border-[#CDE5DB] bg-[#F4FBF8] px-3 py-2 text-sm text-[#0A4335]">
                FedEx will charge shipping costs directly to your connected FedEx account.
            </p>
            @unless ($productionEnabled)
                <p class="mt-3 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">Live FedEx connection opens after platform production setup is complete. Sandbox connection is available for testing.</p>
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

                <fieldset class="space-y-2">
                    <legend class="text-xs font-semibold text-[color:var(--color-ink-muted)]">FedEx environment</legend>
                    <label class="flex cursor-pointer items-start gap-3 rounded-lg border border-[color:var(--color-border)] bg-white px-3 py-3">
                        <input
                            type="radio"
                            name="environment"
                            value="sandbox"
                            class="mt-1"
                            @checked(old('environment', $defaultEnvironment) === 'sandbox')
                        >
                        <span>
                            <span class="block text-sm font-semibold text-[color:var(--color-ink)]">Sandbox test account</span>
                            <span class="mt-0.5 block text-xs text-[color:var(--color-ink-muted)]">Safe for testing. Does not create production shipping charges.</span>
                        </span>
                    </label>
                    <label class="flex items-start gap-3 rounded-lg border border-[color:var(--color-border)] px-3 py-3 {{ $productionEnabled ? 'cursor-pointer bg-white' : 'cursor-not-allowed bg-stone-50 opacity-70' }}">
                        <input
                            type="radio"
                            name="environment"
                            value="live"
                            class="mt-1"
                            @checked(old('environment', $defaultEnvironment) === 'live')
                            @disabled(! $productionEnabled)
                        >
                        <span>
                            <span class="block text-sm font-semibold text-[color:var(--color-ink)]">Live FedEx account</span>
                            <span class="mt-0.5 block text-xs text-[color:var(--color-ink-muted)]">
                                @if ($productionEnabled)
                                    Connect a real merchant FedEx account. Shipping charges bill to that account.
                                @else
                                    Unavailable until production credentials and approval are configured on the platform.
                                @endif
                            </span>
                        </span>
                    </label>
                </fieldset>

                <x-ui.button type="submit">Continue to agreement</x-ui.button>
            </form>
        </x-ui.panel>
    </div>
@endsection
