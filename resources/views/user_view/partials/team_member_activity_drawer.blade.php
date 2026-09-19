@php
    $recentTeamActivity = $recentTeamActivity ?? collect();
    $formatActivity = $formatActivity ?? static function ($log): array {
        return [
            'title' => 'Team membership updated',
            'copy' => 'Store-scoped team change',
            'when' => optional($log->created_at)?->diffForHumans() ?? 'Recently',
        ];
    };
@endphp

<div id="teamActivityOverlay" class="ui-modal-overlay team-drawer-overlay hidden" data-close-team-activity></div>

<aside id="teamActivityDrawer" class="ui-drawer-panel team-drawer team-drawer--wide translate-x-full" role="dialog" aria-modal="true" aria-labelledby="team-activity-title" aria-hidden="true">
    <div class="team-drawer-head">
        <div>
            <p class="team-drawer-eyebrow">Audit log</p>
            <h2 id="team-activity-title">Team activity</h2>
            <p>Review invitations, access changes, and removals for this store.</p>
        </div>
        <button type="button" class="team-ws-icon-btn" data-close-team-activity aria-label="Close activity drawer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
    </div>

    <div class="team-drawer-body">
        @if ($recentTeamActivity->isEmpty())
            <div class="team-ws-empty" style="min-height: 26rem">
                <div>
                    <div class="team-ws-empty-icon" aria-hidden="true">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M3 12a9 9 0 1 0 3-6.7L3 8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><path d="M3 3v5h5M12 7v6l4 2" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </div>
                    <h3>No activity yet</h3>
                    <p>Invites and access updates will appear here.</p>
                </div>
            </div>
        @else
            @foreach ($recentTeamActivity as $log)
                @php $activity = $formatActivity($log); @endphp
                <div class="team-ws-activity-item">
                    <div class="team-ws-activity-icon" aria-hidden="true">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none"><path d="M12 3 5 6v5c0 4.8 2.9 8.1 7 10 4.1-1.9 7-5.2 7-10V6l-7-3Z" stroke="currentColor" stroke-width="1.8"/><path d="m9 12 2 2 4-5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
                    </div>
                    <div>
                        <div class="team-ws-activity-title">{{ $activity['title'] }}</div>
                        <div class="team-ws-activity-copy">{{ $activity['copy'] }}</div>
                    </div>
                    <div class="team-ws-activity-time">{{ $activity['when'] }}</div>
                </div>
            @endforeach
        @endif
    </div>

    <div class="team-drawer-footer">
        <span class="team-ws-hint">Production activity is stored in Security logs and is not cleared from here.</span>
        @if (Route::has('security'))
            <a href="{{ route('security') }}" class="team-ws-btn">Open security</a>
        @else
            <button type="button" class="team-ws-btn" data-close-team-activity>Close</button>
        @endif
    </div>
</aside>
