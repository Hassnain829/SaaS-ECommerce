@php
    $setup = $deliverySetup ?? [];
    $healthItems = collect($setup['health_items'] ?? []);
    $errors = $healthItems->where('severity', 'error');
    $warnings = $healthItems->where('severity', 'warning');
    $statusStyles = [
        'complete' => ['badge' => 'bg-[#ECFDF5] text-[#047857]', 'label' => 'Complete'],
        'needs_attention' => ['badge' => 'bg-[#FEF3C7] text-[#92400E]', 'label' => 'Needs attention'],
        'missing' => ['badge' => 'bg-[#FEF2F2] text-[#991B1B]', 'label' => 'Not configured'],
        'optional' => ['badge' => 'bg-[#F1F5F9] text-[#64748B]', 'label' => 'Optional'],
        'off' => ['badge' => 'bg-[#F1F5F9] text-[#64748B]', 'label' => 'Off'],
        'added' => ['badge' => 'bg-[#EFF6FF] text-[#1D4ED8]', 'label' => 'Active'],
        'included' => ['badge' => 'bg-[#EFF6FF] text-[#1D4ED8]', 'label' => 'Active'],
    ];
    $cardStatus = fn (?string $status) => $statusStyles[$status ?? 'missing'] ?? $statusStyles['missing'];
@endphp

