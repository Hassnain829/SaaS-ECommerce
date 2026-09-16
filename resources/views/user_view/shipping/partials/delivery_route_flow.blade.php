@php
    $stages = $stages ?? [];
    $detailRegionId = $detailRegionId ?? 'route-detail-region';
    $showDetail = $showDetail ?? true;
@endphp
<div class="flow-shell">
    <div class="flow">
        @foreach ($stages as $index => $stage)
            @php
                $nextStage = $stages[$index + 1] ?? null;
                $connectorState = '';
                if (is_array($nextStage)) {
                    if (! empty($nextStage['optional']) && ($nextStage['status'] ?? '') === 'muted') {
                        $connectorState = 'is-muted';
                    } elseif (($nextStage['status'] ?? '') === 'muted' && empty($nextStage['current'])) {
                        $connectorState = 'is-muted';
                    } elseif (($stage['status'] ?? '') === 'blocked' || ($nextStage['status'] ?? '') === 'blocked') {
                        $connectorState = 'is-blocked';
                    }
                }
                $stageHref = (string) ($stage['href'] ?? '');
                $isLink = ! empty($stage['asLink']) && $stageHref !== '';
                $isStatic = ! empty($stage['asStatic']);
            @endphp
            @if ($isLink)
                <a
                    href="{{ $stageHref }}"
                    class="stage is-{{ $stage['status'] }}{{ ! empty($stage['current']) ? ' is-current' : '' }}"
                    @if (! empty($stage['current'])) aria-current="step" @endif
                >
                    <span class="stage-icon"><svg class="do-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><use href="#do-i-{{ $stage['icon'] }}" xlink:href="#do-i-{{ $stage['icon'] }}"/></svg></span>
                    <span>
                        <span class="stage-label">
                            {{ $stage['label'] }}
                            @if (! empty($stage['optional']))
                                <span class="optional-label">Optional</span>
                            @endif
                        </span>
                        <span class="stage-value">{{ $stage['value'] }}</span>
                        <span class="stage-status">{{ $stage['statusLabel'] }}</span>
                    </span>
                </a>
            @elseif ($isStatic)
                <div
                    class="stage is-static is-{{ $stage['status'] }}{{ ! empty($stage['current']) ? ' is-current' : '' }}"
                    @if (! empty($stage['current'])) aria-current="step" @endif
                >
                    <span class="stage-icon"><svg class="do-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><use href="#do-i-{{ $stage['icon'] }}" xlink:href="#do-i-{{ $stage['icon'] }}"/></svg></span>
                    <span>
                        <span class="stage-label">
                            {{ $stage['label'] }}
                            @if (! empty($stage['optional']))
                                <span class="optional-label">Optional</span>
                            @endif
                        </span>
                        <span class="stage-value">{{ $stage['value'] }}</span>
                        <span class="stage-status">{{ $stage['statusLabel'] }}</span>
                    </span>
                </div>
            @else
                <button
                    type="button"
                    class="stage is-{{ $stage['status'] }}{{ ! empty($stage['current']) ? ' is-current' : '' }}"
                    data-delivery-action="toggle-stage"
                    data-stage-id="{{ $stage['id'] }}"
                    data-stage-label="{{ $stage['label'] }}"
                    data-stage-value="{{ $stage['value'] }}"
                    data-stage-detail="{{ $stage['detail'] ?? '' }}"
                    data-stage-action-label="{{ $stage['actionLabel'] ?? '' }}"
                    data-stage-action="{{ $stage['action'] ?? '' }}"
                    data-stage-icon="{{ $stage['icon'] }}"
                    @if ($stageHref !== '') data-stage-href="{{ $stageHref }}" @endif
                    aria-expanded="false"
                    @if (! empty($stage['current'])) aria-current="step" @endif
                >
                    <span class="stage-icon"><svg class="do-icon" viewBox="0 0 24 24" fill="none" aria-hidden="true"><use href="#do-i-{{ $stage['icon'] }}" xlink:href="#do-i-{{ $stage['icon'] }}"/></svg></span>
                    <span>
                        <span class="stage-label">
                            {{ $stage['label'] }}
                            @if (! empty($stage['optional']))
                                <span class="optional-label">Optional</span>
                            @endif
                        </span>
                        <span class="stage-value">{{ $stage['value'] }}</span>
                        <span class="stage-status">{{ $stage['statusLabel'] }}</span>
                    </span>
                </button>
            @endif
            @if ($nextStage)
                <div class="connector {{ $connectorState }}" aria-hidden="true">
                    <span class="connector-line"><span class="connector-pulse"></span></span>
                </div>
            @endif
        @endforeach
    </div>
    @if ($showDetail)
        <div id="{{ $detailRegionId }}"></div>
    @endif
</div>
