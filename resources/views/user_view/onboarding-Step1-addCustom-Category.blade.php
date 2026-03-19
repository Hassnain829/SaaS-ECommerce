<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Categories - BaaS Core</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-transparent font-sans text-[#0F172A] antialiased" style="font-family: 'Inter', system-ui, sans-serif;">
    <div class="flex min-h-screen items-center justify-center bg-slate-700/80 px-5 py-7 backdrop-blur-sm">
        <div class="w-full max-w-4xl overflow-hidden rounded-2xl border border-[#E2E8F0] bg-white shadow-2xl">
            <div class="border-b border-[#F1F5F9] px-6 pb-4 pt-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-[26px] font-medium leading-tight text-[#0F172A]" style="font-family: 'Poppins', sans-serif;">Browse All Categories</h2>
                        <p class="mt-2 text-sm text-[#94A3B8]">Select the categories that best describe your business to tailor your dashboard.</p>
                    </div>
                    <a href="{{ route('onboarding-StoreDetails-1') }}" target="_top" class="text-[#94A3B8] transition hover:text-[#64748B]" aria-label="Close overlay">
                        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M6 6L18 18M18 6L6 18" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                        </svg>
                    </a>
                </div>

                <div class="relative mt-4">
                    <svg class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-[#94A3B8]" width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M21 21L16.65 16.65M18 10.5C18 14.6421 14.6421 18 10.5 18C6.35786 18 3 14.6421 3 10.5C3 6.35786 6.35786 3 10.5 3C14.6421 3 18 6.35786 18 10.5Z" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
                    </svg>
                    <input
                        id="categorySearchInput"
                        type="text"
                        placeholder="Search categories..."
                        class="h-11 w-full rounded-[10px] border border-[#CBD5E1] bg-white pl-11 pr-4 text-sm text-[#0F172A] outline-none transition placeholder:text-[#94A3B8] focus:border-[#0052CC] focus:ring-2 focus:ring-[#0052CC]/15"
                    >
                </div>
            </div>

            @if ($errors->any())
                <div class="mx-6 mt-4 rounded-lg border border-[#F4B8BF] bg-[#FFF1F2] px-4 py-3 text-sm text-[#B42318]">
                    <ul class="list-disc ml-5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @php
                $isResetOverlay = request()->boolean('reset');
                $overlaySelectedCategory = $isResetOverlay ? '' : old('selected_category', $storeDraft['category'] ?? '');
                $overlayBusinessModels = $isResetOverlay ? [] : old('business_models', $storeDraft['business_models'] ?? []);
                $overlayCustomCategory = $isResetOverlay ? '' : old('custom_category', $storeDraft['custom_category'] ?? '');
            @endphp

            <form id="custom-category-form" action="{{ route('AddCustomCategoryOverlay.store') }}" method="POST" target="_top">
                @csrf
                <input type="hidden" id="selectedCategoryInput" name="selected_category" value="{{ $overlaySelectedCategory }}">
                <input type="hidden" id="customCategoryHidden" name="custom_category" value="{{ $overlayCustomCategory }}">
                <div id="businessModelsContainer" class="hidden"></div>

            <div class="max-h-[36rem] space-y-8 overflow-y-auto px-6 py-4 md:max-h-[60vh]">
                <section>
                    <h3 class="mb-4 px-1 text-xs font-semibold uppercase tracking-[0.06em] text-[#94A3B8]">Retail</h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="physical" data-model="Physical Goods">
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="20" height="16" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M1 16C0.716667 16 0.479167 15.9042 0.2875 15.7125C0.0958333 15.5208 0 15.2833 0 15C0 14.8333 0.0333333 14.6792 0.1 14.5375C0.166667 14.3958 0.266667 14.2833 0.4 14.2L9 7.75V6C9 5.71667 9.1 5.47917 9.3 5.2875C9.5 5.09583 9.74167 5 10.025 5C10.4417 5 10.7917 4.85 11.075 4.55C11.3583 4.25 11.5 3.89167 11.5 3.475C11.5 3.05833 11.3542 2.70833 11.0625 2.425C10.7708 2.14167 10.4167 2 10 2C9.58333 2 9.22917 2.14583 8.9375 2.4375C8.64583 2.72917 8.5 3.08333 8.5 3.5H6.5C6.5 2.53333 6.84167 1.70833 7.525 1.025C8.20833 0.341667 9.03333 0 10 0C10.9667 0 11.7917 0.3375 12.475 1.0125C13.1583 1.6875 13.5 2.50833 13.5 3.475C13.5 4.25833 13.2708 4.95833 12.8125 5.575C12.3542 6.19167 11.75 6.61667 11 6.85V7.75L19.6 14.2C19.7333 14.2833 19.8333 14.3958 19.9 14.5375C19.9667 14.6792 20 14.8333 20 15C20 15.2833 19.9042 15.5208 19.7125 15.7125C19.5208 15.9042 19.2833 16 19 16H1ZM4 14H16L10 9.5L4 14Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Fashion</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">Clothing &amp; Shoes</div>
                            </div>
                        </div>

                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="physical" data-model="Electronics">
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="20" height="16" viewBox="0 0 20 16" fill="none" aria-hidden="true">
                                    <path d="M0 16V14H10V16H0ZM3 13C2.45 13 1.97917 12.8042 1.5875 12.4125C1.19583 12.0208 1 11.55 1 11V2C1 1.45 1.19583 0.979167 1.5875 0.5875C1.97917 0.195833 2.45 0 3 0H17C17.55 0 18.0208 0.195833 18.4125 0.5875C18.8042 0.979167 19 1.45 19 2H3V11H10V13H3ZM18 14V6H14V14H18ZM13.5 16C13.0833 16 12.7292 15.8542 12.4375 15.5625C12.1458 15.2708 12 14.9167 12 14.5V5.5C12 5.08333 12.1458 4.72917 12.4375 4.4375C12.7292 4.14583 13.0833 4 13.5 4H18.5C18.9167 4 19.2708 4.14583 19.5625 4.4375C19.8542 4.72917 20 5.08333 20 5.5V14.5C20 14.9167 19.8542 15.2708 19.5625 15.5625C19.2708 15.8542 18.9167 16 18.5 16H13.5ZM16 8.5C16.2167 8.5 16.3958 8.425 16.5375 8.275C16.6792 8.125 16.75 7.95 16.75 7.75C16.75 7.53333 16.6792 7.35417 16.5375 7.2125C16.3958 7.07083 16.2167 7 16 7C15.8 7 15.625 7.07083 15.475 7.2125C15.325 7.35417 15.25 7.53333 15.25 7.75C15.25 7.95 15.325 8.125 15.475 8.275C15.625 8.425 15.8 8.5 16 8.5Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Electronics</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">Tech &amp; Gadgets</div>
                            </div>
                        </div>

                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="physical" data-model="Home">
                            <span class="selection-badge absolute right-3 top-3 inline-flex h-4 w-4 items-center justify-center rounded-full bg-[#0052CC] text-[10px] font-bold leading-none text-white" style="display:none;">
                                <svg width="10" height="10" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M3 8L6.2 11.2L13 4.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="16" height="18" viewBox="0 0 16 18" fill="none" aria-hidden="true">
                                    <path d="M2 16H5V10H11V16H14V7L8 2.5L2 7V16ZM0 18V6L8 0L16 6V18H9V12H7V18H0Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Home</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">Furniture &amp; Decor</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="mb-4 px-1 text-xs font-semibold uppercase tracking-[0.06em] text-[#94A3B8]">Service &amp; Digital</h3>
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="subscription" data-model="Subscriptions">
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M2 20C1.45 20 0.979167 19.8042 0.5875 19.4125C0.195833 19.0208 0 18.55 0 18V8C0 7.45 0.195833 6.97917 0.5875 6.5875C0.979167 6.19583 1.45 6 2 6H18C18.55 6 19.0208 6.19583 19.4125 6.5875C19.8042 6.97917 20 7.45 20 8V18C20 18.55 19.8042 19.0208 19.4125 19.4125C19.0208 19.8042 18.55 20 18 20H2ZM2 18H18V8H2V18ZM8 17L14 13L8 9V17ZM2 5V3H18V5H2ZM5 2V0H15V2H5ZM2 18V8V18Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Subscriptions</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">SaaS &amp; Content</div>
                            </div>
                        </div>

                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="virtual" data-model="Memberships">
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="24" height="22" viewBox="0 0 24 22" fill="none" aria-hidden="true">
                                    <path d="M0 22V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H9C9.55 0 10.0208 0.195833 10.4125 0.5875C10.8042 0.979167 11 1.45 11 2V22H9V20H2V22H0ZM15.5 22V15.4L17.65 13.35L17.125 10.75C16.475 11.5 15.6875 12.0625 14.7625 12.4375C13.8375 12.8125 12.9167 13 12 13V11C12.8 11 13.575 10.8083 14.325 10.425C15.075 10.0417 15.6917 9.45833 16.175 8.675L16.925 7.45C17.175 7.03333 17.5417 6.75 18.025 6.6C18.5083 6.45 18.9667 6.46667 19.4 6.65L24 8.6V13.5H22V9.925L20.575 9.325L23 22H20.95L19.425 14.85L17.5 16.65V22H15.5ZM2 18H9V2H2V18ZM4 12L7.5 10L4 8V12ZM17 6C16.45 6 15.9792 5.80417 15.5875 5.4125C15.1958 5.02083 15 4.55 15 4C15 3.45 15.1958 2.97917 15.5875 2.5875C15.9792 2.19583 16.45 2 17 2C17.55 2 18.0208 2.19583 18.4125 2.5875C18.8042 2.97917 19 3.45 19 4C19 4.55 18.8042 5.02083 18.4125 5.4125C18.0208 5.80417 17.55 6 17 6Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Virtual Products</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">Assets &amp; Files</div>
                            </div>
                        </div>

                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="service" data-model="Services">
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="24" height="12" viewBox="0 0 24 12" fill="none" aria-hidden="true">
                                    <path d="M0 12V10.425C0 9.70833 0.366667 9.125 1.1 8.675C1.83333 8.225 2.8 8 4 8C4.21667 8 4.425 8.00417 4.625 8.0125C4.825 8.02083 5.01667 8.04167 5.2 8.075C4.96667 8.425 4.79167 8.79167 4.675 9.175C4.55833 9.55833 4.5 9.95833 4.5 10.375V12H0ZM6 12V10.375C6 9.84167 6.14583 9.35417 6.4375 8.9125C6.72917 8.47083 7.14167 8.08333 7.675 7.75C8.20833 7.41667 8.84583 7.16667 9.5875 7C10.3292 6.83333 11.1333 6.75 12 6.75C12.8833 6.75 13.6958 6.83333 14.4375 7C15.1792 7.16667 15.8167 7.41667 16.35 7.75C16.8833 8.08333 17.2917 8.47083 17.575 8.9125C17.8583 9.35417 18 9.84167 18 10.375V12H6ZM19.5 12V10.375C19.5 9.94167 19.4458 9.53333 19.3375 9.15C19.2292 8.76667 19.0667 8.40833 18.85 8.075C19.0333 8.04167 19.2208 8.02083 19.4125 8.0125C19.6042 8.00417 19.8 8 20 8C21.2 8 22.1667 8.22083 22.9 8.6625C23.6333 9.10417 24 9.69167 24 10.425V12H19.5ZM8.125 10H15.9C15.7333 9.66667 15.2708 9.375 14.5125 9.125C13.7542 8.875 12.9167 8.75 12 8.75C11.0833 8.75 10.2458 8.875 9.4875 9.125C8.72917 9.375 8.275 9.66667 8.125 10ZM4 7C3.45 7 2.97917 6.80417 2.5875 6.4125C2.19583 6.02083 2 5.55 2 5C2 4.43333 2.19583 3.95833 2.5875 3.575C2.97917 3.19167 3.45 3 4 3C4.56667 3 5.04167 3.19167 5.425 3.575C5.80833 3.95833 6 4.43333 6 5C6 5.55 5.80833 6.02083 5.425 6.4125C5.04167 6.80417 4.56667 7 4 7ZM20 7C19.45 7 18.9792 6.80417 18.5875 6.4125C18.1958 6.02083 18 5.55 18 5C18 4.43333 18.1958 3.95833 18.5875 3.575C18.9792 3.19167 19.45 3 20 3C20.5667 3 21.0417 3.19167 21.425 3.575C21.8083 3.95833 22 4.43333 22 5C22 5.55 21.8083 6.02083 21.425 6.4125C21.0417 6.80417 20.5667 7 20 7ZM12 6C11.1667 6 10.4583 5.70833 9.875 5.125C9.29167 4.54167 9 3.83333 9 3C9 2.15 9.29167 1.4375 9.875 0.8625C10.4583 0.2875 11.1667 0 12 0C12.85 0 13.5625 0.2875 14.1375 0.8625C14.7125 1.4375 15 2.15 15 3C15 3.83333 14.7125 4.54167 14.1375 5.125C13.5625 5.70833 12.85 6 12 6ZM12 4C12.2833 4 12.5208 3.90417 12.7125 3.7125C12.9042 3.52083 13 3.28333 13 3C13 2.71667 12.9042 2.47917 12.7125 2.2875C12.5208 2.09583 12.2833 2 12 2C11.7167 2 11.4792 2.09583 11.2875 2.2875C11.0958 2.47917 11 2.71667 11 3C11 3.28333 11.0958 3.52083 11.2875 3.7125C11.4792 3.90417 11.7167 4 12 4Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Consultations</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">Services &amp; Advice</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section>
                    <h3 class="mb-4 px-1 text-xs font-semibold uppercase tracking-[0.06em] text-[#94A3B8]">Niche</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="physical" data-model="Health & Wellness">
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="20" height="20" viewBox="0 0 20 20" fill="none" aria-hidden="true">
                                    <path d="M10 20C8.78333 19.85 7.575 19.5208 6.375 19.0125C5.175 18.5042 4.10417 17.775 3.1625 16.825C2.22083 15.875 1.45833 14.675 0.875 13.225C0.291667 11.775 0 10.0333 0 8V7H1C1.85 7 2.725 7.10833 3.625 7.325C4.525 7.54167 5.36667 7.86667 6.15 8.3C6.35 6.86667 6.80417 5.39583 7.5125 3.8875C8.22083 2.37917 9.05 1.08333 10 0C10.95 1.08333 11.7792 2.37917 12.4875 3.8875C13.1958 5.39583 13.65 6.86667 13.85 8.3C14.6333 7.86667 15.475 7.54167 16.375 7.325C17.275 7.10833 18.15 7 19 7H20V8C20 10.0333 19.7083 11.775 19.125 13.225C18.5417 14.675 17.7792 15.875 16.8375 16.825C15.8958 17.775 14.8292 18.5042 13.6375 19.0125C12.4458 19.5208 11.2333 19.85 10 20ZM9.95 17.95C9.76667 15.1833 8.94583 13.0917 7.4875 11.675C6.02917 10.2583 4.21667 9.38333 2.05 9.05C2.23333 11.9 3.07917 14.025 4.5875 15.425C6.09583 16.825 7.88333 17.6667 9.95 17.95ZM10 11.6C10.25 11.2333 10.5542 10.8542 10.9125 10.4625C11.2708 10.0708 11.6167 9.73333 11.95 9.45C11.9167 8.5 11.7292 7.50833 11.3875 6.475C11.0458 5.44167 10.5833 4.43333 10 3.45C9.41667 4.43333 8.95417 5.44167 8.6125 6.475C8.27083 7.50833 8.08333 8.5 8.05 9.45C8.38333 9.73333 8.73333 10.0708 9.1 10.4625C9.46667 10.8542 9.76667 11.2333 10 11.6ZM11.95 17.5C12.5667 17.3 13.2083 17.0083 13.875 16.625C14.5417 16.2417 15.1625 15.7208 15.7375 15.0625C16.3125 14.4042 16.8042 13.5833 17.2125 12.6C17.6208 11.6167 17.8667 10.4333 17.95 9.05C16.3833 9.28333 15.0083 9.80417 13.825 10.6125C12.6417 11.4208 11.7333 12.45 11.1 13.7C11.3 14.2333 11.4708 14.8167 11.6125 15.45C11.7542 16.0833 11.8667 16.7667 11.95 17.5Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Health &amp; Wellness</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">Supplements &amp; Gear</div>
                            </div>
                        </div>

                        <div class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="physical" data-model="Toys & Games">
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="21" height="16" viewBox="0 0 21 16" fill="none" aria-hidden="true">
                                    <path d="M5.975 16C5.225 16 4.57083 15.7625 4.0125 15.2875C3.45417 14.8125 3.125 14.2 3.025 13.45C2.39167 13.1167 1.89167 12.6417 1.525 12.025C1.15833 11.4083 0.975 10.7333 0.975 10C0.975 9.11667 1.22917 8.32917 1.7375 7.6375C2.24583 6.94583 2.925 6.46667 3.775 6.2L1.975 4.4L1.675 4.7C1.49167 4.88333 1.25833 4.975 0.975 4.975C0.691667 4.975 0.458333 4.88333 0.275 4.7C0.0916667 4.51667 0 4.28333 0 4C0 3.71667 0.0916667 3.48333 0.275 3.3L2.275 1.3C2.45833 1.11667 2.69167 1.025 2.975 1.025C3.25833 1.025 3.49167 1.11667 3.675 1.3C3.85833 1.48333 3.95 1.71667 3.95 2C3.95 2.28333 3.85833 2.51667 3.675 2.7L3.375 3L4.775 4.4L5.575 2.05C5.775 1.43333 6.1375 0.9375 6.6625 0.5625C7.1875 0.1875 7.775 0 8.425 0H13.525C14.175 0 14.7625 0.1875 15.2875 0.5625C15.8125 0.9375 16.175 1.43333 16.375 2.05L17.725 6.1C18.675 6.28333 19.4542 6.74167 20.0625 7.475C20.6708 8.20833 20.975 9.05 20.975 10C20.975 10.7333 20.7917 11.4083 20.425 12.025C20.0583 12.6417 19.5583 13.1167 18.925 13.45C18.825 14.2 18.4958 14.8125 17.9375 15.2875C17.3792 15.7625 16.725 16 15.975 16C15.3417 16 14.7708 15.8167 14.2625 15.45C13.7542 15.0833 13.3917 14.6 13.175 14H8.775C8.55833 14.6 8.19583 15.0833 7.6875 15.45C7.17917 15.8167 6.60833 16 5.975 16ZM6.375 6H9.975V2H8.425C8.20833 2 8.01667 2.0625 7.85 2.1875C7.68333 2.3125 7.55833 2.48333 7.475 2.7L6.375 6ZM11.975 6H15.575L14.475 2.7C14.3917 2.48333 14.2667 2.3125 14.1 2.1875C13.9333 2.0625 13.7417 2 13.525 2H11.975V6ZM8.775 12H13.175C13.3917 11.4 13.7542 10.9167 14.2625 10.55C14.7708 10.1833 15.3417 10 15.975 10C16.475 10 16.9417 10.1167 17.375 10.35C17.8083 10.5833 18.175 10.9 18.475 11.3C18.625 11.1167 18.7458 10.9125 18.8375 10.6875C18.9292 10.4625 18.975 10.2333 18.975 10C18.975 9.45 18.7792 8.97917 18.3875 8.5875C17.9958 8.19583 17.525 8 16.975 8H4.975C4.425 8 3.95417 8.19583 3.5625 8.5875C3.17083 8.97917 2.975 9.45 2.975 10C2.975 10.2333 3.02083 10.4625 3.1125 10.6875C3.20417 10.9125 3.325 11.1167 3.475 11.3C3.775 10.9 4.14167 10.5833 4.575 10.35C5.00833 10.1167 5.475 10 5.975 10C6.60833 10 7.17917 10.1833 7.6875 10.55C8.19583 10.9167 8.55833 11.4 8.775 12ZM5.975 14C6.25833 14 6.49583 13.9042 6.6875 13.7125C6.87917 13.5208 6.975 13.2833 6.975 13C6.975 12.7167 6.87917 12.4792 6.6875 12.2875C6.49583 12.0958 6.25833 12 5.975 12C5.69167 12 5.45417 12.0958 5.2625 12.2875C5.07083 12.4792 4.975 12.7167 4.975 13C4.975 13.2833 5.07083 13.5208 5.2625 13.7125C5.45417 13.9042 5.69167 14 5.975 14ZM15.975 14C16.2583 14 16.4958 13.9042 16.6875 13.7125C16.8792 13.5208 16.975 13.2833 16.975 13C16.975 12.7167 16.8792 12.4792 16.6875 12.2875C16.4958 12.0958 16.2583 12 15.975 12C15.6917 12 15.4542 12.0958 15.2625 12.2875C15.0708 12.4792 14.975 12.7167 14.975 13C14.975 13.2833 15.0708 13.5208 15.2625 13.7125C15.4542 13.9042 15.6917 14 15.975 14Z" fill="#0052CC"/>
                                </svg>
                            </div>
                            <div>
                                <div class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Toys &amp; Games</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">Entertainment</div>
                            </div>
                        </div>
                    </div>
                </section>
                <section id="customCategorySection" class="hidden">
                    <h3 class="mb-4 px-1 text-xs font-semibold uppercase tracking-[0.06em] text-[#94A3B8]">Custom</h3>
                    <div class="grid gap-4 md:grid-cols-2">
                        <div id="customCategoryCard" class="category-card relative flex min-h-[70px] items-start gap-3 rounded-xl bg-[#F8FAFC] px-4 py-3.5" data-category="custom" data-model="">
                            <span class="selection-badge absolute right-3 top-3 inline-flex h-4 w-4 items-center justify-center rounded-full bg-[#0052CC] text-[10px] font-bold leading-none text-white" style="display:none;">
                                <svg width="10" height="10" viewBox="0 0 16 16" fill="none" aria-hidden="true">
                                    <path d="M3 8L6.2 11.2L13 4.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                </svg>
                            </span>
                            <div class="flex h-[42px] w-[42px] shrink-0 items-center justify-center rounded-lg bg-[#EAF2FF] text-[#0052CC]">
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
                                    <path d="M8 10H0V8H8V0H10V8H18V10H10V18H8V10Z" fill="currentColor"/>
                                </svg>
                            </div>
                            <div>
                                <div id="customCategoryTitle" class="category-title text-[15px] font-semibold leading-[1.25] text-[#475569]">Custom</div>
                                <div class="category-subtitle mt-1 text-[13px] leading-[1.35] text-[#94A3B8]">User Added Category</div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[14px] bg-[#F8FAFC] px-6 py-5">
                    <div class="grid items-center gap-4 lg:grid-cols-3">
                    <div class="lg:col-span-2">
                        <h4 class="text-base font-semibold leading-[1.25] text-[#4A84DA]">Can't find your category?</h4>
                        <p class="mt-1 text-[13px] leading-[1.45] text-[#94A3B8]">Create a custom one that fits your unique business model.</p>
                    </div>
                    <div class="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:justify-end">
                        <input
                            id="customCategoryInput"
                            type="text"
                            placeholder="Create custom category..."
                            class="h-11 min-w-0 flex-1 rounded-xl border border-[#CBD5E1] bg-white px-4 text-sm text-[#0F172A] outline-none placeholder:text-[#94A3B8] focus:border-[#0052CC] focus:ring-2 focus:ring-[#0052CC]/15 lg:max-w-[230px]"
                        >
                        <button id="addCustomCategoryBtn" type="button" class="inline-flex h-12 min-w-20 items-center justify-center whitespace-nowrap rounded-xl bg-[#0052CC] px-[18px] text-sm font-bold leading-none text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042A3]">
                            + Add
                        </button>
                    </div>
                    </div>
                </section>
            </div>

            <div class="border-t border-[#F1F5F9] bg-[#F8FAFC] px-6 py-4">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <a href="{{ route('onboarding-StoreDetails-1') }}" target="_top" class="text-sm font-medium text-[#94A3B8] transition hover:text-[#64748B]">Cancel and close</a>
                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:gap-[22px]">
                        <span id="selectedCountText" class="text-sm text-[#94A3B8]">0 category selected</span>
                        <button type="submit" class="inline-flex h-[46px] min-w-[170px] items-center justify-center whitespace-nowrap rounded-xl bg-[#0052CC] px-6 text-sm font-bold text-white shadow-lg shadow-[#0052CC]/20 transition hover:bg-[#0042A3]">
                            Confirm Selection
                        </button>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>
    <script>
        (() => {
            const selectedCategoryInput = document.getElementById('selectedCategoryInput');
            const customCategoryInput = document.getElementById('customCategoryInput');
            const customCategoryHidden = document.getElementById('customCategoryHidden');
            const businessModelsContainer = document.getElementById('businessModelsContainer');
            const selectedCountText = document.getElementById('selectedCountText');
            const searchInput = document.getElementById('categorySearchInput');

            const selectedModels = new Set(@json($overlayBusinessModels));
            const cards = [...document.querySelectorAll('.category-card')];
            const customSection = document.getElementById('customCategorySection');
            const customCard = document.getElementById('customCategoryCard');
            const customCategoryTitle = document.getElementById('customCategoryTitle');

            const ensureBadge = (card) => {
                let badge = card.querySelector('.selection-badge');
                if (!badge) {
                    badge = document.createElement('span');
                    badge.className = 'selection-badge absolute right-3 top-3 inline-flex h-4 w-4 items-center justify-center rounded-full bg-[#0052CC] text-[10px] font-bold leading-none text-white';
                    badge.style.display = 'none';
                    badge.innerHTML = '<svg width="10" height="10" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M3 8L6.2 11.2L13 4.5" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
                    card.appendChild(badge);
                }
                return badge;
            };

            const syncBusinessModelInputs = () => {
                businessModelsContainer.innerHTML = '';
                selectedModels.forEach((model) => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'business_models[]';
                    input.value = model;
                    businessModelsContainer.appendChild(input);
                });
            };

            const refreshCount = () => {
                const hasCustom = customCategoryHidden.value.trim() ? 1 : 0;
                const total = selectedModels.size + hasCustom;
                selectedCountText.textContent = `${total} category selected`;
            };

            const applyCardStyle = (card, selected) => {
                const badge = ensureBadge(card);
                card.classList.toggle('ring-2', selected);
                card.classList.toggle('ring-[#0052CC]', selected);
                card.classList.toggle('bg-[#EAF2FF]', selected);
                badge.style.display = selected ? 'inline-flex' : 'none';
                const title = card.querySelector('.category-title');
                const subtitle = card.querySelector('.category-subtitle');
                if (title) {
                    title.style.color = selected ? '#4A84DA' : '#475569';
                }
                if (subtitle) {
                    subtitle.style.color = selected ? '#64748B' : '#94A3B8';
                }
            };

            const clearPredefinedSelections = () => {
                cards.forEach((card) => {
                    if (card !== customCard) {
                        const model = card.dataset.model;
                        if (model) {
                            selectedModels.delete(model);
                        }
                        applyCardStyle(card, false);
                    }
                });
            };

            cards.forEach((card) => {
                if (card === customCard) {
                    return;
                }
                const mapping = {
                    category: card.dataset.category,
                    model: card.dataset.model,
                };

                const initiallySelected = selectedModels.has(mapping.model);
                applyCardStyle(card, initiallySelected);

                card.style.cursor = 'pointer';
                card.addEventListener('click', () => {
                    if (selectedModels.has(mapping.model)) {
                        selectedModels.delete(mapping.model);
                        applyCardStyle(card, false);
                    } else {
                        selectedModels.add(mapping.model);
                        selectedCategoryInput.value = mapping.category;
                        applyCardStyle(card, true);
                    }
                    syncBusinessModelInputs();
                    refreshCount();
                });
            });

            document.getElementById('addCustomCategoryBtn').addEventListener('click', () => {
                const value = customCategoryInput.value.trim();
                if (!value) {
                    return;
                }
                clearPredefinedSelections();
                customCategoryHidden.value = value;
                customCategoryInput.value = value;
                customCategoryTitle.textContent = value;
                customCard.dataset.model = value;
                selectedCategoryInput.value = 'custom';
                customSection.classList.remove('hidden');
                applyCardStyle(customCard, true);
                refreshCount();
            });

            document.getElementById('custom-category-form').addEventListener('submit', () => {
                if (!customCategoryHidden.value.trim() && customCategoryInput.value.trim()) {
                    customCategoryHidden.value = customCategoryInput.value.trim();
                    customCategoryTitle.textContent = customCategoryHidden.value.trim();
                    customCard.dataset.model = customCategoryHidden.value.trim();
                    customSection.classList.remove('hidden');
                    clearPredefinedSelections();
                    selectedCategoryInput.value = 'custom';
                    applyCardStyle(customCard, true);
                }
                syncBusinessModelInputs();
            });

            searchInput.addEventListener('input', () => {
                const query = searchInput.value.trim().toLowerCase();
                cards.forEach((card) => {
                    const title = card.querySelector('.text-\\[15px\\]')?.textContent?.toLowerCase() ?? '';
                    const subtitle = card.querySelector('.text-\\[13px\\]')?.textContent?.toLowerCase() ?? '';
                    const visible = !query || title.includes(query) || subtitle.includes(query);
                    card.style.display = visible ? '' : 'none';
                });
            });

            syncBusinessModelInputs();
            if (customCategoryHidden.value.trim()) {
                customCategoryTitle.textContent = customCategoryHidden.value.trim();
                customCard.dataset.model = customCategoryHidden.value.trim();
                customSection.classList.remove('hidden');
                applyCardStyle(customCard, true);
            }
            refreshCount();
        })();
    </script>
</body>
</html>