<section class="space-y-6">
    <div class="rounded-2xl border border-[#CBD5E1] bg-white p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.14em] text-[#94A3B8]">Delivery setup</p>
                <h2 class="mt-1 text-xl font-poppins font-semibold text-[#0F172A]">
                    @if ($setup['is_ready'] ?? false)
                        Delivery setup is ready
                    @else
                        Finish your delivery setup
                    @endif
                </h2>
                <p class="mt-2 max-w-2xl text-sm leading-relaxed text-[#64748B]">
                    Answer four merchant questions: where orders ship from, where you deliver, what customers see at checkout, and whether delivery is ready.
                    Tax stays under <a href="{{ route('settings.taxes.index') }}" class="font-semibold text-[#1D4ED8] hover:underline">Checkout &amp; tax</a>.
                </p>
            </div>
            @if ($canManageShipping ?? false)
                <div class="flex flex-wrap gap-2">
                    <a href="{{ ($setup['is_ready'] ?? false) ? route('settings.delivery.setup.review') : route('settings.delivery.setup.ship-from') }}" class="inline-flex h-10 shrink-0 items-center rounded-lg bg-[#0052CC] px-4 text-sm font-bold text-white">
                        {{ ($setup['is_ready'] ?? false) ? 'Review delivery setup' : 'Set up delivery' }}
                    </a>
                    <button type="button" data-shipping-tab="advanced" class="inline-flex h-10 shrink-0 items-center rounded-lg border border-[#CBD5E1] bg-white px-4 text-sm font-semibold text-[#475569]">
                        Open advanced delivery settings
                    </button>
                </div>
            @endif
        </div>
    </div>

    @if ($errors->isNotEmpty() || $warnings->isNotEmpty())
        <div class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
            <h3 class="text-lg font-semibold text-[#0F172A]">Setup health</h3>
            <ul class="mt-4 space-y-3">
                @foreach ($healthItems as $item)
                    @php
                        $style = match ($item['severity'] ?? 'warning') {
                            'error' => 'border-[#FECACA] bg-[#FEF2F2] text-[#991B1B]',
                            default => 'border-[#FDE68A] bg-[#FFFBEB] text-[#92400E]',
                        };
                    @endphp
                    <li class="flex flex-col gap-3 rounded-xl border px-4 py-3 sm:flex-row sm:items-center sm:justify-between {{ $style }}">
                        <div>
                            <p class="text-sm font-semibold">{{ $item['label'] ?? 'Setup item' }}</p>
                            <p class="mt-1 text-sm">{{ $item['message'] ?? '' }}</p>
                        </div>
                        @if ($canManageShipping ?? false)
                            @if (! empty($item['action_href']))
                                <a href="{{ $item['action_href'] }}" class="inline-flex h-9 shrink-0 items-center rounded-lg bg-white px-3 text-sm font-semibold text-[#0F172A] shadow-sm">
                                    {{ $item['action_label'] ?? 'Fix' }}
                                </a>
                            @elseif (! empty($item['action_tab']))
                                <button type="button" data-shipping-tab="{{ $item['action_tab'] }}" class="inline-flex h-9 shrink-0 items-center rounded-lg bg-white px-3 text-sm font-semibold text-[#0F172A] shadow-sm">
                                    {{ $item['action_label'] ?? 'Fix' }}
                                </button>
                            @endif
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid gap-4 lg:grid-cols-2">
        @php
            $summaryCards = [
                ['key' => 'ship_from', 'heading' => 'Where do you ship from?', 'edit_href' => route('settings.locations.index'), 'edit_label' => 'Edit ship-from location'],
                ['key' => 'delivery_areas', 'heading' => 'Where do you deliver?', 'edit_tab' => 'areas', 'edit_label' => 'Edit delivery areas'],
                ['key' => 'delivery_options', 'heading' => 'What do customers see at checkout?', 'edit_tab' => 'options', 'edit_label' => 'Edit delivery options'],
                ['key' => 'delivery_providers', 'heading' => 'Delivery provider', 'edit_tab' => 'providers', 'edit_label' => 'Edit delivery providers'],
            ];
        @endphp

        @foreach ($summaryCards as $card)
            @php
                $summary = $setup[$card['key']] ?? [];
                $status = $cardStatus($summary['status'] ?? 'missing');
            @endphp
            <article class="rounded-2xl border border-[#E2E8F0] bg-white p-5 shadow-sm">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs font-bold uppercase tracking-[1px] text-[#64748B]">{{ $card['heading'] }}</p>
                        <h3 class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $summary['title'] ?? 'Not configured' }}</h3>
                    </div>
                    <span class="rounded-full px-2.5 py-1 text-xs font-bold {{ $status['badge'] }}">{{ $status['label'] }}</span>
                </div>
                <p class="mt-2 text-sm leading-relaxed text-[#64748B]">{{ $summary['detail'] ?? '' }}</p>
                @if ($canManageShipping ?? false)
                    <div class="mt-4">
                        @if (! empty($card['edit_href']))
                            <a href="{{ $card['edit_href'] }}" class="text-sm font-semibold text-[#1D4ED8]">{{ $card['edit_label'] }}</a>
                        @elseif (! empty($card['edit_tab']))
                            <button type="button" data-shipping-tab="{{ $card['edit_tab'] }}" class="text-sm font-semibold text-[#1D4ED8]">{{ $card['edit_label'] }}</button>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </div>

    @php($taxSummary = $setup['tax_summary'] ?? [])
    <article class="rounded-2xl border border-[#BFDBFE] bg-[#EFF6FF] p-5 shadow-sm">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-bold uppercase tracking-[1px] text-[#1D4ED8]">Checkout tax (read-only)</p>
                <h3 class="mt-2 text-lg font-semibold text-[#0F172A]">{{ $taxSummary['title'] ?? 'Tax is off' }}</h3>
                <p class="mt-2 text-sm leading-relaxed text-[#475569]">{{ $taxSummary['detail'] ?? 'Configure tax separately from delivery setup.' }}</p>
            </div>
            <a href="{{ $taxSummary['edit_href'] ?? route('settings.taxes.index') }}" class="inline-flex h-10 shrink-0 items-center rounded-lg border border-[#93C5FD] bg-white px-4 text-sm font-semibold text-[#1D4ED8]">
                Edit tax settings
            </a>
        </div>
    </article>

    @if ($canManageShipping ?? false)
        <div class="rounded-2xl border border-dashed border-[#CBD5E1] bg-[#F8FAFC] p-5">
            <h3 class="text-sm font-semibold text-[#0F172A]">Quick actions</h3>
            <div class="mt-3 flex flex-wrap gap-2">
                <a href="{{ route('settings.locations.index') }}" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm font-semibold text-[#475569]">Add ship-from address</a>
                <button type="button" data-shipping-tab="areas" data-open-drawer="zone-add" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm font-semibold text-[#475569]">Choose a delivery area</button>
                <button type="button" data-shipping-tab="options" data-open-drawer="method-add" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm font-semibold text-[#475569]">Add a delivery option</button>
                <button type="button" data-shipping-tab="options" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm font-semibold text-[#475569]">Fix checkout visibility</button>
                <a href="{{ route('settings.delivery.test-address') }}" class="inline-flex h-9 items-center rounded-lg border border-[#CBD5E1] bg-white px-3 text-sm font-semibold text-[#475569]">Test a customer address</a>
            </div>
        </div>
    @endif
</section>
