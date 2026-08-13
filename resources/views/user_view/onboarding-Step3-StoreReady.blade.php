<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store ready — Merchant workspace</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="user-typography min-h-screen flex flex-col overflow-x-hidden font-[Inter] bg-[#F5F7F8]">
    @include('user_view.partials.flash_success')

    @php
        $settings = is_array($store->settings) ? $store->settings : [];
        $primaryMarket = $settings['primary_market'] ?? 'Not set';
        $businessModels = collect($settings['business_models'] ?? [])->filter()->values();
        $contactEmail = trim((string) ($settings['contact_email'] ?? ''));
    @endphp

    <div class="w-full flex flex-col relative overflow-hidden">
        <header class="flex justify-between items-center px-4 sm:px-6 lg:px-16 py-3 bg-white border-b border-[#E2E8F0] w-full">
            <div class="flex items-center gap-4">
                <div class="w-6 h-6">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2H15.3333V8.6667H8.6667V15.3333H2V22H22V2Z" fill="#0052CC"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-[#0F172A]">Merchant workspace</span>
            </div>

            <div class="flex items-center gap-3 sm:gap-6">
                <nav class="hidden md:flex items-center gap-4 lg:gap-8 text-sm">
                    <a href="{{ route('dashboard') }}" class="text-[#475569] font-inter font-medium">Dashboard</a>
                    <a href="{{ route('products') }}" class="text-[#0052CC] font-semibold">Products</a>
                    <a href="{{ route('orders') }}" class="text-[#475569] font-inter font-medium">Orders</a>
                    <a href="{{ route('generalSettings') }}" class="text-[#475569] font-inter font-medium">Settings</a>
                </nav>
                <div class="flex items-center gap-3 sm:gap-4">
                    <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex bg-brand text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm">Go to Dashboard</a>
                </div>
            </div>
        </header>

        <main class="flex-1 flex flex-col items-center px-4 py-12 md:py-16">
            <div class="w-full max-w-[800px] flex flex-col items-center">
                <div
                    id="onboarding-stepper"
                    class="w-full max-w-[672px] mb-12 relative"
                    data-onboarding-stepper
                    data-initial-step="3"
                >
                    <div class="pointer-events-none absolute top-5 left-[calc(16.67%+20px)] right-[calc(16.67%+20px)] z-0 h-0.5 overflow-hidden rounded-full bg-[#E2E8F0]">
                        <div
                            data-stepper-fill
                            class="h-full rounded-full bg-brand transition-all duration-300 ease-out"
                            style="width: 100%"
                        ></div>
                    </div>
                    <div class="relative z-10 flex w-full justify-between" role="tablist" aria-label="Onboarding steps">
                        <button
                            type="button"
                            role="tab"
                            id="onboarding-tab-1"
                            data-step="1"
                            aria-controls="onboarding-panel-1"
                            aria-selected="false"
                            class="group flex w-1/3 flex-col items-center focus:outline-none"
                        >
                            <span data-step-node class="flex h-10 w-10 items-center justify-center rounded-full bg-brand text-white shadow-sm ring-2 ring-transparent transition group-hover:ring-[#0052CC]/20 group-focus-visible:ring-[#0052CC]/40">
                                <svg data-step-check width="14" height="11" viewBox="0 0 14 11" fill="none" aria-hidden="true">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                                <span data-step-number class="hidden text-sm font-bold">1</span>
                            </span>
                            <span data-step-label class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Create Store</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            id="onboarding-tab-2"
                            data-step="2"
                            aria-controls="onboarding-panel-2"
                            aria-selected="false"
                            class="group flex w-1/3 flex-col items-center focus:outline-none"
                        >
                            <span data-step-node class="flex h-10 w-10 items-center justify-center rounded-full bg-brand text-white shadow-sm ring-2 ring-transparent transition group-hover:ring-[#0052CC]/20 group-focus-visible:ring-[#0052CC]/40">
                                <svg data-step-check width="14" height="11" viewBox="0 0 14 11" fill="none" aria-hidden="true">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                                <span data-step-number class="hidden text-sm font-bold">2</span>
                            </span>
                            <span data-step-label class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Add Product</span>
                        </button>
                        <button
                            type="button"
                            role="tab"
                            id="onboarding-tab-3"
                            data-step="3"
                            aria-controls="onboarding-panel-3"
                            aria-selected="true"
                            class="group flex w-1/3 flex-col items-center focus:outline-none"
                        >
                            <span data-step-node class="flex h-10 w-10 items-center justify-center rounded-full bg-brand text-white shadow-sm ring-4 ring-[#0052CC]/20 transition group-hover:ring-[#0052CC]/20 group-focus-visible:ring-[#0052CC]/40">
                                <svg data-step-check width="14" height="11" viewBox="0 0 14 11" fill="none" aria-hidden="true">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                                <span data-step-number class="hidden text-sm font-bold">3</span>
                            </span>
                            <span data-step-label class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Ready</span>
                        </button>
                    </div>
                </div>

                <div
                    id="onboarding-panel-1"
                    role="tabpanel"
                    aria-labelledby="onboarding-tab-1"
                    data-step-panel="1"
                    hidden
                    class="w-full max-w-[448px]"
                >
                    <div class="mb-8 flex flex-col items-center text-center">
                        <h1 class="text-3xl font-medium text-[#0F172A] md:text-4xl">Store details</h1>
                        <p class="mt-2 max-w-[500px] text-lg text-[#475569]">Review what you set up for this store. You can still change these details before you finish.</p>
                    </div>
                    <div class="mb-8 overflow-hidden rounded-xl border border-[#0052CC]/10 bg-white shadow-xl">
                        <div class="border-b border-[#0052CC]/10 bg-brand/5 px-6 py-4">
                            <span class="text-base font-medium text-[#1E293B]">Create store</span>
                        </div>
                        <div class="space-y-4 p-6">
                            @if ($store->logo)
                                <div class="flex justify-center pb-2">
                                    <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="h-20 w-20 rounded-xl border border-[#E2E8F0] bg-white object-contain p-2 shadow-sm">
                                </div>
                            @endif
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">Store Name</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ $store->name }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">Primary market</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ $primaryMarket }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">Address</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ $store->address ?: 'Not set' }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">Currency</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ $store->currency }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">Timezone</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ $store->timezone }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">Category</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ ucfirst((string) $store->category) }}</span>
                            </div>
                            @if ($businessModels->isNotEmpty())
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#64748B]">Business model</span>
                                    <span class="text-right text-sm font-medium text-[#0F172A]">{{ $businessModels->implode(', ') }}</span>
                                </div>
                            @endif
                            @if ($contactEmail !== '')
                                <div class="flex items-center justify-between gap-4">
                                    <span class="text-sm text-[#64748B]">Contact email</span>
                                    <span class="text-right text-sm font-medium text-[#0F172A]">{{ $contactEmail }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <a href="{{ route('onboarding-StoreDetails-1') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-brand px-8 py-3 text-sm font-bold text-white shadow-lg shadow-brand/20 hover:bg-brand-hover">
                            Edit store details
                        </a>
                        <button type="button" data-step-goto="2" class="inline-flex w-full items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-8 py-3 text-sm font-semibold text-[#334155] hover:bg-gray-50">
                            Continue to Add product
                        </button>
                    </div>
                </div>

                <div
                    id="onboarding-panel-2"
                    role="tabpanel"
                    aria-labelledby="onboarding-tab-2"
                    data-step-panel="2"
                    hidden
                    class="w-full max-w-[448px]"
                >
                    <div class="mb-8 flex flex-col items-center text-center">
                        <h1 class="text-3xl font-medium text-[#0F172A] md:text-4xl">Add products</h1>
                        <p class="mt-2 max-w-[500px] text-lg text-[#475569]">Add a product in the workspace or import a catalog. You can finish this later from Products.</p>
                    </div>
                    <div class="mb-8 overflow-hidden rounded-xl border border-[#0052CC]/10 bg-white shadow-xl">
                        <div class="border-b border-[#0052CC]/10 bg-brand/5 px-6 py-4">
                            <span class="text-base font-medium text-[#1E293B]">First product</span>
                        </div>
                        <div class="space-y-4 p-6">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">Store</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ $store->name }}</span>
                            </div>
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm text-[#64748B]">First Product</span>
                                <span class="text-right text-sm font-medium text-[#0F172A]">{{ $product?->name ?? 'Not added yet' }}</span>
                            </div>
                            <p class="text-sm text-[#64748B]">Use the product workspace for a full editor, or import a file if you already have a catalog.</p>
                        </div>
                    </div>
                    <div class="flex flex-col gap-3">
                        <a href="{{ route('products.create') }}" class="inline-flex w-full items-center justify-center rounded-xl bg-brand px-8 py-3 text-sm font-bold text-white shadow-lg shadow-brand/20 hover:bg-brand-hover">
                            Add product
                        </a>
                        <a href="{{ route('products.import.create') }}" class="inline-flex w-full items-center justify-center rounded-xl border border-[#E2E8F0] bg-white px-8 py-3 text-sm font-semibold text-[#334155] hover:bg-gray-50">
                            Import products
                        </a>
                        <button type="button" data-step-goto="3" class="inline-flex w-full items-center justify-center rounded-xl border border-[#CBD5E1] px-8 py-3 text-sm font-semibold text-[#334155] hover:bg-[#F8FAFC]">
                            Continue to Ready
                        </button>
                    </div>
                </div>

                <div
                    id="onboarding-panel-3"
                    role="tabpanel"
                    aria-labelledby="onboarding-tab-3"
                    data-step-panel="3"
                    class="flex w-full flex-col items-center"
                >
                    <div class="mb-10 flex flex-col items-center text-center">
                        <div class="flex h-32 w-32 items-center justify-center rounded-full bg-brand/10">
                            <svg width="54" height="52" viewBox="0 0 54 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M0 51.25L12.5 16.25L35 38.75L0 51.25ZM8.25 43L25.875 36.75L14.5 25.375L8.25 43ZM31.375 27.625L28.75 25L42.75 11C44.0833 9.66667 45.6875 9 47.5625 9C49.4375 9 51.0417 9.66667 52.375 11L53.875 12.5L51.25 15.125L49.75 13.625C49.1667 13.0417 48.4375 12.75 47.5625 12.75C46.6875 12.75 45.9583 13.0417 45.375 13.625L31.375 27.625ZM21.375 17.625L18.75 15L20.25 13.5C20.8333 12.9167 21.125 12.2083 21.125 11.375C21.125 10.5417 20.8333 9.83333 20.25 9.25L18.625 7.625L21.25 5L22.875 6.625C24.2083 7.95833 24.875 9.54167 24.875 11.375C24.875 13.2083 24.2083 14.7917 22.875 16.125L21.375 17.625ZM26.375 22.625L23.75 20L32.75 11C33.3333 10.4167 33.625 9.6875 33.625 8.8125C33.625 7.9375 33.3333 7.20833 32.75 6.625L28.75 2.625L31.375 0L35.375 4C36.7083 5.33333 37.375 6.9375 37.375 8.8125C37.375 10.6875 36.7083 12.2917 35.375 13.625L26.375 22.625ZM36.375 32.625L33.75 30L37.75 26C39.0833 24.6667 40.6875 24 42.5625 24C44.4375 24 46.0417 24.6667 47.375 26L51.375 30L48.75 32.625L44.75 28.625C44.1667 28.0417 43.4375 27.75 42.5625 27.75C41.6875 27.75 40.9583 28.0417 40.375 28.625L36.375 32.625Z" fill="#0052CC"/>
                            </svg>
                        </div>
                        <h1 class="mt-6 text-3xl font-medium text-[#0F172A] md:text-4xl">Your management workspace is ready</h1>
                        <p class="mt-2 max-w-[500px] text-lg text-[#475569]">Continue with the next setup steps for this store. No public storefront domain is claimed until a real connected channel exists.</p>
                    </div>

                    <div class="mb-8 w-full max-w-[448px]">
                        <div class="overflow-hidden rounded-xl border border-[#0052CC]/10 bg-white shadow-xl">
                            <div class="border-b border-[#0052CC]/10 bg-brand/5 px-6 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="text-base font-medium text-[#1E293B]">Store summary</span>
                                </div>
                            </div>
                            <div class="space-y-4 p-6">
                                @if ($store->logo)
                                    <div class="flex justify-center pb-2">
                                        <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="h-20 w-20 rounded-xl border border-[#E2E8F0] bg-white object-contain p-2 shadow-sm">
                                    </div>
                                @endif
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-[#64748B]">Store Name</span>
                                    <span class="text-right text-sm font-inter font-medium text-[#0F172A]">{{ $store->name }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-[#64748B]">First Product</span>
                                    <span class="text-right text-sm font-inter font-medium text-[#0F172A]">{{ $product?->name ?? 'Not added yet' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-[#64748B]">Category</span>
                                    <span class="text-right text-sm font-inter font-medium text-[#0F172A]">{{ ucfirst((string) $store->category) }}</span>
                                </div>
                                <div class="border-t border-[#F1F5F9] pt-4">
                                    <span class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Status</span>
                                    <p class="text-sm font-bold text-[#0F172A]">Management workspace ready</p>
                                    <p class="mt-1 text-sm text-[#64748B]">Next: add products, review inventory, configure delivery, and invite a teammate.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="mb-6 w-full max-w-[448px] rounded-xl border border-[#E2E8F0] bg-white p-5">
                        <p class="text-sm font-semibold text-[#0F172A]">Suggested next steps</p>
                        <ul class="mt-3 space-y-2 text-sm text-[#475569]">
                            <li><a href="{{ route('products.create') }}" class="font-semibold text-[#0052CC] hover:underline">Add products</a> in the product workspace</li>
                            <li><a href="{{ route('products') }}" class="font-semibold text-[#0052CC] hover:underline">Review inventory</a> on your product catalog</li>
                            <li><a href="{{ route('shippingAutomation') }}" class="font-semibold text-[#0052CC] hover:underline">Configure delivery</a> for checkout</li>
                            <li><a href="{{ route('team-members.index') }}" class="font-semibold text-[#0052CC] hover:underline">Invite a teammate</a></li>
                            <li>
                                <span class="font-semibold text-[#0F172A]">Connect a selling channel</span>
                                <span class="text-[#64748B]"> when the production connected-channel feature becomes available. The developer test storefront is not a required setup step.</span>
                            </li>
                        </ul>
                    </div>

                    <form method="POST" action="{{ route('onboarding_StoreReady.complete') }}" class="w-full max-w-[448px]">
                        @csrf
                        <div class="flex w-full flex-col gap-4">
                            <button type="submit" class="flex w-full items-center justify-center gap-2 rounded-xl bg-brand px-8 py-4 font-bold text-white shadow-lg shadow-brand/20 transition hover:bg-brand-hover">
                                <span>Go to Dashboard</span>
                            </button>
                            <a href="{{ route('products.create') }}" class="flex w-full items-center justify-center gap-2 rounded-xl border border-[#E2E8F0] bg-white px-8 py-3 font-semibold text-[#334155] transition hover:bg-gray-50">
                                <span>Add another product</span>
                            </a>
                        </div>

                        <label class="mt-8 flex cursor-pointer items-center gap-2">
                            <input type="checkbox" name="dont_show_again" value="1" class="h-4 w-4 rounded border-[#CBD5E1] text-[#0052CC] focus:ring-[#0052CC]/20">
                            <span class="text-sm text-[#64748B]">Don't show this screen again</span>
                        </label>
                    </form>
                </div>
            </div>
        </main>
    </div>
    <script>
        (() => {
            const root = document.querySelector('[data-onboarding-stepper]');
            if (!root) {
                return;
            }

            const tabs = [...root.querySelectorAll('[role="tab"][data-step]')];
            const panels = [...document.querySelectorAll('[data-step-panel]')];
            const fill = root.querySelector('[data-stepper-fill]');
            const hashes = { 1: 'create-store', 2: 'add-product', 3: 'ready' };
            const hashToStep = Object.fromEntries(Object.entries(hashes).map(([step, hash]) => [hash, Number(step)]));

            const fillWidth = (step) => {
                if (step <= 1) {
                    return '0%';
                }
                if (step === 2) {
                    return '50%';
                }
                return '100%';
            };

            const setNodeState = (tab, step, activeStep) => {
                const node = tab.querySelector('[data-step-node]');
                const check = tab.querySelector('[data-step-check]');
                const number = tab.querySelector('[data-step-number]');
                const label = tab.querySelector('[data-step-label]');
                const isActive = step === activeStep;
                const isComplete = step < activeStep;

                tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
                tab.tabIndex = isActive ? 0 : -1;

                node.classList.toggle('bg-brand', isActive || isComplete);
                node.classList.toggle('text-white', isActive || isComplete);
                node.classList.toggle('bg-white', !isActive && !isComplete);
                node.classList.toggle('text-[#94A3B8]', !isActive && !isComplete);
                node.classList.toggle('border', !isActive && !isComplete);
                node.classList.toggle('border-[#CBD5E1]', !isActive && !isComplete);
                node.classList.toggle('ring-4', isActive);
                node.classList.toggle('ring-[#0052CC]/20', isActive);
                node.classList.toggle('ring-2', !isActive);
                node.classList.toggle('ring-transparent', !isActive);

                check.classList.toggle('hidden', !isActive && !isComplete);
                number.classList.toggle('hidden', isActive || isComplete);

                label.classList.toggle('text-[#0052CC]', isActive || isComplete);
                label.classList.toggle('text-[#94A3B8]', !isActive && !isComplete);
            };

            const showStep = (nextStep, { updateHash = true } = {}) => {
                const step = Math.min(3, Math.max(1, Number(nextStep) || 3));

                tabs.forEach((tab) => setNodeState(tab, Number(tab.dataset.step), step));
                panels.forEach((panel) => {
                    panel.hidden = Number(panel.dataset.stepPanel) !== step;
                });

                if (fill) {
                    fill.style.width = fillWidth(step);
                }

                if (updateHash) {
                    const hash = hashes[step];
                    if (hash && window.location.hash.replace('#', '') !== hash) {
                        history.replaceState(null, '', `#${hash}`);
                    }
                }
            };

            const stepFromHash = () => hashToStep[window.location.hash.replace('#', '')] || Number(root.dataset.initialStep || 3);

            tabs.forEach((tab) => {
                tab.addEventListener('click', () => showStep(tab.dataset.step));
                tab.addEventListener('keydown', (event) => {
                    const current = Number(tab.dataset.step);
                    if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
                        event.preventDefault();
                        const next = current === 3 ? 1 : current + 1;
                        showStep(next);
                        root.querySelector(`[data-step="${next}"]`)?.focus();
                    }
                    if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
                        event.preventDefault();
                        const previous = current === 1 ? 3 : current - 1;
                        showStep(previous);
                        root.querySelector(`[data-step="${previous}"]`)?.focus();
                    }
                });
            });

            document.querySelectorAll('[data-step-goto]').forEach((button) => {
                button.addEventListener('click', () => showStep(button.dataset.stepGoto));
            });

            window.addEventListener('hashchange', () => showStep(stepFromHash(), { updateHash: false }));
            showStep(stepFromHash(), { updateHash: false });
        })();
    </script>
</body>
</html>
