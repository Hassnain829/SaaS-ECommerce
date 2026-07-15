@php
    $setup = $deliverySetup ?? [];
    $healthItems = collect($setup['health_items'] ?? []);
    $isReady = (bool) ($setup['is_ready'] ?? false);

    $rowIcon = function (?string $status): array {
        return match ($status) {
            'complete', 'added', 'included' => ['class' => 'settings-checklist-icon-ready', 'glyph' => '✓'],
            'needs_attention' => ['class' => 'settings-checklist-icon-pending', 'glyph' => '!'],
            'optional', 'off' => ['class' => 'settings-checklist-icon-optional', 'glyph' => '○'],
            default => ['class' => 'settings-checklist-icon-missing', 'glyph' => '•'],
        };
    };

    $checklist = [
        [
            'key' => 'ship_from',
            'label' => 'Step 1',
            'title' => 'Ship-from location',
            'question' => 'Where do you ship from?',
            'action_href' => route('settings.locations.index'),
            'action_label' => 'Manage locations',
        ],
        [
            'key' => 'delivery_areas',
            'label' => 'Step 2',
            'title' => 'Delivery areas',
            'question' => 'Where do you deliver?',
            'action_tab' => 'areas',
            'action_label' => 'Manage areas',
        ],
        [
            'key' => 'delivery_options',
            'label' => 'Step 3',
            'title' => 'Checkout delivery options',
            'question' => 'What do customers see at checkout?',
            'action_tab' => 'options',
            'action_label' => 'Manage options',
        ],
        [
            'key' => 'delivery_providers',
            'label' => 'Optional',
            'title' => 'Delivery provider',
            'question' => 'Connect a carrier for labels and rates',
            'action_href' => route('shipping.carriers.connect.index'),
            'action_label' => 'Connect provider',
        ],
    ];
@endphp

<section class="settings-hub">
    <header class="settings-hub-hero">
        <div class="settings-hub-hero-top">
            <div>
                <p class="settings-hub-kicker">Delivery setup</p>
                <h2 class="settings-hub-title">{{ $isReady ? 'Ready for checkout' : 'Finish delivery setup' }}</h2>
                <p class="settings-hub-lead">
                    Manage ship-from locations, delivery areas, checkout delivery options, and optional delivery providers.
                </p>
            </div>
            <span @class([
                'settings-hub-badge',
                'settings-hub-badge-ready' => $isReady,
                'settings-hub-badge-pending' => ! $isReady,
            ])>
                {{ $isReady ? 'Ready' : 'Needs attention' }}
            </span>
        </div>
    </header>

    @foreach ($healthItems as $item)
        @php
            $isError = ($item['severity'] ?? 'warning') === 'error';
        @endphp
        <div @class(['settings-alert', 'settings-alert-error' => $isError])>
            <strong>{{ $item['label'] ?? 'Setup item' }}:</strong> {{ $item['message'] ?? '' }}
            @if ($canManageShipping ?? false)
                @if (! empty($item['action_href']))
                    <a href="{{ $item['action_href'] }}" class="ml-2 font-semibold underline">{{ $item['action_label'] ?? 'Fix' }}</a>
                @elseif (! empty($item['action_tab']))
                    <button type="button" data-shipping-tab="{{ $item['action_tab'] }}" class="ml-2 font-semibold underline">{{ $item['action_label'] ?? 'Fix' }}</button>
                @endif
            @endif
        </div>
    @endforeach

    <div class="settings-checklist">
        @foreach ($checklist as $row)
            @php
                $summary = $setup[$row['key']] ?? [];
                $status = $summary['status'] ?? 'missing';
                $icon = $rowIcon($status);
            @endphp
            <article class="settings-checklist-row">
                <span class="settings-checklist-icon {{ $icon['class'] }}" aria-hidden="true">{{ $icon['glyph'] }}</span>
                <div>
                    <p class="settings-checklist-label">{{ $row['label'] }} · {{ $row['question'] }}</p>
                    <h3 class="settings-checklist-title">{{ $summary['title'] ?? $row['title'] }}</h3>
                    <p class="settings-checklist-detail">{{ $summary['detail'] ?? 'Not configured yet.' }}</p>
                </div>
                @if ($canManageShipping ?? false)
                    @if (! empty($row['action_href']))
                        <a href="{{ $row['action_href'] }}" class="settings-checklist-action settings-checklist-action-primary">{{ $row['action_label'] }}</a>
                    @elseif (! empty($row['action_tab']))
                        <button type="button" data-shipping-tab="{{ $row['action_tab'] }}" class="settings-checklist-action settings-checklist-action-secondary">{{ $row['action_label'] }}</button>
                    @endif
                @endif
            </article>
        @endforeach
    </div>

    @if (! $isReady && ($canManageShipping ?? false))
        <div class="mt-4">
            <x-ui.empty-state
                title="Finish delivery setup"
                lead="Answer a few setup questions so customers can see delivery options at checkout."
                action-label="Start delivery setup"
                :action-href="route('settings.delivery.setup.ship-from')"
            />
        </div>
    @endif

    <p class="text-sm text-[color:var(--color-ink-muted)]">
        Tax is configured separately in
        <a href="{{ route('settings.taxes.index') }}" class="font-semibold text-[color:var(--color-brand)] hover:underline">Checkout &amp; tax</a>.
    </p>
</section>
