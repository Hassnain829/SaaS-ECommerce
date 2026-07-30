@props([
    'title',
    'lead' => null,
])

<header {{ $attributes->class(['ui-page-header']) }}>
    <div class="min-w-0">
        <h1 class="ui-page-header-title">{{ $title }}</h1>
        @if ($lead)
            <p class="ui-page-header-lead">{{ $lead }}</p>
        @endif
    </div>
    @if ($slot->isNotEmpty())
        <div class="flex flex-wrap items-center gap-2">{{ $slot }}</div>
    @endif
</header>
