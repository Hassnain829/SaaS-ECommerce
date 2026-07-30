<section class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
    <h2 class="text-xl font-semibold text-[#0F172A]">Ship-from location</h2>
    <p class="mt-2 text-sm text-[#64748B]">Choose the fulfillment location that will ship USPS packages for this store.</p>

    @if ($canManageShipping ?? false)
        <form method="POST" action="{{ route('settings.shipping.usps-merchant.origin.update', $account) }}" class="mt-5 space-y-4">
            @csrf
            <label class="block space-y-1">
                <span class="text-xs font-semibold text-[#64748B]">Ship-from location</span>
                <select name="origin_location_id" required class="h-10 w-full rounded-lg border border-[#CBD5E1] px-3 text-sm">
                    <option value="">Select origin</option>
                    @foreach ($locations as $entry)
                        <option value="{{ $entry['location']->id }}" @selected($account->defaultOriginLocationId() === $entry['location']->id) @disabled(! ($entry['readiness']->ready ?? false))>{{ $entry['location']->name }}</option>
                    @endforeach
                </select>
            </label>
            <div class="flex flex-wrap gap-3">
                <button type="submit" class="rounded-lg border border-[#CBD5E1] bg-white px-4 py-2 text-sm font-semibold text-[#475569]">Update ship-from</button>
                <a href="{{ route('settings.shipping.usps-merchant.wizard', ['carrierAccount' => $account, 'step' => 'identifiers']) }}" class="inline-flex h-10 items-center rounded-lg bg-brand px-4 text-sm font-bold text-white">Continue</a>
            </div>
        </form>
    @endif
</section>
