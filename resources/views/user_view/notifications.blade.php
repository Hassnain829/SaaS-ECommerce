@extends('layouts.user.user-sidebar')

@section('title', 'Notifications | BaaS Core')

@section('topbar')
    <x-ui.merchant-topbar title="Notifications" lead="Manage your store alerts and stay updated on logistics and sales.">
        <x-slot:actions>
            @if ($unreadCount > 0)
                <form method="POST" action="{{ route('notifications.mark-all-read') }}">
                    @csrf
                    <button type="submit" class="inline-flex h-9 items-center rounded-lg bg-brand px-4 text-xs font-semibold text-white transition hover:bg-brand-hover active:scale-95">
                        Mark all as read
                    </button>
                </form>
            @endif
        </x-slot:actions>
    </x-ui.merchant-topbar>
@endsection

@push('styles')
<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0&display=swap" rel="stylesheet">
<style>
    .material-symbols-outlined {
        font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 20;
        font-size: 20px;
        line-height: 1;
        user-select: none;
    }
    .notif-unread-dot {
        width: 8px;
        height: 8px;
        background-color: #0052CC;
        border-radius: 50%;
    }
    .notification-row .row-actions {
        opacity: 0;
        transition: opacity 0.2s ease;
    }
    .notification-row:hover .row-actions,
    .notification-row:focus-within .row-actions {
        opacity: 1;
    }
    @media (max-width: 1023px) {
        .notification-row .row-actions {
            opacity: 1;
        }
    }
    .notif-prefs-card[data-locked="1"] .notif-prefs-body {
        opacity: 0.72;
        pointer-events: none;
        user-select: none;
    }
    .notif-prefs-card[data-locked="1"] .notif-prefs-edit {
        display: inline-flex;
    }
    .notif-prefs-card[data-locked="0"] .notif-prefs-edit {
        display: none;
    }
</style>
@endpush

@section('content')
@php
    $activeChip = 'all';
    if (($filters['status'] ?? null) === 'unread') {
        $activeChip = 'unread';
    } elseif (($filters['category'] ?? null) === 'orders') {
        $activeChip = 'orders';
    } elseif (($filters['category'] ?? null) === 'inventory') {
        $activeChip = 'inventory';
    }
    $chipBase = 'inline-flex items-center px-3.5 py-1.5 text-sm rounded-full font-medium whitespace-nowrap transition';
    $chipActive = 'bg-brand text-white shadow-sm shadow-[#0052CC]/20';
    $chipIdle = 'bg-stone-50 text-stone-600 ring-1 ring-inset ring-stone-200 hover:bg-stone-100 hover:text-stone-900';
    $hasAny = collect($groups)->contains(fn ($items) => $items->isNotEmpty());
    $tz = $store->timezone ?: 'UTC';
@endphp

@include('user_view.partials.flash_success')

