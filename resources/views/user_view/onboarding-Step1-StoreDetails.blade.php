<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding - Store Details | BaaS Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F5F7F8] antialiased text-[#0F172A] min-h-screen flex flex-col overflow-x-hidden font-[Inter]">
    @include('user_view.partials.flash_success')

    <div class="w-full bg-[#F5F7F8] flex flex-col">
        <header class="flex justify-between items-center px-4 sm:px-6 lg:px-16 py-3 bg-white border-b border-[#E2E8F0] w-full">
            <div class="flex items-center gap-4">
                <div class="w-6 h-6">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2H15.3333V8.6667H8.6667V15.3333H2V22H22V2Z" fill="#0052CC"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-[#0F172A]">BaaS Platform</span>
            </div>

            <div class="flex items-center gap-3 sm:gap-6">
                <nav class="hidden md:flex items-center gap-4 lg:gap-8 text-sm">
                    <a href="{{ route('dashboard') }}" class="text-[#475569] font-inter font-medium">Dashboard</a>
                    <a href="{{ route('products') }}" class="text-[#0052CC] font-semibold">Products</a>
                    <a href="{{ route('orders') }}" class="text-[#475569] font-inter font-medium">Orders</a>
                    <a href="{{ route('generalSettings') }}" class="text-[#475569] font-inter font-medium">Settings</a>
                </nav>
                <div class="flex items-center gap-3 sm:gap-4">
                    <button form="store-onboarding-form" type="submit" class="hidden sm:inline-flex bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm">Save & Continue</button>
                    <div class="w-10 h-10 rounded-full border border-[#E2E8F0] overflow-hidden bg-[#E2E8F0]">
                        <svg width="40" height="40" viewBox="0 0 40 40" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <rect width="40" height="40" rx="20" fill="#CBD5E1" />
                            <circle cx="20" cy="15" r="6" fill="#94A3B8" />
                            <path d="M30 30C30 26 26 22 20 22C14 22 10 26 10 30" fill="#94A3B8" />
                        </svg>
                    </div>
                </div>
            </div>
        </header>

        <main class="w-full max-w-5xl mx-auto px-6 py-8 sm:px-10">
            <nav class="flex items-center gap-2 text-sm font-inter font-medium mb-6">
                <a href="{{ route('onboarding-StoreDetails-1') }}" class="text-[#0052CC] opacity-70 hover:opacity-100">Onboarding</a>
                <svg width="5" height="7" viewBox="0 0 5 7" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="#94A3B8"/>
                </svg>
                <span class="text-[#0F172A]">Store Details</span>
            </nav>

            <div class="mb-8">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-inter font-medium text-[#64748B] uppercase tracking-wider">Step 1 of 3</span>
                    <span class="text-xs text-[#64748B]">Setup Progress: 33% Complete</span>
                </div>
                <div class="w-full h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                    <div class="w-1/3 h-2 bg-[#0052CC] rounded-full"></div>
                </div>
                <div class="flex justify-end mt-1">
                    <span class="text-xs text-[#0052CC] font-inter font-medium">Next: Add First Product</span>
                </div>
            </div>

            <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-6 md:p-8">
                <div class="mb-8">
                    <h1 class="text-3xl font-medium text-[#0F172A] font-[Poppins]">Let's set up your store</h1>
                    <p class="text-base text-[#64748B] mt-1">Fill in the essential details to create your digital storefront.</p>
                </div>

                @if ($errors->any())
                    <div class="mb-6 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                        <ul class="list-disc ml-5">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @php
                    $selectedCategory = old('category', $storeDraft['category'] ?? 'physical');
                    $businessModelOld = old('business_models', $storeDraft['business_models'] ?? []);
                    $customCategoryOld = old('custom_category', $storeDraft['custom_category'] ?? '');
                @endphp

                <form id="store-onboarding-form" action="{{ route('onboarding-StoreDetails-1.store') }}" method="POST" enctype="multipart/form-data" class="space-y-8">
                    @csrf
                    {{-- Determine if this is a new store creation or editing --}}
                    <input type="hidden" name="mode" value="{{ !empty($storeDraft) && isset($storeDraft['name']) ? 'edit' : 'create' }}">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-[#334155] mb-2 font-[Poppins]">Store Name</label>
                            <input id="name" name="name" type="text" placeholder="e.g. Modern Marketplace" value="{{ old('name', $storeDraft['name'] ?? '') }}"
                                   class="w-full px-4 py-3 border border-[#CBD5E1] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20 focus:border-[#0052CC]">
                        </div>
                        <div>
                            <label for="primary_market" class="block text-sm font-medium text-[#334155] mb-2 font-[Poppins]">Primary Market</label>
                            <div class="relative">
                                <select id="primary_market" name="primary_market" class="w-full appearance-none px-4 py-3 border border-[#CBD5E1] rounded-lg text-sm text-[#0F172A] bg-white focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                    @foreach ($primaryMarkets as $market)
                                        <option value="{{ $market }}" @selected(old('primary_market', $storeDraft['primary_market'] ?? 'Global Market') === $market)>{{ $market }}</option>
                                    @endforeach
                                </select>
                              <!--  <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[#6B7280]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg> -->
                            </div>
                        </div>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-[#334155] mb-2 font-[Poppins]">Store Logo</label>
                        <div class="bg-[#F8FAFC] border-2 border-dashed border-[#CBD5E1] rounded-xl p-8 flex flex-col items-center gap-4">
                            <div class="bg-white rounded-full p-3 shadow-sm">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M14 2H6C5.46957 2 4.96086 2.21071 4.58579 2.58579C4.21071 2.96086 4 3.46957 4 4V20C4 20.5304 4.21071 21.0391 4.58579 21.4142C4.96086 21.7893 5.46957 22 6 22H18C18.5304 22 19.0391 21.7893 19.4142 21.4142C19.7893 21.0391 20 20.5304 20 20V8L14 2Z" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M14 2V8H20" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M12 18V12" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                    <path d="M9 15H15" stroke="#94A3B8" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </div>
                            <div class="text-center">
                                <p class="text-sm font-bold text-[#0F172A]">Upload Store Logo</p>
                                <p class="text-xs text-[#64748B] mt-1">PNG, JPG or SVG. Max 2MB.</p>
                            </div>
                            <label for="store_logo" class="inline-flex h-9 items-center justify-center whitespace-nowrap bg-[#0052CC] px-4 text-xs font-bold text-white rounded-lg shadow-md hover:bg-[#0042a3] transition cursor-pointer">Browse Files</label>
                            <input id="store_logo" name="store_logo" type="file" accept=".jpg,.jpeg,.png,.svg" class="hidden">
                        </div>
                    </div>

                    <div>
                        <label for="address" class="block text-sm font-medium text-[#334155] mb-2 font-[Poppins]">Business Address</label>
                        <textarea id="address" name="address" rows="3" placeholder="Street address, City, State, Zip Code" class="w-full px-4 py-3 border border-[#CBD5E1] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">{{ old('address', $storeDraft['address'] ?? '') }}</textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label for="currency" class="block text-sm font-medium text-[#334155] mb-2 font-[Poppins]">Store Currency</label>
                            <div class="relative">
                                <select id="currency" name="currency" class="w-full appearance-none px-4 py-3 border border-[#CBD5E1] rounded-lg text-sm text-[#0F172A] bg-white">
                                    @foreach ($currencies as $currency)
                                        <option value="{{ $currency }}" @selected(old('currency', $storeDraft['currency'] ?? 'USD') === $currency)>{{ $currency }}</option>
                                    @endforeach
                                </select>
                              <!--  <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[#6B7280]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg> -->
                            </div>
                        </div>
                        <div>
                            <label for="timezone" class="block text-sm font-medium text-[#334155] mb-2 font-[Poppins]">Timezone</label>
                            <div class="relative">
                                <select id="timezone" name="timezone" class="w-full appearance-none px-4 py-3 border border-[#CBD5E1] rounded-lg text-sm text-[#0F172A] bg-white">
                                    @foreach ($timezones as $timezone)
                                        <option value="{{ $timezone }}" @selected(old('timezone', $storeDraft['timezone'] ?? 'UTC') === $timezone)>{{ $timezone }}</option>
                                    @endforeach
                                </select>
                             <!--   <svg class="absolute right-4 top-1/2 -translate-y-1/2 w-5 h-5 text-[#6B7280]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                </svg> -->
                            </div>
                        </div>
                    </div>

                    <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-sm font-semibold text-[#334155]">Business Model & Category</h3>
                            <span class="text-xs text-[#94A3B8] font-inter font-medium">Select one or more</span>
                        </div>
                        <input id="categoryInput" type="hidden" name="category" value="{{ $selectedCategory }}">
                        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                            <div class="category-tile bg-[#0052CC]/5 border-2 border-[#0052CC] rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" data-category="physical" data-model="Physical Goods">
                                <svg width="24" height="24" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M3 20C2.45 20 1.97917 19.8042 1.5875 19.4125C1.19583 19.0208 1 18.55 1 18V6.725C0.7 6.54167 0.458333 6.30417 0.275 6.0125C0.0916667 5.72083 0 5.38333 0 5V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V5C20 5.38333 19.9083 5.72083 19.725 6.0125C19.5417 6.30417 19.3 6.54167 19 6.725V18C19 18.55 18.8042 19.0208 18.4125 19.4125C18.0208 19.8042 17.55 20 17 20H3ZM3 7V18H17V7H3ZM2 5H18V2H2V5ZM7 12H13V10H7V12Z" fill="#0052CC"/>
                                </svg>
                                <span class="text-xs text-center font-[Poppins] text-[#0F172A]">Physical Goods</span>
                            </div>
                            <div class="category-tile bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" data-category="digital" data-model="Digital Products">
                                <svg width="24" height="24" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M8 12L3 7L4.4 5.55L7 8.15V0H9V8.15L11.6 5.55L13 7L8 12ZM2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14V11H2V14H14V11H16V14C16 14.55 15.8042 15.0208 15.4125 15.4125C15.0208 15.8042 14.55 16 14 16H2Z" fill="#64748B"/>
                                </svg>
                                <span class="text-xs text-center font-[Poppins] text-[#0F172A]">Digital Products</span>
                            </div>
                            <div class="category-tile bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" data-category="service" data-model="Services">
                                <svg width="24" height="24" viewBox="0 0 23 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M10.885 18C10.9517 18 11.0183 17.9833 11.085 17.95C11.1517 17.9167 11.2017 17.8833 11.235 17.85L19.435 9.65C19.635 9.45 19.7808 9.225 19.8725 8.975C19.9642 8.725 20.01 8.475 20.01 8.225C20.01 7.95833 19.9642 7.70417 19.8725 7.4625C19.7808 7.22083 19.635 7.00833 19.435 6.825L15.185 2.575C15.0017 2.375 14.7892 2.22917 14.5475 2.1375C14.3058 2.04583 14.0517 2 13.785 2C13.535 2 13.285 2.04583 13.035 2.1375C12.785 2.22917 12.56 2.375 12.36 2.575L12.085 2.85L13.935 4.725C14.185 4.95833 14.3683 5.225 14.485 5.525C14.6017 5.825 14.66 6.14167 14.66 6.475C14.66 7.175 14.4225 7.7625 13.9475 8.2375C13.4725 8.7125 12.885 8.95 12.185 8.95C11.8517 8.95 11.5308 8.89167 11.2225 8.775C10.9142 8.65833 10.6433 8.48333 10.41 8.25L8.535 6.4L4.16 10.775C4.11 10.825 4.0725 10.8792 4.0475 10.9375C4.0225 10.9958 4.01 11.0583 4.01 11.125C4.01 11.2583 4.06 11.3792 4.16 11.4875C4.26 11.5958 4.37667 11.65 4.51 11.65C4.57667 11.65 4.64333 11.6333 4.71 11.6C4.77667 11.5667 4.82667 11.5333 4.86 11.5L8.26 8.1L9.66 9.5L6.285 12.9C6.235 12.95 6.1975 13.0042 6.1725 13.0625C6.1475 13.1208 6.135 13.1833 6.135 13.25C6.135 13.3833 6.185 13.5 6.285 13.6C6.385 13.7 6.50167 13.75 6.635 13.75C6.70167 13.75 6.76833 13.7333 6.835 13.7C6.90167 13.6667 6.95167 13.6333 6.985 13.6L10.385 10.225L11.785 11.625L8.41 15.025C8.36 15.0583 8.3225 15.1083 8.2975 15.175C8.2725 15.2417 8.26 15.3083 8.26 15.375C8.26 15.5083 8.31 15.625 8.41 15.725C8.51 15.825 8.62667 15.875 8.76 15.875C8.82667 15.875 8.88917 15.8625 8.9475 15.8375C9.00583 15.8125 9.06 15.775 9.11 15.725L12.51 12.35L13.91 13.75L10.51 17.15C10.46 17.2 10.4225 17.2542 10.3975 17.3125C10.3725 17.3708 10.36 17.4333 10.36 17.5C10.36 17.6333 10.4142 17.75 10.5225 17.85C10.6308 17.95 10.7517 18 10.885 18ZM10.86 20C10.2433 20 9.6975 19.7958 9.2225 19.3875C8.7475 18.9792 8.46833 18.4667 8.385 17.85C7.81833 17.7667 7.34333 17.5333 6.96 17.15C6.57667 16.7667 6.34333 16.2917 6.26 15.725C5.69333 15.6417 5.2225 15.4042 4.8475 15.0125C4.4725 14.6208 4.24333 14.15 4.16 13.6C3.52667 13.5167 3.01 13.2417 2.61 12.775C2.21 12.3083 2.01 11.7583 2.01 11.125C2.01 10.7917 2.0725 10.4708 2.1975 10.1625C2.3225 9.85417 2.50167 9.58333 2.735 9.35L8.535 3.575L11.81 6.85C11.8433 6.9 11.8933 6.9375 11.96 6.9625C12.0267 6.9875 12.0933 7 12.16 7C12.31 7 12.435 6.95417 12.535 6.8625C12.635 6.77083 12.685 6.65 12.685 6.5C12.685 6.43333 12.6725 6.36667 12.6475 6.3C12.6225 6.23333 12.585 6.18333 12.535 6.15L8.96 2.575C8.77667 2.375 8.56417 2.22917 8.3225 2.1375C8.08083 2.04583 7.82667 2 7.56 2C7.31 2 7.06 2.04583 6.81 2.1375C6.56 2.22917 6.335 2.375 6.135 2.575L2.61 6.125C2.46 6.275 2.335 6.45 2.235 6.65C2.135 6.85 2.06833 7.05 2.035 7.25C2.00167 7.45 2.00167 7.65417 2.035 7.8625C2.06833 8.07083 2.135 8.26667 2.235 8.45L0.785 9.9C0.501667 9.51667 0.293333 9.09583 0.16 8.6375C0.0266667 8.17917 -0.0233333 7.71667 0.01 7.25C0.0433333 6.78333 0.16 6.32917 0.36 5.8875C0.56 5.44583 0.835 5.05 1.185 4.7L4.71 1.175C5.11 0.791667 5.55583 0.5 6.0475 0.3C6.53917 0.1 7.04333 0 7.56 0C8.07667 0 8.58083 0.1 9.0725 0.3C9.56417 0.5 10.0017 0.791667 10.385 1.175L10.66 1.45L10.935 1.175C11.335 0.791667 11.7808 0.5 12.2725 0.3C12.7642 0.1 13.2683 0 13.785 0C14.3017 0 14.8058 0.1 15.2975 0.3C15.7892 0.5 16.2267 0.791667 16.61 1.175L20.835 5.4C21.2183 5.78333 21.51 6.225 21.71 6.725C21.91 7.225 22.01 7.73333 22.01 8.25C22.01 8.76667 21.91 9.27083 21.71 9.7625C21.51 10.2542 21.2183 10.6917 20.835 11.075L12.635 19.25C12.4017 19.4833 12.1308 19.6667 11.8225 19.8C11.5142 19.9333 11.1933 20 10.86 20Z" fill="#64748B"/>
                                </svg>
                                <span class="text-xs text-center font-[Poppins] text-[#0F172A]">Services</span>
                            </div>
                            <div class="category-tile bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" data-category="subscription" data-model="Subscriptions">
                                <svg width="24" height="24" viewBox="0 0 21 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2 20C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V4C0 3.45 0.195833 2.97917 0.5875 2.5875C0.979167 2.19583 1.45 2 2 2H3V0H5V2H13V0H15V2H16C16.55 2 17.0208 2.19583 17.4125 2.5875C17.8042 2.97917 18 3.45 18 4V10H16V8H2V18H9V20H2ZM16 22C14.7833 22 13.7208 21.6208 12.8125 20.8625C11.9042 20.1042 11.3333 19.15 11.1 18H12.65C12.8667 18.7333 13.2792 19.3333 13.8875 19.8C14.4958 20.2667 15.2 20.5 16 20.5C16.9667 20.5 17.7917 20.1583 18.475 19.475C19.1583 18.7917 19.5 17.9667 19.5 17C19.5 16.0333 19.1583 15.2083 18.475 14.525C17.7917 13.8417 16.9667 13.5 16 13.5C15.5167 13.5 15.0667 13.5875 14.65 13.7625C14.2333 13.9375 13.8667 14.1833 13.55 14.5H15V16H11V12H12.5V13.425C12.95 12.9917 13.475 12.6458 14.075 12.3875C14.675 12.1292 15.3167 12 16 12C17.3833 12 18.5625 12.4875 19.5375 13.4625C20.5125 14.4375 21 15.6167 21 17C21 18.3833 20.5125 19.5625 19.5375 20.5375C18.5625 21.5125 17.3833 22 16 22Z" fill="#64748B"/>
                                </svg>
                                <span class="text-xs text-center font-[Poppins] text-[#0F172A]">Subscriptions</span>
                            </div>
                            <div class="category-tile bg-white border border-[#E2E8F0] rounded-xl p-4 flex flex-col items-center gap-2 cursor-pointer" data-category="virtual" data-model="Memberships">
                                <svg width="24" height="24" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2 11V13H18V11H2ZM2 0H18C18.55 0 19.0208 0.195833 19.4125 0.5875C19.8042 0.979167 20 1.45 20 2V13C20 13.55 19.8042 14.0208 19.4125 14.4125C19.0208 14.8042 18.55 15 18 15H14V20L10 18L6 20V15H2C1.45 15 0.979167 14.8042 0.5875 14.4125C0.195833 14.0208 0 13.55 0 13V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0Z" fill="#64748B"/>
                                </svg>
                                <span class="text-xs text-center font-[Poppins] text-[#0F172A]">Memberships</span>
                            </div>
                            <button id="openCustomCategoryModal" type="button" class="bg-white border-2 border-dashed border-[#CBD5E1] rounded-xl p-4 flex flex-col items-center justify-center gap-2 cursor-pointer hover:border-[#94A3B8] transition w-full">
                                <svg width="24" height="24" viewBox="0 0 25 25" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M11.25 18.75H13.75V13.75H18.75V11.25H13.75V6.25H11.25V11.25H6.25V13.75H11.25V18.75ZM12.5 25C10.7708 25 9.14583 24.6719 7.625 24.0156C6.10417 23.3594 4.78125 22.4688 3.65625 21.3438C2.53125 20.2188 1.64062 18.8958 0.984375 17.375C0.328125 15.8542 0 14.2292 0 12.5C0 10.7708 0.328125 9.14583 0.984375 7.625C1.64062 6.10417 2.53125 4.78125 3.65625 3.65625C4.78125 2.53125 6.10417 1.64062 7.625 0.984375C9.14583 0.328125 10.7708 0 12.5 0C14.2292 0 15.8542 0.328125 17.375 0.984375C18.8958 1.64062 20.2188 2.53125 21.3438 3.65625C22.4688 4.78125 23.3594 6.10417 24.0156 7.625C24.6719 9.14583 25 10.7708 25 12.5C25 14.2292 24.6719 15.8542 24.0156 17.375C23.3594 18.8958 22.4688 20.2188 21.3438 21.3438C20.2188 22.4688 18.8958 23.3594 17.375 24.0156C15.8542 24.6719 14.2292 25 12.5 25Z" fill="#94A3B8"/>
                                </svg>
                                <span class="text-xs text-center font-[Poppins] text-[#475569]">Other / Custom</span>
                            </button>
                        </div>
                        <input id="custom_category" name="custom_category" type="hidden" value="{{ $customCategoryOld }}">
                        <div class="hidden">
                            <input id="bm_physical" type="checkbox" name="business_models[]" value="Physical Goods" @checked(in_array('Physical Goods', $businessModelOld, true))>
                            <input id="bm_digital" type="checkbox" name="business_models[]" value="Digital Products" @checked(in_array('Digital Products', $businessModelOld, true))>
                            <input id="bm_service" type="checkbox" name="business_models[]" value="Services" @checked(in_array('Services', $businessModelOld, true))>
                            <input id="bm_subscription" type="checkbox" name="business_models[]" value="Subscriptions" @checked(in_array('Subscriptions', $businessModelOld, true))>
                            <input id="bm_virtual" type="checkbox" name="business_models[]" value="Memberships" @checked(in_array('Memberships', $businessModelOld, true))>
                        </div>
                    </div>

                    <div class="flex justify-between items-center pt-6 border-t border-[#E2E8F0]">
                        <a href="{{ route('store-management') }}" class="text-sm font-semibold text-[#64748B] hover:text-[#334155] transition">Skip for now</a>
                        <button type="submit" class="inline-flex h-12 items-center justify-center whitespace-nowrap bg-[#0052CC] text-white font-bold px-6 rounded-lg shadow-lg shadow-[#0052CC]/20 hover:bg-[#0042a3] transition gap-2 min-w-[144px]">
                            <span>Save & Continue</span>
                            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M10.1458 7.5H0V5.83333H10.1458L5.47917 1.16667L6.66667 0L13.3333 6.66667L6.66667 13.3333L5.47917 12.1667L10.1458 7.5Z" fill="white"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <div id="customCategoryModal" class="fixed inset-0 z-50 hidden">
        <iframe
            id="customCategoryModalFrame"
            src="{{ route('AddCustomCategoryOverlay', ['reset' => 1]) }}"
            class="h-full w-full border-0 bg-transparent"
            title="Custom Category"
        ></iframe>
    </div>

    <div class="text-center text-xs text-[#94A3B8] py-4">&copy; 2024 BaaS Core &middot; Onboarding flow</div>
    <script>
        (() => {
            const categoryInput = document.getElementById('categoryInput');
            const tiles = [...document.querySelectorAll('.category-tile')];
            const modelMap = {
                physical: document.getElementById('bm_physical'),
                digital: document.getElementById('bm_digital'),
                service: document.getElementById('bm_service'),
                subscription: document.getElementById('bm_subscription'),
                virtual: document.getElementById('bm_virtual'),
            };

            const setActiveTile = (active) => {
                tiles.forEach((tile) => {
                    const selected = tile.dataset.category === active;
                    tile.classList.toggle('bg-[#0052CC]/5', selected);
                    tile.classList.toggle('border-2', selected);
                    tile.classList.toggle('border-[#0052CC]', selected);
                    tile.classList.toggle('bg-white', !selected);
                    tile.classList.toggle('border', !selected);
                    tile.classList.toggle('border-[#E2E8F0]', !selected);
                });

                Object.entries(modelMap).forEach(([key, input]) => {
                    if (input) {
                        input.checked = key === active;
                    }
                });
            };

            tiles.forEach((tile) => {
                tile.addEventListener('click', () => {
                    const selected = tile.dataset.category;
                    categoryInput.value = selected;
                    setActiveTile(selected);
                });
            });

            if (categoryInput.value) {
                setActiveTile(categoryInput.value);
            } else {
                tiles.forEach((tile) => {
                    tile.classList.remove('bg-[#0052CC]/5', 'border-2', 'border-[#0052CC]');
                    tile.classList.add('bg-white', 'border', 'border-[#E2E8F0]');
                });
                Object.values(modelMap).forEach((input) => {
                    if (input) {
                        input.checked = false;
                    }
                });
            }

            const customCategoryModal = document.getElementById('customCategoryModal');
            const openCustomCategoryModal = document.getElementById('openCustomCategoryModal');

            const showCustomCategoryModal = () => {
                if (!customCategoryModal) {
                    return;
                }
                customCategoryModal.classList.remove('hidden');
                customCategoryModal.classList.add('flex');
            };

            const hideCustomCategoryModal = () => {
                if (!customCategoryModal) {
                    return;
                }
                customCategoryModal.classList.add('hidden');
                customCategoryModal.classList.remove('flex');
            };

            if (openCustomCategoryModal) {
                openCustomCategoryModal.addEventListener('click', showCustomCategoryModal);
            }
            if (customCategoryModal) {
                customCategoryModal.addEventListener('click', (event) => {
                    if (event.target === customCategoryModal) {
                        hideCustomCategoryModal();
                    }
                });
            }
        })();
    </script>
</body>
</html>
