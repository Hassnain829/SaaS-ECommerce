@props([
    'title' => null,
    'lead' => null,
    'showHelp' => true,
    'showNotifications' => true,
])

@php
    $resolvedTitle = filled($title)
        ? (string) $title
        : trim((string) $__env->yieldContent('page_title'));
    $resolvedLead = filled($lead)
        ? (string) $lead
        : trim((string) $__env->yieldContent('page_lead'));
    if ($resolvedTitle === '') {
        $documentTitle = trim((string) $__env->yieldContent('title'));
        $resolvedTitle = $documentTitle !== ''
            ? trim(preg_split('/\s*(?:—|\|)\s*/u', $documentTitle)[0] ?? $documentTitle)
            : 'Workspace';
    }
    $topbarUser = auth()->user();
    $topbarInitial = $topbarUser
        ? \Illuminate\Support\Str::of($topbarUser->name)->trim()->substr(0, 1)->upper()
        : '?';
    $hasSearch = isset($search) && $search->isNotEmpty();
    $hasActions = isset($actions) && $actions->isNotEmpty();
@endphp

<header {{ $attributes->class([
    'merchant-topbar sticky top-0 z-30 flex min-h-[3.75rem] shrink-0 items-center gap-3 border-b border-border bg-surface px-4 py-2 lg:px-6',
    'flex-wrap md:flex-nowrap' => $hasSearch,
]) }}>
    <button
        type="button"
        id="sidebarToggle"
        onclick="openSidebar()"
        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-border bg-surface text-ink-secondary transition hover:bg-surface-muted md:hidden"
        aria-label="Open menu"
    >
        <svg width="18" height="12" viewBox="0 0 20 14" fill="none" aria-hidden="true">
            <path d="M0 14V12H20V14H0ZM0 7V5H20V7H0ZM0 2V0H20V2H0Z" fill="currentColor"/>
        </svg>
    </button>

    <div class="min-w-0 flex-1">
        <h1 class="truncate text-lg font-semibold leading-tight tracking-tight text-ink md:text-[1.25rem]">{{ $resolvedTitle }}</h1>
        @if ($resolvedLead !== '')
            <p class="mt-0.5 hidden truncate text-xs leading-4 text-ink-muted sm:block">{{ $resolvedLead }}</p>
        @endif
    </div>

    @if ($hasSearch)
        <div class="relative order-3 w-full shrink-0 md:order-none md:w-44 lg:w-56 xl:w-72">
            {{ $search }}
        </div>
    @endif

    <div class="flex shrink-0 items-center gap-2">
        @if ($hasActions)
            {{ $actions }}
            <div class="hidden h-5 w-px bg-border sm:block" aria-hidden="true"></div>
        @endif

        @if ($showNotifications)
            <a
                href="{{ route('notifications') }}"
                class="relative flex h-9 w-9 items-center justify-center rounded-md text-ink-muted transition hover:bg-surface-muted hover:text-ink"
                aria-label="Notifications"
            >
                <svg width="15" height="18" viewBox="0 0 16 20" fill="none" aria-hidden="true">
                    <path d="M0 17V15H2V8C2 6.61667 2.41667 5.3875 3.25 4.3125C4.08333 3.2375 5.16667 2.53333 6.5 2.2V1.5C6.5 1.08333 6.64583 0.729167 6.9375 0.4375C7.22917 0.145833 7.58333 0 8 0C8.41667 0 8.77083 0.145833 9.0625 0.4375C9.35417 0.729167 9.5 1.08333 9.5 1.5V2.2C10.8333 2.53333 11.9167 3.2375 12.75 4.3125C13.5833 5.3875 14 6.61667 14 8V15H16V17H0ZM8 20C7.45 20 6.97917 19.8042 6.5875 19.4125C6.19583 19.0208 6 18.55 6 18H10C10 18.55 9.80417 19.0208 9.4125 19.4125C9.02083 19.8042 8.55 20 8 20Z" fill="currentColor"/>
                </svg>
                @if (! empty($merchantUnreadNotificationCount) && (int) $merchantUnreadNotificationCount > 0)
                    <span class="absolute right-1 top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-danger px-1 text-[10px] font-bold leading-none text-white" aria-hidden="true">
                        {{ (int) $merchantUnreadNotificationCount > 99 ? '99+' : (int) $merchantUnreadNotificationCount }}
                    </span>
                @endif
            </a>
        @endif

        @if ($showHelp)
            <a
                href="{{ route('generalSettings') }}"
                class="hidden h-9 w-9 items-center justify-center rounded-md text-sm font-semibold text-ink-muted transition hover:bg-surface-muted hover:text-ink sm:inline-flex"
                aria-label="Help"
                title="Help"
            >
                ?
            </a>
        @endif

        <div class="relative shrink-0" data-merchant-profile-menu>
            <button
                type="button"
                class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-border bg-surface-muted text-xs font-bold text-ink transition hover:border-border-strong"
                aria-haspopup="true"
                aria-expanded="false"
                aria-label="Open profile menu"
                data-merchant-profile-toggle
            >
                @if ($topbarUser?->avatar)
                    <img src="{{ asset('storage/'.$topbarUser->avatar) }}" alt="" class="h-full w-full object-cover">
                @else
                    <span aria-hidden="true">{{ $topbarInitial }}</span>
                @endif
            </button>
            <div
                class="absolute right-0 z-50 mt-1.5 hidden w-44 overflow-hidden rounded-md border border-border bg-surface py-1 shadow-lg"
                data-merchant-profile-dropdown
                role="menu"
            >
                <a href="{{ route('profileSettings') }}" class="block px-3.5 py-2 text-sm text-ink hover:bg-surface-muted" role="menuitem">Profile settings</a>
                <a href="{{ route('logout') }}" class="block px-3.5 py-2 text-sm text-danger hover:bg-danger-soft" role="menuitem">Sign out</a>
            </div>
        </div>
    </div>
</header>