{{-- Feed 80% / Settings 20% on desktop --}}
<div class="flex flex-col gap-5 xl:flex-row xl:items-start xl:gap-5">
    {{-- Left: notification bars (80%) --}}
    <div class="min-w-0 w-full space-y-5 xl:w-[80%]">
        <form method="GET" action="{{ route('notifications') }}" class="rounded-2xl border border-stone-200/90 bg-white p-3.5 shadow-sm shadow-stone-900/[0.03] sm:p-4">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <div class="relative min-w-0 flex-1">
                    <span class="material-symbols-outlined pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-stone-400">search</span>
                    <input
                        type="search"
                        name="q"
                        value="{{ $filters['q'] ?? '' }}"
                        placeholder="Search by SKU, Order ID or content..."
                        class="h-10 w-full rounded-xl border border-stone-200 bg-stone-50/80 pl-10 pr-4 text-sm text-stone-800 placeholder:text-stone-400 focus:border-[#0052CC] focus:bg-white focus:outline-none focus:ring-2 focus:ring-[#0052CC]/15"
                    >
                </div>
                <div class="hidden h-8 w-px shrink-0 bg-stone-200 sm:block"></div>
                <div class="flex items-center gap-1.5 overflow-x-auto pb-0.5">
                    <a href="{{ route('notifications', array_filter(['q' => $filters['q'] ?? null])) }}"
                       class="{{ $chipBase }} {{ $activeChip === 'all' ? $chipActive : $chipIdle }}">All</a>
                    <a href="{{ route('notifications', array_filter(['status' => 'unread', 'q' => $filters['q'] ?? null])) }}"
                       class="{{ $chipBase }} {{ $activeChip === 'unread' ? $chipActive : $chipIdle }}">Unread</a>
                    <a href="{{ route('notifications', array_filter(['category' => 'orders', 'q' => $filters['q'] ?? null])) }}"
                       class="{{ $chipBase }} {{ $activeChip === 'orders' ? $chipActive : $chipIdle }}">Orders</a>
                    <a href="{{ route('notifications', array_filter(['category' => 'inventory', 'q' => $filters['q'] ?? null])) }}"
                       class="{{ $chipBase }} {{ $activeChip === 'inventory' ? $chipActive : $chipIdle }}">Inventory</a>
                </div>
                <button type="submit" class="sr-only">Search</button>
            </div>
        </form>

        @if ($failedEmails->isNotEmpty())
            <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3">
                <p class="text-sm font-semibold text-red-800">Some emails could not be delivered</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($failedEmails as $failed)
                        <li class="flex flex-wrap items-start justify-between gap-2 text-sm text-red-900">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ $failed->title }}</p>
                                @if ($failed->error_message)
                                    <p class="mt-0.5 line-clamp-2 text-xs text-red-700">{{ $failed->error_message }}</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('notifications.retry', $failed) }}">
                                @csrf
                                <button type="submit" class="font-semibold text-[#0052CC] hover:underline">Retry send</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        @if (! empty($canManageCustomerEmailFailures) && $failedCustomerEmails->isNotEmpty())
            <div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3">
                <p class="text-sm font-semibold text-amber-900">Customer email failures</p>
                <p class="mt-0.5 text-xs text-amber-800">These customer messages did not send. Retry when delivery is ready.</p>
                <ul class="mt-2 space-y-2">
                    @foreach ($failedCustomerEmails as $failed)
                        <li class="flex flex-wrap items-start justify-between gap-2 text-sm text-amber-950">
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium">{{ $failed->recipient_email }}</p>
                                <p class="truncate text-xs text-amber-800">
                                    {{ $eventLabels[$failed->type] ?? $failed->type }}
                                    @if ($failed->failed_at)
                                        · {{ $failed->failed_at->timezone($tz)->diffForHumans() }}
                                    @endif
                                </p>
                                @if ($failed->error_message)
                                    <p class="mt-0.5 line-clamp-2 text-xs text-amber-700">{{ $failed->error_message }}</p>
                                @endif
                            </div>
                            <form method="POST" action="{{ route('notifications.retry-customer', $failed) }}">
                                @csrf
                                <button type="submit" class="font-semibold text-[#0052CC] hover:underline">Retry send</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-stone-200/90 bg-white shadow-sm shadow-stone-900/[0.03]">
            @if ($hasAny)
                @foreach ($groups as $label => $items)
                    @continue($items->isEmpty())
                    <div @class([
                        'border-b border-stone-200 bg-[#F8FAFC] px-5 py-3 sm:px-6',
                        'border-t' => ! $loop->first,
                    ])>
                        <h3 class="text-[11px] font-bold uppercase tracking-[0.14em] text-stone-500">{{ $label }}</h3>
                    </div>
                    <div class="divide-y divide-stone-100">
                        @foreach ($items as $notification)
                            @php
                                $icon = \App\Support\NotificationEvent::uiIcon((string) $notification->type);
                                $unread = ! $notification->is_read;
                                $when = $notification->created_at?->timezone($tz);
                            @endphp
                            <div @class([
                                'notification-row group relative flex items-start gap-3.5 px-4 py-4 transition-colors hover:bg-stone-50/90 sm:items-center sm:gap-4 sm:px-6 sm:py-4.5',
                                'bg-[#F8FBFF]/70' => $unread,
                                'opacity-80' => ! $unread,
                            ])>
                                @if ($unread)
                                    <div class="notif-unread-dot absolute left-1.5 top-6 sm:left-2 sm:top-1/2 sm:-translate-y-1/2" aria-hidden="true"></div>
                                @endif
                                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full {{ $icon['bg'] }} ring-1 ring-black/[0.03]">
                                    <span class="material-symbols-outlined {{ $icon['fg'] }}">{{ $icon['icon'] }}</span>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="mb-0.5 flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                                        <h4 class="truncate text-sm font-semibold text-stone-900">{{ $notification->title }}</h4>
                                        <span class="whitespace-nowrap text-[12px] font-medium text-stone-400">
                                            {{ $when?->diffForHumans(short: true) ?? '' }}
                                        </span>
                                    </div>
                                    <p class="line-clamp-2 text-sm leading-5 text-stone-500 sm:line-clamp-1">{{ $notification->body }}</p>
                                </div>
                                <div class="row-actions flex shrink-0 items-center gap-2.5 self-center pl-1">
                                    @if ($notification->actionUrl())
                                        <a href="{{ $notification->actionUrl() }}" class="text-sm font-semibold text-[#0052CC] hover:underline">
                                            {{ $notification->actionLabel() }}
                                        </a>
                                    @endif
                                    @if ($unread)
                                        <form method="POST" action="{{ route('notifications.read', $notification) }}">
                                            @csrf
                                            <button type="submit" class="material-symbols-outlined rounded-lg p-1 text-stone-400 transition hover:bg-stone-100 hover:text-stone-700" title="Mark as read" aria-label="Mark as read">
                                                close
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endforeach
            @else
                <div class="px-6 py-16 text-center">
                    <div class="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-stone-100">
                        <span class="material-symbols-outlined text-stone-400">notifications</span>
                    </div>
                    <p class="text-base font-semibold text-stone-900">No alerts yet</p>
                    <p class="mx-auto mt-1 max-w-sm text-sm text-stone-500">When orders, stock, imports, or shipments need attention, they will show up here.</p>
                </div>
            @endif
        </div>

        @if ($notifications->hasPages())
            <div>{{ $notifications->links() }}</div>
        @endif
    </div>

    {{-- Right: settings (20%) --}}
    <aside class="w-full shrink-0 xl:w-[20%] xl:min-w-[220px] xl:max-w-[320px]">
        @php $prefsLocked = ! empty($settingsSaved); @endphp
        <div
            class="notif-prefs-card overflow-hidden rounded-2xl border border-stone-200/90 bg-white shadow-sm shadow-stone-900/[0.03] xl:sticky xl:top-24"
            data-locked="{{ $prefsLocked ? '1' : '0' }}"
            id="notification-prefs-card"
        >
            <div class="border-b border-stone-100 px-4 py-4 sm:px-5">
                <h2 class="font-[Poppins] text-base font-bold text-stone-900">Settings</h2>
                <p class="mt-0.5 text-xs leading-4 text-stone-500">Configure alert delivery preferences.</p>
                <button
                    type="button"
                    class="notif-prefs-edit mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[#0052CC] transition hover:underline"
                    id="notification-prefs-edit"
                    @if (! $prefsLocked) style="display:none" @endif
                >
                    <span class="material-symbols-outlined text-[16px]">edit</span>
                    Edit Preferences
                </button>
            </div>

            <form method="POST" action="{{ route('notifications.preferences.update') }}" class="space-y-6 px-4 py-5 sm:px-5" id="notification-settings-form">
                @csrf
                @method('PUT')

                <div class="notif-prefs-body space-y-6">
                    <div>
                        <h3 class="mb-3 text-[10px] font-bold uppercase tracking-[0.14em] text-stone-400">Channels</h3>
                        <div class="space-y-3.5">
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="material-symbols-outlined shrink-0 text-[18px] text-stone-400">desktop_windows</span>
                                    <span class="truncate text-sm font-medium text-stone-700">Desktop</span>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" name="in_app_enabled" value="1" @checked($inAppEnabled) @disabled($prefsLocked)>
                                    <span class="settings-switch-track" aria-hidden="true"></span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="material-symbols-outlined shrink-0 text-[18px] text-stone-400">mail</span>
                                    <span class="truncate text-sm font-medium text-stone-700">Email alerts</span>
                                </div>
                                <label class="settings-switch">
                                    <input type="checkbox" name="email_enabled" value="1" @checked($emailEnabled) @disabled($prefsLocked)>
                                    <span class="settings-switch-track" aria-hidden="true"></span>
                                </label>
                            </div>
                            <div class="flex items-center justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="material-symbols-outlined shrink-0 text-[18px] text-stone-400">smartphone</span>
                                    <span class="truncate text-sm font-medium text-stone-700">Mobile Push</span>
                                </div>
                                <label class="settings-switch" title="Coming soon">
                                    <input type="checkbox" disabled>
                                    <span class="settings-switch-track" aria-hidden="true"></span>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h3 class="mb-3 text-[10px] font-bold uppercase tracking-[0.14em] text-stone-400">Notification Types</h3>
                        <div class="space-y-1">
                            @foreach ($uiGroups as $groupKey => $group)
                                <label @class([
                                    'flex items-start gap-2.5 rounded-lg p-2 transition-colors',
                                    'cursor-pointer hover:bg-stone-50' => ! $prefsLocked,
                                    'cursor-default' => $prefsLocked,
                                ])>
                                    <input
                                        type="checkbox"
                                        name="groups[{{ $groupKey }}]"
                                        value="1"
                                        @checked(! empty($groupEnabled[$groupKey]))
                                        @disabled($prefsLocked)
                                        class="mt-0.5 h-4 w-4 shrink-0 rounded border-stone-300 accent-[#0052CC] focus:ring-[#0052CC]/20"
                                    >
                                    <div class="min-w-0 flex-1">
                                        <p class="text-sm font-semibold leading-tight text-stone-700">{{ $group['label'] }}</p>
                                        <p class="mt-0.5 text-[11px] leading-snug text-stone-400">{{ $group['description'] }}</p>
                                    </div>
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>

                <button
                    type="submit"
                    id="notification-prefs-submit"
                    @class([
                        'flex w-full items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-semibold text-white shadow-sm transition',
                        'bg-emerald-600 cursor-default' => $prefsLocked,
                        'bg-brand hover:bg-brand-hover active:scale-[0.98]' => ! $prefsLocked,
                    ])
                    @if ($prefsLocked) disabled @endif
                >
                    @if ($prefsLocked)
                        <span class="material-symbols-outlined text-[18px]">check_circle</span>
                        Settings Saved
                    @else
                        Save Changes
                    @endif
                </button>
            </form>
        </div>
    </aside>
</div>

@push('scripts')
<script>
(() => {
    const card = document.getElementById('notification-prefs-card');
    const editBtn = document.getElementById('notification-prefs-edit');
    const form = document.getElementById('notification-settings-form');
    const submitBtn = document.getElementById('notification-prefs-submit');
    if (!card || !editBtn || !form || !submitBtn) return;

    editBtn.addEventListener('click', () => {
        card.setAttribute('data-locked', '0');
        editBtn.style.display = 'none';

        form.querySelectorAll('input[name]').forEach((input) => {
            if (input.name === '_token' || input.name === '_method') return;
            input.disabled = false;
        });

        submitBtn.disabled = false;
        submitBtn.className = 'flex w-full items-center justify-center gap-1.5 rounded-xl py-2.5 text-sm font-semibold text-white shadow-sm transition bg-brand hover:bg-brand-hover active:scale-[0.98]';
        submitBtn.textContent = 'Save Changes';
    });
})();
</script>
@endpush
@endsection
