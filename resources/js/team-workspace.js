/**
 * Team members drawers/modals. Document-level listeners stay bound once
 * so Turbo visits and cache restore keep Invite / Role / Access working.
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

    const inviteOverlay = () => document.getElementById('teamInviteOverlay');
    const inviteDrawer = () => document.getElementById('teamInviteDrawer');
    const roleModal = () => document.getElementById('teamRoleModal');
    const rolePanel = () => document.querySelector('[data-team-role-panel]');
    const accessOverlay = () => document.getElementById('teamAccessOverlay');
    const accessPanel = () => document.getElementById('teamAccessPanel');
    const teamPage = () => document.querySelector('[data-team-page]');

    const openInviteDrawer = () => {
        const overlay = inviteOverlay();
        const drawer = inviteDrawer();
        if (! overlay || ! drawer) {
            return;
        }
        if (typeof window.showMerchantLayer === 'function') {
            window.showMerchantLayer(overlay);
            window.showMerchantLayer(drawer);
        } else {
            overlay.classList.remove('hidden');
            drawer.classList.remove('hidden', 'translate-x-full');
        }
        lockBody(true);
    };

    const closeInviteDrawer = () => {
        const overlay = inviteOverlay();
        const drawer = inviteDrawer();
        if (! overlay || ! drawer) {
            return;
        }
        if (typeof window.closeMerchantLayer === 'function') {
            window.closeMerchantLayer(overlay);
            window.closeMerchantLayer(drawer);
        } else {
            overlay.classList.add('hidden');
            drawer.classList.add('translate-x-full');
            drawer.classList.remove('hidden');
        }
        lockBody(false);
    };

    const openRoleModal = (member) => {
        const modal = roleModal();
        if (! modal) {
            return;
        }
        document.querySelectorAll('[data-role-member-name]').forEach((el) => {
            el.textContent = member.name || 'Selected member';
        });
        document.querySelectorAll('[data-role-member-email]').forEach((el) => {
            el.textContent = member.email || '';
        });
        const roleSelect = document.querySelector('[data-role-select]');
        if (roleSelect && member.role) {
            roleSelect.value = member.role;
        }
        const roleForm = document.querySelector('[data-team-role-form]');
        if (roleForm && member.id && roleForm.dataset.actionTemplate) {
            roleForm.action = roleForm.dataset.actionTemplate.replace('__USER_ID__', member.id);
        }
        const hiddenMemberId = document.querySelector('[data-role-member-id]');
        if (hiddenMemberId) {
            hiddenMemberId.value = member.id || '';
        }
        const hiddenMemberName = document.querySelector('[data-role-member-name-input]');
        if (hiddenMemberName) {
            hiddenMemberName.value = member.name || '';
        }
        const hiddenMemberEmail = document.querySelector('[data-role-member-email-input]');
        if (hiddenMemberEmail) {
            hiddenMemberEmail.value = member.email || '';
        }

        if (typeof window.showMerchantLayer === 'function') {
            window.showMerchantLayer(modal);
        } else {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
        rolePanel()?.classList.remove('scale-95', 'opacity-0');
        lockBody(true);
    };

    const closeRoleModal = () => {
        const modal = roleModal();
        if (! modal) {
            return;
        }
        rolePanel()?.classList.add('scale-95', 'opacity-0');
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        lockBody(false);
    };

    const renderAccessItems = (role) => {
        const itemsByRole = {
            owner: [
                'Can update store settings and destructive store actions',
                'Can create, edit, and delete products',
                'Can manage managers and staff roles',
            ],
            manager: [
                'Can create, edit, and delete products',
                'Can update normal store operations',
                'Cannot delete the store or claim owner-only actions',
            ],
            staff: [
                'Can review store context and member access',
                'Blocked from destructive management actions',
                'Blocked from protected product management flows for now',
            ],
        };

        return (itemsByRole[role] || ['Store-level access details are not available for this role yet.'])
            .map((item) => `<li class="flex items-start gap-2"><span class="mt-1 h-1.5 w-1.5 rounded-full bg-brand"></span><span>${item}</span></li>`)
            .join('');
    };

    const openAccessPanel = (member) => {
        const overlay = accessOverlay();
        const panel = accessPanel();
        if (! overlay || ! panel) {
            return;
        }
        document.querySelectorAll('[data-access-member-name]').forEach((el) => {
            el.textContent = member.name || 'Selected member';
        });
        document.querySelectorAll('[data-access-member-email]').forEach((el) => {
            el.textContent = member.email || '';
        });
        document.querySelectorAll('[data-access-member-role]').forEach((el) => {
            el.textContent = member.role || 'staff';
        });
        document.querySelectorAll('[data-access-member-global-role]').forEach((el) => {
            el.textContent = member.global_role || 'user';
        });
        document.querySelectorAll('[data-access-member-joined]').forEach((el) => {
            el.textContent = member.joined_at || 'Recently added';
        });
        document.querySelectorAll('[data-access-member-description]').forEach((el) => {
            el.textContent = member.description || 'Store-scoped access profile.';
        });
        document.querySelectorAll('[data-access-member-list]').forEach((el) => {
            el.innerHTML = renderAccessItems(member.role || 'staff');
        });

        if (typeof window.showMerchantLayer === 'function') {
            window.showMerchantLayer(overlay);
            window.showMerchantLayer(panel);
        } else {
            overlay.classList.remove('hidden');
            panel.classList.remove('hidden', 'translate-x-full');
        }
        lockBody(true);
    };

    const closeAccessPanel = () => {
        const overlay = accessOverlay();
        const panel = accessPanel();
        if (! overlay || ! panel) {
            return;
        }
        if (typeof window.closeMerchantLayer === 'function') {
            window.closeMerchantLayer(overlay);
            window.closeMerchantLayer(panel);
        } else {
            overlay.classList.add('hidden');
            panel.classList.add('translate-x-full');
            panel.classList.remove('hidden');
        }
        lockBody(false);
    };

    const closeTeamOverlays = () => {
        closeRoleModal();
        closeInviteDrawer();
        closeAccessPanel();
        lockBody(false);
    };

    const filterRows = (query) => {
        const needle = String(query || '').trim().toLowerCase();
        document.querySelectorAll('[data-member-row]').forEach((row) => {
            row.classList.toggle('hidden', needle !== '' && ! row.textContent.toLowerCase().includes(needle));
        });
    };

    const handleClick = (event) => {
        const target = event.target;
        if (! (target instanceof Element)) {
            return;
        }

        if (target.closest('[data-open-team-invite]')) {
            event.preventDefault();
            closeRoleModal();
            closeAccessPanel();
            openInviteDrawer();
            return;
        }
        if (target.closest('[data-close-team-invite]') || target.closest('#teamInviteOverlay')) {
            event.preventDefault();
            closeInviteDrawer();
            return;
        }
        if (target.closest('[data-open-team-role]')) {
            event.preventDefault();
            closeInviteDrawer();
            closeAccessPanel();
            openRoleModal(parseMember(target.closest('[data-open-team-role]').dataset.member));
            return;
        }
        if (target.closest('[data-close-team-role]') || target === roleModal()) {
            event.preventDefault();
            closeRoleModal();
            return;
        }
        if (target.closest('[data-open-team-access]')) {
            event.preventDefault();
            closeInviteDrawer();
            closeRoleModal();
            openAccessPanel(parseMember(target.closest('[data-open-team-access]').dataset.member));
            return;
        }
        if (target.closest('[data-close-team-access]') || target.closest('#teamAccessOverlay')) {
            event.preventDefault();
            closeAccessPanel();
        }
    };

    const handleInput = (event) => {
        const input = event.target;
        if (! (input instanceof HTMLInputElement) || ! input.hasAttribute('data-team-search')) {
            return;
        }
        filterRows(input.value);
    };

    const handleKeydown = (event) => {
        if (event.key !== 'Escape' || ! teamPage()) {
            return;
        }
        const modal = roleModal();
        if (modal && ! modal.classList.contains('hidden')) {
            closeRoleModal();
            return;
        }
        const drawer = inviteDrawer();
        if (drawer && ! drawer.classList.contains('translate-x-full')) {
            closeInviteDrawer();
            return;
        }
        const panel = accessPanel();
        if (panel && ! panel.classList.contains('translate-x-full')) {
            closeAccessPanel();
        }
    };

    const restoreFromServer = () => {
        const page = teamPage();
        if (! page || page.dataset.teamRestored === '1') {
            return;
        }
        page.dataset.teamRestored = '1';
        if (page.getAttribute('data-team-open-invite') === '1') {
            openInviteDrawer();
        }
        if (page.getAttribute('data-team-open-role') === '1') {
            openRoleModal(parseMember(page.getAttribute('data-team-role-member')));
        }
    };

    const boot = () => {
        if (! teamPage()) {
            return;
        }
        restoreFromServer();
    };

    const bindOnce = () => {
        if (window.__teamWorkspaceDocBound) {
            return;
        }
        window.__teamWorkspaceDocBound = true;
        document.addEventListener('click', handleClick);
        document.addEventListener('input', handleInput);
        document.addEventListener('keydown', handleKeydown);
        document.addEventListener('turbo:before-cache', () => {
            closeTeamOverlays();
            const page = teamPage();
            if (page) {
                delete page.dataset.teamRestored;
                delete page.dataset.turboBound;
            }
        });
    };

    bindOnce();
    document.addEventListener('DOMContentLoaded', boot);
    document.addEventListener('turbo:load', boot);
    document.addEventListener('turbo:render', boot);
})();
