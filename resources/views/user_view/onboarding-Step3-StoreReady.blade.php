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
                <div class="w-full max-w-[672px] mb-12 relative">
                    <div class="absolute top-5 left-[calc(16.67%+20px)] right-[calc(16.67%+20px)] h-0.5 bg-brand z-0"></div>
                    <div class="relative z-10 flex justify-between items-start w-full">
                        <div class="flex flex-col items-center w-1/3">
                            <div class="w-10 h-10 bg-brand rounded-full flex items-center justify-center">
                                <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Create Store</span>
                        </div>
                        <div class="flex flex-col items-center w-1/3">
                            <div class="w-10 h-10 bg-brand rounded-full flex items-center justify-center">
                                <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Add Product</span>
                        </div>
                        <div class="flex flex-col items-center w-1/3">
                            <div class="w-10 h-10 bg-brand rounded-full flex items-center justify-center">
                                <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Ready</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col items-center text-center mb-10">
                    <div class="w-32 h-32 bg-brand/10 rounded-full flex items-center justify-center">
                        <svg width="54" height="52" viewBox="0 0 54 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0 51.25L12.5 16.25L35 38.75L0 51.25ZM8.25 43L25.875 36.75L14.5 25.375L8.25 43ZM31.375 27.625L28.75 25L42.75 11C44.0833 9.66667 45.6875 9 47.5625 9C49.4375 9 51.0417 9.66667 52.375 11L53.875 12.5L51.25 15.125L49.75 13.625C49.1667 13.0417 48.4375 12.75 47.5625 12.75C46.6875 12.75 45.9583 13.0417 45.375 13.625L31.375 27.625ZM21.375 17.625L18.75 15L20.25 13.5C20.8333 12.9167 21.125 12.2083 21.125 11.375C21.125 10.5417 20.8333 9.83333 20.25 9.25L18.625 7.625L21.25 5L22.875 6.625C24.2083 7.95833 24.875 9.54167 24.875 11.375C24.875 13.2083 24.2083 14.7917 22.875 16.125L21.375 17.625ZM26.375 22.625L23.75 20L32.75 11C33.3333 10.4167 33.625 9.6875 33.625 8.8125C33.625 7.9375 33.3333 7.20833 32.75 6.625L28.75 2.625L31.375 0L35.375 4C36.7083 5.33333 37.375 6.9375 37.375 8.8125C37.375 10.6875 36.7083 12.2917 35.375 13.625L26.375 22.625ZM36.375 32.625L33.75 30L37.75 26C39.0833 24.6667 40.6875 24 42.5625 24C44.4375 24 46.0417 24.6667 47.375 26L51.375 30L48.75 32.625L44.75 28.625C44.1667 28.0417 43.4375 27.75 42.5625 27.75C41.6875 27.75 40.9583 28.0417 40.375 28.625L36.375 32.625Z" fill="#0052CC"/>
                        </svg>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-medium text-[#0F172A] mt-6">Your management workspace is ready</h1>
                    <p class="text-lg text-[#475569] mt-2 max-w-[500px]">Continue with the next setup steps for this store. No public storefront domain is claimed until a real connected channel exists.</p>
                </div>

                <div class="w-full max-w-[448px] mb-8">
                    <div class="bg-white rounded-xl shadow-xl border border-[#0052CC]/10 overflow-hidden">
                        <div class="bg-brand/5 px-6 py-4 border-b border-[#0052CC]/10">
                            <div class="flex items-center gap-2">
                                <span class="text-base font-medium text-[#1E293B]">Store summary</span>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            @if ($store->logo)
                                <div class="flex justify-center pb-2">
                                    <img src="{{ asset('storage/'.$store->logo) }}" alt="{{ $store->name }} logo" class="h-20 w-20 rounded-xl border border-[#E2E8F0] bg-white object-contain p-2 shadow-sm">
                                </div>
                            @endif
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-[#64748B]">Store Name</span>
                                <span class="text-sm font-inter font-medium text-[#0F172A] text-right">{{ $store->name }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-[#64748B]">First Product</span>
                                <span class="text-sm font-inter font-medium text-[#0F172A] text-right">{{ $product?->name ?? 'Not added yet' }}</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-[#64748B]">Category</span>
                                <span class="text-sm font-inter font-medium text-[#0F172A] text-right">{{ ucfirst((string) $store->category) }}</span>
                            </div>
                            <div class="pt-4 border-t border-[#F1F5F9]">
                                <span class="text-xs font-bold uppercase tracking-wide text-[#64748B]">Status</span>
                                <p class="text-sm font-bold text-[#0F172A]">Management workspace ready</p>
                                <p class="mt-1 text-sm text-[#64748B]">Next: add products, review inventory, configure delivery, and invite a teammate.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="w-full max-w-[448px] mb-6 rounded-xl border border-[#E2E8F0] bg-white p-5">
                    <p class="text-sm font-semibold text-[#0F172A]">Suggested next steps</p>
                    <ul class="mt-3 space-y-2 text-sm text-[#475569]">
                        <li><a href="{{ route('products.create') }}" class="font-semibold text-[#0052CC] hover:underline">Add products</a> in the product workspace</li>
                        <li><a href="{{ route('products.import.create') }}" class="font-semibold text-[#0052CC] hover:underline">Import a catalog</a> when you have a file ready</li>
                        <li><a href="{{ route('shippingAutomation') }}" class="font-semibold text-[#0052CC] hover:underline">Configure delivery</a> for checkout</li>
                        <li><a href="{{ route('developer-storefront.settings') }}" class="font-semibold text-[#0052CC] hover:underline">Connect a selling channel</a> when ready</li>
                        <li><a href="{{ route('team-members.index') }}" class="font-semibold text-[#0052CC] hover:underline">Invite a teammate</a></li>
                    </ul>
                </div>

                <form method="POST" action="{{ route('onboarding_StoreReady.complete') }}" class="w-full max-w-[448px]">
                    @csrf
                    <div class="w-full flex flex-col gap-4">
                        <button type="submit" class="w-full bg-brand text-white font-bold py-4 px-8 rounded-xl shadow-lg shadow-brand/20 flex items-center justify-center gap-2 hover:bg-brand-hover transition">
                            <span>Go to Dashboard</span>
                        </button>
                        <a href="{{ route('products.create') }}" class="w-full bg-white border border-[#E2E8F0] text-[#334155] font-semibold py-3 px-8 rounded-xl flex items-center justify-center gap-2 hover:bg-gray-50 transition">
                            <span>Add another product</span>
                        </a>
                    </div>

                    <label class="flex items-center gap-2 mt-8 cursor-pointer">
                        <input type="checkbox" name="dont_show_again" value="1" class="h-4 w-4 rounded border-[#CBD5E1] text-[#0052CC] focus:ring-[#0052CC]/20">
                        <span class="text-sm text-[#64748B]">Don't show this screen again</span>
                    </label>
                </form>
            </div>
        </main>
    </div>
</body>
</html>
