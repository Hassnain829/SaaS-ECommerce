@php
    $catalog = $teamAccessCatalog ?? \App\Support\StoreMemberAccess::catalog();
    $inviteStores = $inviteStores ?? collect();
@endphp

<div id="teamInviteOverlay" class="ui-modal-overlay team-drawer-overlay hidden" data-close-team-invite></div>

<aside id="teamInviteDrawer" class="ui-drawer-panel team-drawer translate-x-full" role="dialog" aria-modal="true" aria-labelledby="team-invite-title" aria-hidden="true">
    <div class="team-drawer-head">
        <div>
            <p class="team-drawer-eyebrow">New teammate</p>
            <h2 id="team-invite-title">Add team member</h2>
            <p>Choose a starting preset now. They will get an email to accept access and set a password.</p>
        </div>
        <button type="button" class="team-ws-icon-btn" data-close-team-invite aria-label="Close invite drawer">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none"><path d="m6 6 12 12M18 6 6 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        </button>
    </div>

    <form method="POST" action="{{ route('team-members.store') }}" class="team-drawer-form" data-team-invite-form>
        @csrf
        <input type="hidden" name="_team_invite_modal" value="1">

        <div class="team-drawer-body">
            <div class="grid gap-5" data-team-perm-root>
                <input type="hidden" name="access_preset" value="{{ old('access_preset', 'view') }}" data-team-preset-value>
                <div class="sr-only" aria-hidden="true">
                    @foreach (($catalog['groups'] ?? []) as $group)
                        @foreach ($group['permissions'] as $permission)
                            <input type="checkbox" name="permissions[]" value="{{ $permission['key'] }}" data-team-permission="{{ $permission['key'] }}" @checked(in_array($permission['key'], old('permissions', \App\Support\StoreMemberAccess::presets()['view']), true))>
                        @endforeach
                    @endforeach
                </div>

                @if ($errors->has('name') || $errors->has('email') || $errors->has('access_preset') || $errors->has('permissions') || $errors->has('job_title') || $errors->has('store_ids'))
                    <div class="team-ws-note is-warn">
                        @foreach (['name', 'email', 'access_preset', 'permissions', 'job_title', 'store_ids'] as $field)
                            @error($field)
                                <p>{{ $message }}</p>
                            @enderror
                        @endforeach
                    </div>
                @endif

                <div class="team-ws-field">
                    <label class="team-ws-label" for="invite-member-name">Full name</label>
                    <input id="invite-member-name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" placeholder="Alicia Carter">
                </div>
                <div class="team-ws-field">
                    <label class="team-ws-label" for="invite-member-email">Work email</label>
                    <input id="invite-member-email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" placeholder="alicia@company.com">
                </div>
                <div class="team-ws-field">
                    <label class="team-ws-label" for="invite-job-title">Job title <span class="font-medium text-stone-400">(optional)</span></label>
                    <input id="invite-job-title" name="job_title" type="text" list="invite-job-title-options" value="{{ old('job_title') }}" placeholder="Warehouse Operator">
                    <datalist id="invite-job-title-options">
                        @foreach ($catalog['job_titles'] as $title)
                            <option value="{{ $title }}"></option>
                        @endforeach
                    </datalist>
                    <p class="team-ws-hint">The title is only a label. Authority comes from the starting access below.</p>
                </div>

                @if ($inviteStores->count() > 1)
                    <fieldset class="team-ws-field">
                        <legend class="team-ws-label">Assigned stores</legend>
                        <div class="team-ws-choice-list">
                            @foreach ($inviteStores as $storeOption)
                                <label class="team-ws-choice-card">
                                    <input type="checkbox" name="store_ids[]" value="{{ $storeOption->id }}" @checked(in_array((string) $storeOption->id, array_map('strval', old('store_ids', [$selectedStore->id])), true))>
                                    <span class="team-ws-choice-copy">
                                        <strong>{{ $storeOption->name }}</strong>
                                        @if ((int) $storeOption->id === (int) $selectedStore->id)
                                            <span class="team-ws-choice-meta">Current store</span>
                                        @endif
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @else
                    <input type="hidden" name="store_ids[]" value="{{ $selectedStore->id }}">
                    <div class="team-ws-store-chip">
                        <p>Assigned store</p>
                        <p>{{ $selectedStore->name }}</p>
                    </div>
                @endif

                <div class="team-ws-field">
                    <p class="team-ws-label">Starting access</p>
                    <div class="team-ws-radio-cards" role="radiogroup" aria-label="Starting access">
                        @foreach ($catalog['presets'] as $preset)
                            <label class="team-ws-radio-card">
                                <input
                                    type="radio"
                                    name="invite_access_preset_choice"
                                    value="{{ $preset['key'] }}"
                                    data-team-preset="{{ $preset['key'] }}"
                                    @checked(old('access_preset', 'view') === $preset['key'])
                                >
                                <span class="team-ws-radio-copy">
                                    <strong>{{ $preset['label'] }}</strong>
                                    <p>{{ $preset['description'] }}</p>
                                </span>
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="team-ws-form-note">
                    <strong>Invitation email</strong><br>
                    They receive a link at this address to accept access and choose a password. They cannot use the store until they accept.
                </div>
            </div>
        </div>

        <div class="team-drawer-footer">
            <span class="team-ws-hint">You can customize permissions from the member panel after adding them.</span>
            <div class="team-drawer-actions">
                <button type="button" class="team-ws-btn" data-close-team-invite>Cancel</button>
                <button type="submit" class="team-ws-btn team-ws-btn-primary">Send invitation</button>
            </div>
        </div>
    </form>
</aside>
