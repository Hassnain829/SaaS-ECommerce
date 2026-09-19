@php
    $catalog = $teamAccessCatalog ?? \App\Support\StoreMemberAccess::catalog();
@endphp

<div id="teamPresetsOverlay" class="ui-modal-overlay team-drawer-overlay hidden" data-close-team-presets></div>

<aside id="teamPresetsDrawer" class="ui-drawer-panel team-drawer translate-x-full" role="dialog" aria-modal="true" aria-labelledby="team-presets-title" aria-hidden="true">
    <div class="team-drawer-head">
        <div>
            <p class="team-drawer-eyebrow">Access templates</p>
            <h2 id="team-presets-title">Permission presets</h2>
            <p>Presets are safe starting points. Applying a preset replaces the selected member’s unsaved permission changes.</p>
        </div>
        <button type="button" class="team-ws-icon-btn" data-close-team-presets aria-label="Close presets drawer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
    </div>

    <div class="team-drawer-body">
        @foreach ($catalog['presets'] as $preset)
            @php
                $chips = match ($preset['key']) {
                    'full_operational' => ['Catalog', 'Orders', 'Fulfillment', 'Store settings'],
                    'operations' => ['Orders', 'Stock', 'Customers', 'No labels'],
                    'view' => ['View products', 'View orders', 'View customers'],
                    default => ['Module controls', 'Store scoped', 'Audited changes'],
                };
            @endphp
            <article class="team-ws-preset-card">
                <div class="team-ws-preset-card-head">
                    <div>
                        <h3>{{ $preset['label'] }}</h3>
                        <p class="team-ws-hint mt-1">{{ $preset['short'] ?? $preset['label'] }}</p>
                    </div>
                    @if (($canManageTeam ?? false) && $preset['key'] !== 'custom')
                        <button type="button" class="team-ws-btn team-ws-btn-sm" data-apply-preset="{{ $preset['key'] }}">Apply</button>
                    @elseif ($preset['key'] === 'custom')
                        <button type="button" class="team-ws-btn team-ws-btn-sm" data-apply-preset="custom">Use custom</button>
                    @endif
                </div>
                <p>{{ $preset['description'] }}</p>
                <div class="team-ws-mini-perms">
                    @foreach ($chips as $chip)
                        <span>{{ $chip }}</span>
                    @endforeach
                </div>
            </article>
        @endforeach

        <div class="team-ws-form-note mt-4">
            <strong>Protected capabilities</strong><br>
            Ownership, closing the store, the platform subscription, and billing authority stay with the Owner. Sensitive permissions stay off until you enable them.
        </div>
    </div>

    <div class="team-drawer-footer">
        <span class="team-ws-hint">Presets can be changed at any time.</span>
        <button type="button" class="team-ws-btn team-ws-btn-primary" data-close-team-presets>Done</button>
    </div>
</aside>
