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
            } elseif (str_contains($normalizedMessage, 'category')) {
                $successTitle = 'Category saved';
            } elseif (str_contains($normalizedMessage, 'onboarding')) {
                $successTitle = 'Setup completed';
            } else {
                $successTitle = 'Success';
            }
        }

        $successMeta = session('success_meta');
    @endphp

    <div
        data-success-flash
        class="pointer-events-none fixed right-4 top-4 z-[100] w-[calc(100%-2rem)] max-w-md opacity-0 translate-y-[-12px] transition-all duration-500 ease-out sm:right-6 sm:top-6"
        role="status"
        aria-live="polite"
    >
        <div class="pointer-events-auto overflow-hidden rounded-[24px] border border-emerald-200/80 bg-[linear-gradient(135deg,#ffffff_0%,#f3fff8_45%,#ecfdf3_100%)] shadow-[0_24px_60px_rgba(5,150,105,0.18)] ring-1 ring-emerald-100/70">
            <div class="absolute inset-x-0 top-0 h-1 bg-[linear-gradient(90deg,#10B981_0%,#34D399_55%,#A7F3D0_100%)]" aria-hidden="true"></div>
            <div class="relative p-5 sm:p-6">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 shadow-[0_10px_25px_rgba(16,185,129,0.35)]">
                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true">
                            <path d="M11 21C16.5228 21 21 16.5228 21 11C21 5.47715 16.5228 1 11 1C5.47715 1 1 5.47715 1 11C1 16.5228 5.47715 21 11 21Z" fill="white" fill-opacity="0.18"/>
                            <path d="M7 11.25L9.75 14L15.25 8.5" stroke="white" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-emerald-600/80">Action completed</p>
                                <h3 class="mt-1 text-lg font-semibold text-slate-900">{{ $successTitle }}</h3>
                            </div>
                            <button
                                type="button"
                                data-success-flash-close
                                class="mt-0.5 inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-emerald-100 bg-white/80 text-slate-400 transition hover:border-emerald-200 hover:text-slate-600"
                                aria-label="Dismiss success message"
                            >
                                <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true">
                                    <path d="M10.5 3.5L3.5 10.5M3.5 3.5L10.5 10.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                                </svg>
                            </button>
                        </div>
                        <p class="mt-2 text-sm leading-6 text-slate-600">{{ $successMessage }}</p>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200/70">
                                Saved just now
                            </span>
                            @if ($successMeta)
                                <span class="inline-flex items-center rounded-full bg-white px-3 py-1 text-xs font-medium text-slate-500 ring-1 ring-slate-200">
                                    {{ $successMeta }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
                <div class="mt-5 h-1.5 overflow-hidden rounded-full bg-emerald-100/80">
                    <div data-success-flash-progress class="h-full rounded-full bg-[linear-gradient(90deg,#10B981_0%,#34D399_100%)] origin-left"></div>
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

            const closeButton = flash.querySelector('[data-success-flash-close]');
            const progressBar = flash.querySelector('[data-success-flash-progress]');
            const hideDelay = 4800;

            requestAnimationFrame(() => {
                flash.classList.remove('opacity-0', 'translate-y-[-12px]');
                flash.classList.add('opacity-100', 'translate-y-0');
            });

            if (progressBar) {
                progressBar.animate(
                    [
                        { transform: 'scaleX(1)' },
                        { transform: 'scaleX(0)' },
                    ],
                    {
                        duration: hideDelay,
                        easing: 'linear',
                        fill: 'forwards',
                    }
                );
            }

            const hideFlash = () => {
                flash.classList.add('opacity-0', 'translate-y-[-12px]');
                flash.classList.remove('opacity-100', 'translate-y-0');

                window.setTimeout(() => {
                    flash.remove();
                }, 400);
            };

            const hideTimer = window.setTimeout(hideFlash, hideDelay);

            closeButton?.addEventListener('click', () => {
                window.clearTimeout(hideTimer);
                hideFlash();
            });
        })();
    </script>
@endif
