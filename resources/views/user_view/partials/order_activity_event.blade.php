@php
    $activityType = $activityType ?? 'order';
@endphp
<li data-activity-item data-activity-type="{{ $activityType }}">
    <span class="timeline-dot" aria-hidden="true">✓</span>
    <div>
        <strong>{{ $event->title }}</strong>
        @if ($event->description)
            <span>{{ $event->description }}</span>
        @endif
        <span>
            {{ \App\Support\OrderLifecycle::eventTypeLabel($event->event_type) }}
            · {{ $event->actor?->name ?? 'System' }}
        </span>
    </div>
    <time>{{ $event->created_at?->format('M j, g:i A') ?? 'Time not recorded' }}</time>
</li>
