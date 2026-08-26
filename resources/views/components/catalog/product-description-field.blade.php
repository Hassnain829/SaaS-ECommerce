@props([
    'id' => 'product-description',
    'name' => 'description',
    'value' => '',
    'rows' => 10,
    'label' => 'Description',
    'help' => 'Plain text or HTML is supported. Imported WooCommerce HTML is cleaned and shown as formatted copy on the product workspace.',
    'placeholder' => 'Describe this product for shoppers…',
])

@php
    $fieldValue = old($name, $value);
    $fieldValue = is_string($fieldValue) ? $fieldValue : '';
@endphp

<div class="product-rich-text-editor space-y-2" data-product-rich-text-editor data-preview-url="{{ route('products.description.preview') }}">
    <div class="flex flex-wrap items-end justify-between gap-2">
        <label for="{{ $id }}" class="block text-sm font-medium text-[#334155]">{{ $label }}</label>
        <div class="inline-flex rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] p-0.5 text-xs font-semibold" role="group" aria-label="Description editor mode">
            <button
                type="button"
                data-rich-text-mode="write"
                class="rounded-md bg-white px-2.5 py-1 text-[#0F172A] shadow-sm transition"
            >Write</button>
            <button
                type="button"
                data-rich-text-mode="preview"
                class="rounded-md px-2.5 py-1 text-[#64748B] transition"
            >Preview</button>
        </div>
    </div>

    <textarea
        id="{{ $id }}"
        name="{{ $name }}"
        rows="{{ (int) $rows }}"
        data-rich-text-input
        class="w-full rounded-lg border border-[#CBD5E1] px-4 py-3 font-mono text-sm leading-relaxed text-[#0F172A]"
        placeholder="{{ $placeholder }}"
    >{{ $fieldValue }}</textarea>

    <div
        data-rich-text-preview
        hidden
        class="min-h-[12rem] rounded-lg border border-[#CBD5E1] bg-white px-4 py-3"
    >
        <div
            data-rich-text-preview-body
            class="product-rich-text max-w-none text-sm leading-relaxed text-[#475569] [&_a]:font-semibold [&_a]:text-[#0052CC] [&_a]:underline [&_p]:mb-3 [&_p:last-child]:mb-0 [&_ul]:mb-3 [&_ul]:list-disc [&_ul]:pl-5 [&_ol]:mb-3 [&_ol]:list-decimal [&_ol]:pl-5 [&_li]:mb-1 [&_strong]:font-semibold [&_b]:font-semibold"
        ></div>
    </div>

    @if ($help)
        <p class="text-xs text-[#64748B]">{{ $help }}</p>
    @endif
</div>

@once
    @push('scripts')
        <script>
            (() => {
                const activeWriteClasses = ['bg-white', 'text-[#0F172A]', 'shadow-sm'];
                const idleWriteClasses = ['text-[#64748B]'];

                const setModeButtons = (root, mode) => {
                    root.querySelectorAll('[data-rich-text-mode]').forEach((btn) => {
                        const isActive = btn.getAttribute('data-rich-text-mode') === mode;
                        activeWriteClasses.forEach((c) => btn.classList.toggle(c, isActive));
                        idleWriteClasses.forEach((c) => btn.classList.toggle(c, !isActive));
                    });
                };

                const showWrite = (root) => {
                    const input = root.querySelector('[data-rich-text-input]');
                    const preview = root.querySelector('[data-rich-text-preview]');
                    if (input) input.hidden = false;
                    if (preview) preview.hidden = true;
                    setModeButtons(root, 'write');
                };

                const showPreview = async (root) => {
                    const input = root.querySelector('[data-rich-text-input]');
                    const preview = root.querySelector('[data-rich-text-preview]');
                    const body = root.querySelector('[data-rich-text-preview-body]');
                    const url = root.getAttribute('data-preview-url');
                    if (!input || !preview || !body || !url) {
                        return;
                    }

                    input.hidden = true;
                    preview.hidden = false;
                    setModeButtons(root, 'preview');
                    body.innerHTML = '<p class="text-[#94A3B8]">Loading preview…</p>';

                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            credentials: 'same-origin',
                            body: JSON.stringify({ content: input.value || '' }),
                        });
                        const data = await res.json();
                        body.innerHTML = data.html
                            ? data.html
                            : '<p class="text-[#94A3B8]">Nothing to preview yet.</p>';
                    } catch (e) {
                        body.innerHTML = '<p class="text-[#B91C1C]">Preview could not be loaded.</p>';
                    }
                };

                document.addEventListener('click', (event) => {
                    const btn = event.target.closest('[data-rich-text-mode]');
                    if (!btn) {
                        return;
                    }
                    const root = btn.closest('[data-product-rich-text-editor]');
                    if (!root) {
                        return;
                    }
                    event.preventDefault();
                    if (btn.getAttribute('data-rich-text-mode') === 'preview') {
                        showPreview(root);
                    } else {
                        showWrite(root);
                    }
                });
            })();
        </script>
    @endpush
@endonce
