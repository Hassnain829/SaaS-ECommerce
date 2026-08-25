<div id="permanentDeleteStoreModal" class="ui-modal-shell ui-modal-shell--alert hidden">
    <div class="ui-modal-panel ui-modal-panel--md">
        <div class="px-5 py-5 sm:px-6">
            <p class="text-xs font-semibold uppercase tracking-[0.08em] text-[#991B1B]">Permanent deletion</p>
            <h2 id="permanentDeleteStoreTitle" class="mt-2 text-xl font-bold text-[#7F1D1D]">Permanently delete store?</h2>
            <p class="mt-3 text-sm leading-relaxed text-[#7F1D1D]">
                This permanently removes this closed store and its retained store data. This action cannot be undone.
            </p>
            <p class="mt-2 text-sm leading-relaxed text-[#7F1D1D]">
                Products, customers, orders, inventory, shipping configuration and other store records will be permanently removed.
            </p>

            <form id="permanentDeleteStoreForm" method="POST" class="mt-6 space-y-4">
                @csrf
                @method('DELETE')
                <div>
                    <label for="confirm_store_name" class="mb-2 block text-sm font-medium text-[#334155]">
                        Type <span id="permanentDeleteStoreNameLabel" class="font-bold text-[#0F172A]"></span> to confirm
                    </label>
                    <input
                        id="confirm_store_name"
                        name="confirm_store_name"
                        type="text"
                        autocomplete="off"
                        class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 text-sm text-[#0F172A] focus:border-[#DC2626] focus:outline-none focus:ring-2 focus:ring-[#DC2626]/20"
                    >
                </div>
                <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                    <button
                        type="button"
                        id="cancelPermanentDeleteStore"
                        class="rounded-xl border border-[#E2E8F0] px-5 py-3 text-sm font-semibold text-[#334155] transition hover:bg-[#F8FAFC]"
                    >
                        Cancel
                    </button>
                    <button
                        type="submit"
                        id="submitPermanentDeleteStore"
                        disabled
                        class="rounded-xl bg-[#DC2626] px-5 py-3 text-sm font-bold text-white shadow-lg shadow-[#DC2626]/20 transition enabled:hover:bg-[#B91C1C] disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        Delete Permanently
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
(() => {
    const modal = document.getElementById('permanentDeleteStoreModal');
    const form = document.getElementById('permanentDeleteStoreForm');
    const title = document.getElementById('permanentDeleteStoreTitle');
    const nameLabel = document.getElementById('permanentDeleteStoreNameLabel');
    const confirmInput = document.getElementById('confirm_store_name');
    const submitButton = document.getElementById('submitPermanentDeleteStore');
    const cancelButton = document.getElementById('cancelPermanentDeleteStore');

    let expectedName = '';

    const closeModal = () => {
        modal?.classList.add('hidden');
        modal?.classList.remove('flex');
        expectedName = '';
        if (confirmInput) confirmInput.value = '';
        if (submitButton) submitButton.disabled = true;
    };

    const syncSubmitState = () => {
        if (!submitButton || !confirmInput) return;
        submitButton.disabled = confirmInput.value.trim() !== expectedName;
    };

    document.querySelectorAll('.js-open-permanent-delete-modal').forEach((button) => {
        button.addEventListener('click', () => {
            let store = {};
            try {
                store = JSON.parse(button.getAttribute('data-store') || '{}');
            } catch (e) {
                return;
            }

            expectedName = String(store.name || '').trim();
            if (!expectedName || !form || !modal) return;

            form.action = store.delete_url || '';
            if (title) title.textContent = `Permanently delete ${expectedName}?`;
            if (nameLabel) nameLabel.textContent = expectedName;
            if (confirmInput) confirmInput.value = '';
            syncSubmitState();

            modal.classList.remove('hidden');
            modal.classList.add('flex');
            confirmInput?.focus();
        });
    });

    confirmInput?.addEventListener('input', syncSubmitState);
    cancelButton?.addEventListener('click', closeModal);
    modal?.addEventListener('click', (event) => {
        if (event.target === modal) closeModal();
    });
})();
</script>
@endpush
