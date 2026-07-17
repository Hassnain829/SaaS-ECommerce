@if (session('success'))
    @php
        $successMessage = session('success');
        $successTitle = session('success_title');

        if (!$successTitle) {
            $normalizedMessage = strtolower($successMessage);

            if (str_contains($normalizedMessage, 'store')) {
                $successTitle = 'Store updated';
            } elseif (str_contains($normalizedMessage, 'product')) {
                $successTitle = 'Product updated';
            } elseif (str_contains($normalizedMessage, 'variation')) {
                $successTitle = 'Variation saved';
            } elseif (str_contains($normalizedMessage, 'brand')) {
                $successTitle = 'Brand updated';
            } elseif (str_contains($normalizedMessage, 'category')) {
                $successTitle = 'Category saved';
            } elseif (str_contains($normalizedMessage, 'onboarding')) {
                $successTitle = 'Setup completed';
            } else {
                $successTitle = 'Saved';
            }
        }

        $successMeta = session('success_meta');
    @endphp

    <div
        data-success-flash
        class="pointer-events-none fixed right-4 top-4 z-[200] w-[calc(100%-2rem)] max-w-sm opacity-0 translate-y-[-8px] transition-all duration-300 ease-out sm:right-6 sm:top-5"
        role="status"
        aria-live="polite"
    >
        <div class="pointer-events-auto overflow-hidden rounded-xl border border-emerald-200 bg-white shadow-lg ring-1 ring-emerald-100/80">
            <div class="flex items-start gap-3 p-4">
                <div class="mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-emerald-500 text-white">
                    <svg width="16" height="16" viewBox="0 0 22 22" fill="none" aria-hidden="true">
                        <path d="M7 11.25L9.75 14L15.25 8.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <p class="text-sm font-semibold text-slate-900">{{ $successTitle }}</p>
                            <p class="mt-1 text-sm leading-5 text-slate-600">{{ $successMessage }}</p>
                            @if ($successMeta)
                                <p class="mt-1 text-xs text-slate-500">{{ $successMeta }}</p>
                            @endif
                        </div>
                        <button
                            type="button"
                            data-success-flash-close
                            class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-100 hover:text-slate-600"
                            aria-label="Dismiss success message"
                        >
                            <svg width="12" height="12" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                <path d="M10.5 3.5L3.5 10.5M3.5 3.5L10.5 10.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                            </svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        (() => {
            const flash = document.querySelector('[data-success-flash]');

            if (!flash || flash.dataset.bound === 'true') {
                return;
            }

            flash.dataset.bound = 'true';

            // Escape page/topbar stacking contexts so the toast stays visible above chrome.
            if (flash.parentElement !== document.body) {
                document.body.appendChild(flash);
            }

            const closeButton = flash.querySelector('[data-success-flash-close]');
            const hideDelay = 4200;

            requestAnimationFrame(() => {
                flash.classList.remove('opacity-0', 'translate-y-[-8px]');
                flash.classList.add('opacity-100', 'translate-y-0');
            });

            const hideFlash = () => {
                flash.classList.add('opacity-0', 'translate-y-[-8px]');
                flash.classList.remove('opacity-100', 'translate-y-0');

                window.setTimeout(() => {
                    flash.remove();
                }, 250);
            };

            const hideTimer = window.setTimeout(hideFlash, hideDelay);

            closeButton?.addEventListener('click', () => {
                window.clearTimeout(hideTimer);
                hideFlash();
            });
        })();
    </script>
@endif
