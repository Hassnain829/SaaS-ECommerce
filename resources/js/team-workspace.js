/**
 * Team members workspace. Document-level listeners stay bound once so Turbo
 * visits keep Invite / inspector / drawers working. Permission copy comes
 * from Laravel.
 */
(function () {
    const parseMember = (value) => {
        if (! value) {
            return {};
        }
        if (typeof value === 'object') {
            return value;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            return {};
        }
    };

    const lockBody = (locked) => {
        document.body.classList.toggle('overflow-hidden', locked);
    };

    const teamPage = () => document.querySelector('[data-team-page]');
    const inviteOverlay = () => document.getElementById('teamInviteOverlay');
    const inviteDrawer = () => document.getElementById('teamInviteDrawer');
    const presetsOverlay = () => document.getElementById('teamPresetsOverlay');
    const presetsDrawer = () => document.getElementById('teamPresetsDrawer');
    const activityOverlay = () => document.getElementById('teamActivityOverlay');
    const activityDrawer = () => document.getElementById('teamActivityDrawer');
    const inspectorForm = () => document.querySelector('[data-team-access-form]');
    const canManage = () => teamPage()?.getAttribute('data-team-can-manage') === '1';

    const grantableSet = () => {
        const keys = catalog().grantable;
        if (! Array.isArray(keys)) {
            return null;
        }
        return new Set(keys);
    };

    const canGrant = (key) => {
        const set = grantableSet();
        return set === null || set.has(key);
    };

    const canGrantAll = (keys) => (keys || []).every((key) => canGrant(key));

    const accessLocked = (member = selectedMember) => Boolean(member?.is_owner || member?.is_you) || ! canManage();

    const editableKeysFrom = (keys) => {
        const set = grantableSet();
        if (! set) {
            return [...keys];
        }
        const next = new Set(savedKeys.filter((key) => ! set.has(key)));
        keys.forEach((key) => {
            if (set.has(key)) {
                next.add(key);
            }
        });
        return [...next];
    };

    const catalog = () => {
        const page = teamPage();
        if (! page) {
            return { presets: [], modules: [], dependencies: {}, children: {}, sensitive: [] };
        }
        if (page.__teamCatalog) {
            return page.__teamCatalog;
        }
        try {
            page.__teamCatalog = JSON.parse(page.getAttribute('data-team-access-catalog') || '{}');
        } catch (error) {
            page.__teamCatalog = { presets: [], modules: [], dependencies: {}, children: {}, sensitive: [] };
        }
        return page.__teamCatalog;
    };

    const showLayer = (overlay, panel) => {
        if (typeof window.showMerchantLayer === 'function') {
            window.showMerchantLayer(overlay);
            window.showMerchantLayer(panel);
        } else {
            overlay?.classList.remove('hidden');
            panel?.classList.remove('hidden', 'translate-x-full');
        }
        overlay?.setAttribute('aria-hidden', 'false');
        panel?.setAttribute('aria-hidden', 'false');
    };

    const hideLayer = (overlay, panel) => {
        if (typeof window.closeMerchantLayer === 'function') {
            window.closeMerchantLayer(overlay);
            window.closeMerchantLayer(panel);
        } else {
            overlay?.classList.add('hidden');
            panel?.classList.add('translate-x-full');
            panel?.classList.remove('hidden');
        }
        overlay?.setAttribute('aria-hidden', 'true');
        panel?.setAttribute('aria-hidden', 'true');
    };

    const rootFor = (el) => el?.closest('[data-team-perm-root]') || el;
    const inspectorRoot = () => inspectorForm();

    const presetKeys = (key) => {
        const match = (catalog().presets || []).find((preset) => preset.key === key);
        return Array.isArray(match?.permissions) ? match.permissions : [];
    };

    const applyChecks = (root, keys, { silent = false } = {}) => {
        if (! root) {
            return;
        }
        const selected = new Set(keys);
        root.querySelectorAll('[data-team-permission]').forEach((input) => {
            input.checked = selected.has(input.value);
        });
        if (! silent) {
            root.dataset.permSilent = '1';
            syncDependencies(root);
            delete root.dataset.permSilent;
        }
        if (root === inspectorRoot()) {
            syncInspectorVisuals();
        }
    };

    const checkedKeys = (root) => Array.from(root.querySelectorAll('[data-team-permission]:checked')).map((input) => input.value);

    const setPresetValue = (root, key) => {
        const hidden = root.querySelector('[data-team-preset-value]');
        if (hidden) {
            hidden.value = key;
        }
        root.querySelectorAll('[data-team-preset]').forEach((input) => {
            input.checked = input.value === key;
        });
        if (root === inspectorRoot()) {
            root.querySelectorAll('[data-inspector-preset]').forEach((button) => {
                button.classList.toggle('is-active', button.getAttribute('data-inspector-preset') === key);
            });
        }
    };

    const matchPreset = (keys) => {
        const sorted = [...keys].sort().join('|');
        for (const preset of catalog().presets || []) {
            if (preset.key === 'custom') {
                continue;
            }
            const compare = [...(preset.permissions || [])].sort().join('|');
            if (compare === sorted) {
                return preset.key;
            }
        }
        return 'custom';
    };

    const syncDependencies = (root) => {
        if (! root || root.dataset.permSilent === '1') {
            return;
        }
        const dependencies = catalog().dependencies || {};
        const children = catalog().children || {};
        const selected = new Set(checkedKeys(root));
        let changed = true;
        while (changed) {
            changed = false;
            selected.forEach((key) => {
                (dependencies[key] || []).forEach((parent) => {
                    if (! selected.has(parent)) {
                        selected.add(parent);
                        changed = true;
                    }
                });
            });
        }
        ['products.view', 'orders.view', 'customers.view', 'website.view', 'team.view', 'billing.view', 'settings.delivery'].forEach((parent) => {
            if (selected.has(parent)) {
                return;
            }
            const queue = [parent];
            while (queue.length) {
                const current = queue.shift();
                (children[current] || []).forEach((child) => {
                    if (selected.has(child)) {
                        selected.delete(child);
                        queue.push(child);
                    }
                });
            }
        });
        root.dataset.permSilent = '1';
        applyChecks(root, [...selected], { silent: true });
        setPresetValue(root, matchPreset([...selected]));
        delete root.dataset.permSilent;
    };

    const toastIcons = {
        success: '<svg width="16" height="16" viewBox="0 0 22 22" fill="none" aria-hidden="true"><path d="M7 11.25 9.75 14 15.25 8.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        warn: '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="M12 8v5.25" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/><circle cx="12" cy="16.35" r="1.15" fill="currentColor"/></svg>',
    };

    const toast = (title, message, type = 'success') => {
        const stack = document.querySelector('[data-team-toasts]');
        if (! stack) {
            return;
        }
        if (stack.parentElement !== document.body) {
            document.body.appendChild(stack);
        }
        const tone = type === 'warn' ? 'warn' : 'success';
        const el = document.createElement('div');
        el.className = `team-ws-toast is-${tone}`;
        el.setAttribute('role', 'status');
        el.innerHTML = `
            <span class="team-ws-toast-icon" aria-hidden="true">${toastIcons[tone]}</span>
            <div class="team-ws-toast-copy">
                <p class="team-ws-toast-title"></p>
                <p class="team-ws-toast-message"></p>
            </div>
            <button type="button" class="team-ws-toast-close" aria-label="Dismiss">
                <svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true"><path d="M10.5 3.5 3.5 10.5M3.5 3.5l7 7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
            </button>
        `;
        el.querySelector('.team-ws-toast-title').textContent = title;
        el.querySelector('.team-ws-toast-message').textContent = message;
        el.querySelector('.team-ws-toast-close').addEventListener('click', () => el.remove());
        stack.appendChild(el);
        requestAnimationFrame(() => el.classList.add('is-visible'));
        setTimeout(() => el.remove(), 4200);
    };

    let selectedMember = {};
    let savedKeys = [];
    let dirty = false;

    const markDirty = (isDirty) => {
        dirty = Boolean(isDirty);
        const discard = document.querySelector('[data-access-discard]');
        const save = document.querySelector('[data-access-save]');
        const summary = document.querySelector('[data-inspector-summary]');
        const owner = Boolean(selectedMember.is_owner);
        const locked = accessLocked();
        if (discard) {
            discard.disabled = locked || ! dirty;
        }
        if (save) {
            save.disabled = locked || ! dirty;
            save.classList.toggle('hidden', owner || Boolean(selectedMember.is_you));
        }
        if (summary) {
            if (owner) {
                summary.textContent = 'Full access · protected owner';
            } else if (selectedMember.is_you) {
                summary.textContent = 'Your access · ask the owner to change it';
            } else {
                summary.innerHTML = `${dirty ? '<span class="team-ws-dirty"></span>Unsaved changes · ' : ''}${permissionsSummary()}`;
            }
        }
    };

    const permissionsSummary = () => {
        const root = inspectorRoot();
        if (! root) {
            return 'Store-scoped access profile.';
        }
        const selected = new Set(checkedKeys(root));
        const modules = catalog().modules || [];
        let managed = 0;
        let viewed = 0;
        modules.forEach((module) => {
            const viewKeys = module.view || [];
            const manageKeys = module.manage || [];
            const viewOn = viewKeys.length > 0 && viewKeys.every((key) => selected.has(key));
            const manageOn = manageKeys.length > 0 && manageKeys.every((key) => selected.has(key));
            if (viewOn) {
                viewed += 1;
            }
            if (manageOn) {
                managed += 1;
            }
        });
        return `${managed} module${managed === 1 ? '' : 's'} can be managed · ${viewed} can be viewed`;
    };

    const syncInspectorVisuals = () => {
        const root = inspectorRoot();
        if (! root) {
            return;
        }
        const locked = accessLocked();
        const selected = new Set(checkedKeys(root));
        (catalog().modules || []).forEach((module) => {
            const viewKeys = module.view || [];
            const manageKeys = module.manage || [];
            const viewOn = viewKeys.length > 0 && viewKeys.every((key) => selected.has(key));
            const manageOn = manageKeys.length > 0 && manageKeys.every((key) => selected.has(key));
            root.querySelectorAll(`[data-module="${module.key}"]`).forEach((button) => {
                const level = button.getAttribute('data-level');
                const keys = level === 'manage' ? manageKeys : viewKeys;
                button.classList.toggle('is-on', level === 'manage' ? manageOn : viewOn);
                button.textContent = `${(level === 'manage' ? manageOn : viewOn) ? '✓ ' : ''}${level === 'manage' ? 'Manage' : 'View'}`;
                button.disabled = locked || ! canGrantAll(keys);
            });
        });
        (catalog().sensitive || []).forEach((key) => {
            const on = selected.has(key);
            root.querySelectorAll(`[data-sensitive-toggle="${key}"]`).forEach((button) => {
                const wantsOn = button.getAttribute('data-level') === 'on';
                button.classList.toggle('is-on', wantsOn ? on : ! on);
                button.disabled = locked || ! canGrant(key);
            });
        });
    };

    const toggleModule = (moduleKey, level) => {
        const root = inspectorRoot();
        const module = (catalog().modules || []).find((item) => item.key === moduleKey);
        if (! root || ! module || accessLocked()) {
            return;
        }
        const selected = new Set(checkedKeys(root));
        const viewKeys = (module.view || []).filter((key) => canGrant(key));
        const manageKeys = (module.manage || []).filter((key) => canGrant(key));
        if (level === 'manage') {
            const manageOn = manageKeys.length > 0 && manageKeys.every((key) => selected.has(key));
            if (manageOn) {
                manageKeys.forEach((key) => selected.delete(key));
            } else {
                viewKeys.forEach((key) => selected.add(key));
                manageKeys.forEach((key) => selected.add(key));
            }
        } else {
            const viewOn = viewKeys.length > 0 && viewKeys.every((key) => selected.has(key));
            if (viewOn) {
                viewKeys.forEach((key) => selected.delete(key));
                manageKeys.forEach((key) => selected.delete(key));
            } else {
                viewKeys.forEach((key) => selected.add(key));
            }
        }
        applyChecks(root, [...selected]);
        setPresetValue(root, matchPreset([...selected]));
        markDirty(true);
    };

    const toggleSensitive = (key, on) => {
        const root = inspectorRoot();
        if (! root || accessLocked() || ! canGrant(key)) {
            return;
        }
        const selected = new Set(checkedKeys(root));
        if (on) {
            selected.add(key);
        } else {
            selected.delete(key);
        }
        applyChecks(root, [...selected]);
        setPresetValue(root, matchPreset([...selected]));
        markDirty(true);
    };

    const fillInspector = (member) => {
        const form = inspectorForm();
        const page = teamPage();
        if (! form || ! page) {
            return;
        }
        selectedMember = member;
        savedKeys = Array.isArray(member.permissions) ? [...member.permissions] : [];
        const empty = document.querySelector('[data-inspector-empty]');
        const content = document.querySelector('[data-inspector-content]');
        empty?.classList.toggle('hidden', Boolean(member.id));
        content?.classList.toggle('hidden', ! member.id);
        if (! member.id) {
            markDirty(false);
            return;
        }
        page.setAttribute('data-team-selected', String(member.id));
        form.querySelectorAll('[data-access-member-name]').forEach((el) => {
            el.textContent = member.name || 'Selected member';
        });
        form.querySelectorAll('[data-access-member-email]').forEach((el) => {
            el.textContent = member.email || '';
        });
        form.querySelectorAll('[data-access-initials]').forEach((el) => {
            el.textContent = String(member.name || 'TM').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0].toUpperCase()).join('') || 'TM';
        });
        const badge = form.querySelector('[data-access-badge]');
        if (badge) {
            badge.textContent = member.membership_label || 'Team Member';
        }
        const status = form.querySelector('[data-access-status]');
        if (status) {
            const value = member.status || 'active';
            status.className = `team-ws-status is-${value}`;
            status.innerHTML = `<span class="team-ws-dot"></span>${value.charAt(0).toUpperCase()}${value.slice(1)}`;
        }
        const jobTitle = form.querySelector('[data-access-job-title]');
        if (jobTitle) {
            jobTitle.value = member.job_title || '';
        }
        const memberId = form.querySelector('[data-access-member-id]');
        if (memberId) {
            memberId.value = member.id;
        }
        if (member.id && form.dataset.actionTemplate) {
            form.action = form.dataset.actionTemplate.replace('__USER_ID__', member.id);
        }
        const ownerNote = form.querySelector('[data-access-owner-note]');
        ownerNote?.classList.toggle('hidden', ! member.is_owner);
        const selfNote = form.querySelector('[data-access-self-note]');
        selfNote?.classList.toggle('hidden', ! member.is_you || Boolean(member.is_owner));
        const locked = accessLocked(member);
        form.querySelectorAll('[data-access-edit-fields] button, [data-sensitive-toggle]').forEach((button) => {
            button.disabled = locked;
        });
        const locationIds = new Set((member.location_ids || []).map((id) => String(id)));
        form.querySelectorAll('[data-access-location]').forEach((input) => {
            input.checked = locationIds.has(String(input.value));
        });
        applyChecks(form, savedKeys);
        setPresetValue(form, member.access_preset || matchPreset(savedKeys));
        form.querySelectorAll('[data-team-permission], [data-team-preset], [data-access-location]').forEach((input) => {
            input.disabled = accessLocked(member);
        });
        document.querySelectorAll('[data-member-row]').forEach((row) => {
            const rowMember = parseMember(row.getAttribute('data-member'));
            row.classList.toggle('is-selected', String(rowMember.id) === String(member.id));
        });
        markDirty(false);
    };

    const selectMember = (member, { scroll = false } = {}) => {
        if (dirty && String(selectedMember.id || '') !== String(member.id || '')) {
            const proceed = window.confirm('Discard unsaved access changes for this teammate?');
            if (! proceed) {
                return;
            }
        }
        closeRowMenus();
        fillInspector(member);
        if (scroll) {
            document.querySelector('[data-team-inspector]')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    const applyInspectorPreset = (key) => {
        const root = inspectorRoot();
        if (! selectedMember.id) {
            toast('Select a teammate', 'Choose someone in the directory before applying a preset.', 'warn');
            return;
        }
        if (! root || accessLocked()) {
            toast('Protected access', 'This member’s access cannot be changed in the current state.', 'warn');
            return;
        }
        setPresetValue(root, key);
        if (key !== 'custom') {
            applyChecks(root, editableKeysFrom(presetKeys(key)));
        }
        markDirty(true);
    };

    const discardInspector = () => {
        applyChecks(inspectorRoot(), savedKeys);
        setPresetValue(inspectorRoot(), selectedMember.access_preset || matchPreset(savedKeys));
        markDirty(false);
        toast('Changes discarded', 'The last saved access configuration was restored.');
    };

    const openInviteDrawer = () => {
        const overlay = inviteOverlay();
        const drawer = inviteDrawer();
        if (! overlay || ! drawer) {
            return;
        }
        const root = drawer.querySelector('[data-team-perm-root]');
        if (root && ! root.dataset.initialized) {
            const viewKeys = presetKeys('view');
            const set = grantableSet();
            applyChecks(root, set ? viewKeys.filter((key) => set.has(key)) : viewKeys);
            setPresetValue(root, 'view');
            root.dataset.initialized = '1';
        }
        showLayer(overlay, drawer);
        lockBody(true);
        drawer.querySelector('input,button,select,textarea')?.focus();
    };

    const closeInviteDrawer = () => {
        hideLayer(inviteOverlay(), inviteDrawer());
        lockBody(false);
    };

    const openPresetsDrawer = () => {
        showLayer(presetsOverlay(), presetsDrawer());
        lockBody(true);
    };

    const closePresetsDrawer = () => {
        hideLayer(presetsOverlay(), presetsDrawer());
        lockBody(false);
    };

    const openActivityDrawer = () => {
        showLayer(activityOverlay(), activityDrawer());
        lockBody(true);
    };

    const closeActivityDrawer = () => {
        hideLayer(activityOverlay(), activityDrawer());
        lockBody(false);
    };

    const closeTeamOverlays = () => {
        closeInviteDrawer();
        closePresetsDrawer();
        closeActivityDrawer();
        lockBody(false);
    };

    const closeRowMenus = () => {
        document.querySelectorAll('[data-row-menu]').forEach((menu) => menu.classList.add('hidden'));
        document.querySelectorAll('[data-member-row]').forEach((row) => row.classList.remove('is-menu-open'));
        document.querySelectorAll('[data-team-row-menu]').forEach((button) => button.setAttribute('aria-expanded', 'false'));
    };

    const filterState = {
        query: '',
        status: 'all',
        access: 'all',
        includeSuspended: false,
    };

    const memberMatches = (row) => {
        const member = parseMember(row.getAttribute('data-member'));
        const text = `${member.name || ''} ${member.email || ''} ${member.membership_label || ''} ${member.job_title || ''} ${member.scope || ''}`.toLowerCase();
        const status = row.getAttribute('data-status') || 'active';
        const access = row.getAttribute('data-access-filter') || '';
        const statusMatch = filterState.status === 'all' || status === filterState.status;
        const queryMatch = ! filterState.query || text.includes(filterState.query);
        const accessMatch = filterState.access === 'all' || access === filterState.access;
        const suspendedMatch = filterState.includeSuspended || filterState.status === 'suspended' || status !== 'suspended';
        return statusMatch && queryMatch && accessMatch && suspendedMatch;
    };

    const filterRows = () => {
        const rows = document.querySelectorAll('[data-member-row]');
        let visible = 0;
        rows.forEach((row) => {
            const show = memberMatches(row);
            row.classList.toggle('hidden', ! show);
            if (show) {
                visible += 1;
            }
        });
        const empty = document.querySelector('[data-team-directory-empty]');
        const directory = document.querySelector('[data-team-directory]');
        empty?.classList.toggle('hidden', visible > 0 || rows.length === 0);
        directory?.classList.toggle('hidden', visible === 0 && rows.length > 0);
    };

    const handleClick = (event) => {
        const target = event.target;
        if (! (target instanceof Element) || ! teamPage()) {
            return;
        }

        const preset = target.closest('[data-team-preset]');
        if (preset instanceof HTMLInputElement) {
            const root = rootFor(preset);
            setPresetValue(root, preset.value);
            const keys = preset.value !== 'custom' ? presetKeys(preset.value) : presetKeys('view');
            const set = grantableSet();
            applyChecks(root, set ? keys.filter((key) => set.has(key)) : keys);
            if (preset.value === 'custom') {
                setPresetValue(root, 'custom');
            }
            return;
        }

        const inspectorPreset = target.closest('[data-inspector-preset]');
        if (inspectorPreset) {
            event.preventDefault();
            applyInspectorPreset(inspectorPreset.getAttribute('data-inspector-preset'));
            return;
        }

        const moduleButton = target.closest('[data-module]');
        if (moduleButton) {
            event.preventDefault();
            toggleModule(moduleButton.getAttribute('data-module'), moduleButton.getAttribute('data-level'));
            return;
        }

        const sensitive = target.closest('[data-sensitive-toggle]');
        if (sensitive) {
            event.preventDefault();
            toggleSensitive(sensitive.getAttribute('data-sensitive-toggle'), sensitive.getAttribute('data-level') === 'on');
            return;
        }

        const applyPreset = target.closest('[data-apply-preset]');
        if (applyPreset) {
            event.preventDefault();
            applyInspectorPreset(applyPreset.getAttribute('data-apply-preset'));
            closePresetsDrawer();
            toast('Preset loaded', 'Review the permissions, then choose Save access.');
            return;
        }

        if (target.closest('[data-open-team-invite]')) {
            event.preventDefault();
            closeTeamOverlays();
            openInviteDrawer();
            return;
        }
        if (target.closest('[data-close-team-invite]') || target.closest('#teamInviteOverlay')) {
            event.preventDefault();
            closeInviteDrawer();
            return;
        }
        if (target.closest('[data-open-team-presets]')) {
            event.preventDefault();
            closeTeamOverlays();
            openPresetsDrawer();
            return;
        }
        if (target.closest('[data-close-team-presets]') || target.closest('#teamPresetsOverlay')) {
            event.preventDefault();
            closePresetsDrawer();
            return;
        }
        if (target.closest('[data-open-team-activity]')) {
            event.preventDefault();
            closeTeamOverlays();
            openActivityDrawer();
            return;
        }
        if (target.closest('[data-close-team-activity]') || target.closest('#teamActivityOverlay')) {
            event.preventDefault();
            closeActivityDrawer();
            return;
        }

        const copyEmail = target.closest('[data-copy-email]');
        if (copyEmail) {
            event.preventDefault();
            const email = copyEmail.getAttribute('data-copy-email') || '';
            navigator.clipboard?.writeText(email).then(() => toast('Copied', `${email} copied to the clipboard.`)).catch(() => toast('Copied', email));
            closeRowMenus();
            return;
        }

        const menuButton = target.closest('[data-team-row-menu]');
        if (menuButton) {
            event.preventDefault();
            event.stopPropagation();
            const row = menuButton.closest('[data-member-row]');
            const menu = row?.querySelector('[data-row-menu]');
            const open = menu && menu.classList.contains('hidden');
            closeRowMenus();
            if (open && menu && row) {
                menu.classList.remove('hidden');
                row.classList.add('is-menu-open');
                menuButton.setAttribute('aria-expanded', 'true');
            }
            return;
        }

        if (target.closest('[data-open-team-access]')) {
            event.preventDefault();
            selectMember(parseMember(target.closest('[data-open-team-access]').getAttribute('data-member')), { scroll: true });
            return;
        }

        const statusTab = target.closest('[data-team-status]');
        if (statusTab && statusTab.closest('[data-team-status-tabs]')) {
            filterState.status = statusTab.getAttribute('data-team-status') || 'all';
            document.querySelectorAll('[data-team-status]').forEach((button) => {
                button.classList.toggle('is-active', button === statusTab);
            });
            filterRows();
            return;
        }

        if (target.closest('[data-team-filter-toggle]')) {
            event.preventDefault();
            event.stopPropagation();
            const popover = document.querySelector('[data-team-filter-popover]');
            popover?.classList.toggle('hidden');
            return;
        }

        if (target.closest('[data-team-clear-filters]')) {
            event.preventDefault();
            filterState.access = 'all';
            filterState.includeSuspended = false;
            const select = document.querySelector('[data-team-access-filter]');
            const box = document.querySelector('[data-team-include-suspended]');
            if (select) {
                select.value = 'all';
            }
            if (box) {
                box.checked = false;
            }
            filterRows();
            return;
        }

        if (target.closest('[data-access-discard]')) {
            event.preventDefault();
            discardInspector();
            return;
        }

        const row = target.closest('[data-select-member]');
        if (row && ! target.closest('button') && ! target.closest('form')) {
            selectMember(parseMember(row.getAttribute('data-member')));
            return;
        }

        if (! target.closest('[data-team-filter-wrap]') && ! target.closest('[data-team-filter-popover]')) {
            document.querySelector('[data-team-filter-popover]')?.classList.add('hidden');
        }
        if (! target.closest('[data-row-menu]') && ! target.closest('[data-team-row-menu]')) {
            closeRowMenus();
        }
    };

    const handleChange = (event) => {
        const target = event.target;
        if (! (target instanceof HTMLInputElement) && ! (target instanceof HTMLSelectElement)) {
            return;
        }
        if (target.hasAttribute('data-team-access-filter')) {
            filterState.access = target.value;
            filterRows();
            return;
        }
        if (target.hasAttribute('data-team-include-suspended')) {
            filterState.includeSuspended = target.checked;
            filterRows();
            return;
        }
        if (! (target instanceof HTMLInputElement) || ! target.hasAttribute('data-team-permission')) {
            return;
        }
        const root = rootFor(target);
        if (! root) {
            return;
        }
        setPresetValue(root, 'custom');
        syncDependencies(root);
        if (root === inspectorRoot()) {
            markDirty(true);
        }
    };

    const handleInput = (event) => {
        const input = event.target;
        if (! (input instanceof HTMLInputElement) || ! input.hasAttribute('data-team-search')) {
            return;
        }
        filterState.query = String(input.value || '').trim().toLowerCase();
        document.querySelectorAll('[data-team-search]').forEach((el) => {
            if (el !== input) {
                el.value = input.value;
            }
        });
        filterRows();
    };

    const handleKeydown = (event) => {
        if (! teamPage()) {
            return;
        }
        const row = event.target.closest?.('[data-select-member]');
        if (row && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            selectMember(parseMember(row.getAttribute('data-member')));
            return;
        }
        if (event.key !== 'Escape') {
            return;
        }
        closeTeamOverlays();
        closeRowMenus();
        document.querySelector('[data-team-filter-popover]')?.classList.add('hidden');
    };

    const restoreFromServer = () => {
        const page = teamPage();
        if (! page || page.dataset.teamRestored === '1') {
            return;
        }
        page.dataset.teamRestored = '1';
        const selectedId = page.getAttribute('data-team-access-member') || page.getAttribute('data-team-selected');
        const row = Array.from(document.querySelectorAll('[data-member-row]')).find((el) => {
            const member = parseMember(el.getAttribute('data-member'));
            return String(member.id || '') === String(selectedId || '');
        }) || document.querySelector('[data-member-row]');
        if (row) {
            fillInspector(parseMember(row.getAttribute('data-member')));
        }
        if (page.getAttribute('data-team-open-invite') === '1') {
            openInviteDrawer();
        }
    };

    const boot = () => {
        if (! teamPage()) {
            return;
        }
        restoreFromServer();
        filterRows();
    };

    const bindOnce = () => {
        if (window.__teamWorkspaceDocBound) {
            return;
        }
        window.__teamWorkspaceDocBound = true;
        document.addEventListener('click', handleClick);
        document.addEventListener('change', handleChange);
        document.addEventListener('input', handleInput);
        document.addEventListener('keydown', handleKeydown);
        document.addEventListener('turbo:before-cache', () => {
            closeTeamOverlays();
            closeRowMenus();
            document.querySelectorAll('[data-team-toasts]').forEach((stack) => {
                stack.replaceChildren();
                if (stack.parentElement === document.body) {
                    stack.remove();
                }
            });
            const page = teamPage();
            if (page) {
                delete page.dataset.teamRestored;
                delete page.__teamCatalog;
            }
        });
    };

    bindOnce();
    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);
})();
