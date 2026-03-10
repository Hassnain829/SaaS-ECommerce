<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Onboarding · Add Product | BaaS Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#F5F7F8] antialiased text-[#0F172A] min-h-screen flex flex-col overflow-x-hidden font-[Inter]">

    <!-- main container (full width) -->
    <div class="w-full bg-[#F5F7F8] flex flex-col">

        <!-- header (identical to design) -->
        <header class="flex justify-between items-center px-4 sm:px-6 lg:px-16 py-3 bg-white border-b border-[#E2E8F0] w-full">
            <!-- left logo + name -->
            <div class="flex items-center gap-4">
                <div class="w-6 h-6">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M22 2H15.3333V8.6667H8.6667V15.3333H2V22H22V2Z" fill="#0052CC"/>
                    </svg>
                </div>
                <span class="text-lg font-bold text-[#0F172A]">BaaS Platform</span>
            </div>

            <!-- right: navigation + avatar -->
            <div class="flex items-center gap-3 sm:gap-6">
                <nav class="hidden md:flex items-center gap-4 lg:gap-8 text-sm">
                    <a href="{{ route('dashboard') }}" class="text-[#475569] font-medium">Dashboard</a>
                    <a href="{{ route('products') }}" class="text-[#0052CC] font-semibold">Products</a>
                    <a href="{{ route('orders') }}" class="text-[#475569] font-medium">Orders</a>
                    <a href="{{ route('generalSettings') }}" class="text-[#475569] font-medium">Settings</a>
                </nav>
                <div class="flex items-center gap-3 sm:gap-4">
                    <button class="hidden sm:inline-flex bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm">Save & Continue</button>
                    <!-- avatar placeholder (same as before) -->
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

        <!-- main content area (centered, width 1024px) -->
        <main class="px-6 md:px-10 py-8 max-w-[1024px] w-full mx-auto">

            <!-- breadcrumb -->
            <nav class="flex items-center gap-2 text-sm font-medium mb-6">
                <a href="{{ route('onboarding-StoreDetails-1') }}" class="text-[#0052CC] opacity-70 hover:opacity-100">Onboarding</a>
                <svg width="5" height="7" viewBox="0 0 5 7" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M2.68333 3.5L0 0.816667L0.816667 0L4.31667 3.5L0.816667 7L0 6.18333L2.68333 3.5Z" fill="#94A3B8"/>
                </svg>
                <span class="text-[#0F172A]">Add Product</span>
            </nav>

            <!-- progress: step 2 of 3 -->
            <div class="mb-8">
                <div class="flex justify-between items-center mb-2">
                    <span class="text-sm font-medium text-[#64748B] uppercase tracking-wider">Step 2 of 3</span>
                    <span class="text-xs text-[#64748B]">Setup Progress: 55% Complete</span>
                </div>
                <!-- progress bar -->
                <div class="w-full h-2 bg-[#E2E8F0] rounded-full overflow-hidden">
                    <div class="h-2 w-[55%] bg-[#0052CC] rounded-full"></div>
                </div>
                <div class="flex justify-end mt-1">
                    <span class="text-xs text-[#0052CC] font-medium">Next: Launch</span>
                </div>
            </div>

            <!-- page header with upload CSV -->
            <div class="flex justify-between items-start mb-8">
                <div>
                    <h1 class="text-3xl font-medium text-[#0F172A] font-[Poppins]">Add Product</h1>
                    <p class="text-base text-[#64748B] mt-1">Define your product basics and setup variations like size, color, or material.</p>
                </div>
                <button class="bg-[#E2E8F0] text-[#64748B] text-sm font-medium px-4 py-2 rounded border border-[#E2E8F0]">Upload CSV</button>
            </div>

            <!-- Basic Information card -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                <div class="flex items-center gap-2 mb-6">
                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M9 15H11V9H9V15ZM10 7C10.2833 7 10.5208 6.90417 10.7125 6.7125C10.9042 6.52083 11 6.28333 11 6C11 5.71667 10.9042 5.47917 10.7125 5.2875C10.5208 5.09583 10.2833 5 10 5C9.71667 5 9.47917 5.09583 9.2875 5.2875C9.09583 5.47917 9 5.71667 9 6C9 6.28333 9.09583 6.52083 9.2875 6.7125C9.47917 6.90417 9.71667 7 10 7ZM10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z" fill="#0052CC"/>
                    </svg>
                    <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Basic Information</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <!-- product name -->
                    <div>
                        <label class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Product Name</label>
                        <input type="text" placeholder="e.g. Premium Cotton T-Shirt" value="Premium Cotton T-Shirt"
                               class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>
                    <!-- base price with $ -->
                    <div>
                        <label class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Base Price</label>
                        <div class="relative">
                            <span class="absolute left-4 top-1/2 -translate-y-1/2 text-[#94A3B8]">$</span>
                            <input type="text" value="29.99"
                                   class="w-full pl-8 pr-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                        </div>
                    </div>
                </div>

                <!-- description -->
                <div>
                    <label class="block text-sm font-medium text-[#334155] mb-2 font-poppins">Description</label>
                    <textarea rows="3" placeholder="Describe your product's key features and benefits..." 
                              class="w-full px-4 py-3 border border-[#E2E8F0] rounded-lg text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">Soft, breathable cotton, pre-shrunk fabric, available in multiple colors.</textarea>
                </div>
            </div>

            <!-- Product Variations card -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                <div class="flex justify-between items-center mb-6">
                    <div class="flex items-center gap-2">
                        <svg width="18" height="20" viewBox="0 0 18 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M9 19.05L0 12.05L1.65 10.8L9 16.5L16.35 10.8L18 12.05L9 19.05ZM9 14L0 7L9 0L18 7L9 14ZM9 11.45L14.75 7L9 2.55L3.25 7L9 11.45Z" fill="#0052CC"/>
                        </svg>
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Product Variations</h2>
                    </div>
                    <a href="{{ route('onboarding_AddProduct_VariationsPopup') }}" 
   class="flex items-center gap-2 text-[#0052CC] text-sm font-medium">
    <svg width="17" height="17" viewBox="0 0 17 17" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M7.5 12.5H9.16667V9.16667H12.5V7.5H9.16667V4.16667H7.5V7.5H4.16667V9.16667H7.5V12.5ZM8.33333 16.6667C7.18056 16.6667 6.09722 16.4479 5.08333 16.0104C4.06944 15.5729 3.1875 14.9792 2.4375 14.2292C1.6875 13.4792 1.09375 12.5972 0.65625 11.5833C0.21875 10.5694 0 9.48611 0 8.33333C0 7.18056 0.21875 6.09722 0.65625 5.08333C1.09375 4.06944 1.6875 3.1875 2.4375 2.4375C3.1875 1.6875 4.06944 1.09375 5.08333 0.65625C6.09722 0.21875 7.18056 0 8.33333 0C9.48611 0 10.5694 0.21875 11.5833 0.65625C12.5972 1.09375 13.4792 1.6875 14.2292 2.4375C14.9792 3.1875 15.5729 4.06944 16.0104 5.08333C16.4479 6.09722 16.6667 7.18056 16.6667 8.33333C16.6667 9.48611 16.4479 10.5694 16.0104 11.5833C15.5729 12.5972 14.9792 13.4792 14.2292 14.2292C13.4792 14.9792 12.5972 15.5729 11.5833 16.0104C10.5694 16.4479 9.48611 16.6667 8.33333 16.6667Z" fill="#0052CC"/>
    </svg>
    Add Variation Type
</a>
                </div>

                <div class="space-y-4">
                    <!-- Variation 1: Size -->
                    <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                        <div class="flex justify-between items-center mb-3">
                            <div class="flex items-center gap-3">
                                <svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14C0 13.45 0.195833 12.9792 0.5875 12.5875C0.979167 12.1958 1.45 12 2 12C2.55 12 3.02083 12.1958 3.4125 12.5875C3.80417 12.9792 4 13.45 4 14C4 14.55 3.80417 15.0208 3.4125 15.4125C3.02083 15.8042 2.55 16 2 16ZM8 16C7.45 16 6.97917 15.8042 6.5875 15.4125C6.19583 15.0208 6 14.55 6 14C6 13.45 6.19583 12.9792 6.5875 12.5875C6.97917 12.1958 7.45 12 8 12C8.55 12 9.02083 12.1958 9.4125 12.5875C9.80417 12.9792 10 13.45 10 14C10 14.55 9.80417 15.0208 9.4125 15.4125C9.02083 15.8042 8.55 16 8 16ZM2 10C1.45 10 0.979167 9.80417 0.5875 9.4125C0.195833 9.02083 0 8.55 0 8C0 7.45 0.195833 6.97917 0.5875 6.5875C0.979167 6.19583 1.45 6 2 6C2.55 6 3.02083 6.19583 3.4125 6.5875C3.80417 6.97917 4 7.45 4 8C4 8.55 3.80417 9.02083 3.4125 9.4125C3.02083 9.80417 2.55 10 2 10ZM8 10C7.45 10 6.97917 9.80417 6.5875 9.4125C6.19583 9.02083 6 8.55 6 8C6 7.45 6.19583 6.97917 6.5875 6.5875C6.97917 6.19583 7.45 6 8 6C8.55 6 9.02083 6.19583 9.4125 6.5875C9.80417 6.97917 10 7.45 10 8C10 8.55 9.80417 9.02083 9.4125 9.4125C9.02083 9.80417 8.55 10 8 10ZM2 4C1.45 4 0.979167 3.80417 0.5875 3.4125C0.195833 3.02083 0 2.55 0 2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0C2.55 0 3.02083 0.195833 3.4125 0.5875C3.80417 0.979167 4 1.45 4 2C4 2.55 3.80417 3.02083 3.4125 3.4125C3.02083 3.80417 2.55 4 2 4ZM8 4C7.45 4 6.97917 3.80417 6.5875 3.4125C6.19583 3.02083 6 2.55 6 2C6 1.45 6.19583 0.979167 6.5875 0.5875C6.97917 0.195833 7.45 0 8 0C8.55 0 9.02083 0.195833 9.4125 0.5875C9.80417 0.979167 10 1.45 10 2C10 2.55 9.80417 3.02083 9.4125 3.4125C9.02083 3.80417 8.55 4 8 4Z" fill="#94A3B8"/>
                                </svg>
                                <span class="text-base text-[#0F172A] font-poppins">Variation 1: Size</span>
                            </div>
                            <button class="text-[#94A3B8]">
                                <svg width="14" height="15" viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.5 15C2.04167 15 1.64931 14.8368 1.32292 14.5104C0.996528 14.184 0.833333 13.7917 0.833333 13.3333V2.5H0V0.833333H4.16667V0H9.16667V0.833333H13.3333V2.5H12.5V13.3333C12.5 13.7917 12.3368 14.184 12.0104 14.5104C11.684 14.8368 11.2917 15 10.8333 15H2.5ZM10.8333 2.5H2.5V13.3333H10.8333V2.5ZM4.16667 11.6667H5.83333V4.16667H4.16667V11.6667ZM7.5 11.6667H9.16667V4.16667H7.5V11.6667ZM2.5 2.5V13.3333V2.5Z" fill="#94A3B8"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <!-- size chips -->
                            <div class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 flex items-center gap-2 shadow-sm">
                                <span class="text-sm font-medium">S</span>
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0.933333 9.33333L0 8.4L3.73333 4.66667L0 0.933333L0.933333 0L4.66667 3.73333L8.4 0L9.33333 0.933333L5.6 4.66667L9.33333 8.4L8.4 9.33333L4.66667 5.6L0.933333 9.33333Z" fill="#94A3B8"/>
                                </svg>
                            </div>
                            <div class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 flex items-center gap-2 shadow-sm">
                                <span class="text-sm font-medium">M</span>
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0.933333 9.33333L0 8.4L3.73333 4.66667L0 0.933333L0.933333 0L4.66667 3.73333L8.4 0L9.33333 0.933333L5.6 4.66667L9.33333 8.4L8.4 9.33333L4.66667 5.6L0.933333 9.33333Z" fill="#94A3B8"/>
                                </svg>
                            </div>
                            <div class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 flex items-center gap-2 shadow-sm">
                                <span class="text-sm font-medium">L</span>
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0.933333 9.33333L0 8.4L3.73333 4.66667L0 0.933333L0.933333 0L4.66667 3.73333L8.4 0L9.33333 0.933333L5.6 4.66667L9.33333 8.4L8.4 9.33333L4.66667 5.6L0.933333 9.33333Z" fill="#94A3B8"/>
                                </svg>
                            </div>
                            <!-- add option -->
                            <div class="border-2 border-dashed border-[#CBD5E1] rounded-lg px-3 py-1.5 text-sm text-[#6B7280]">Add option...</div>
                        </div>
                    </div>

                    <!-- Variation 2: Color -->
                    <div class="bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl p-5">
                        <div class="flex justify-between items-center mb-3">
                            <div class="flex items-center gap-3">
                                <svg width="10" height="16" viewBox="0 0 10 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2 16C1.45 16 0.979167 15.8042 0.5875 15.4125C0.195833 15.0208 0 14.55 0 14C0 13.45 0.195833 12.9792 0.5875 12.5875C0.979167 12.1958 1.45 12 2 12C2.55 12 3.02083 12.1958 3.4125 12.5875C3.80417 12.9792 4 13.45 4 14C4 14.55 3.80417 15.0208 3.4125 15.4125C3.02083 15.8042 2.55 16 2 16ZM8 16C7.45 16 6.97917 15.8042 6.5875 15.4125C6.19583 15.0208 6 14.55 6 14C6 13.45 6.19583 12.9792 6.5875 12.5875C6.97917 12.1958 7.45 12 8 12C8.55 12 9.02083 12.1958 9.4125 12.5875C9.80417 12.9792 10 13.45 10 14C10 14.55 9.80417 15.0208 9.4125 15.4125C9.02083 15.8042 8.55 16 8 16ZM2 10C1.45 10 0.979167 9.80417 0.5875 9.4125C0.195833 9.02083 0 8.55 0 8C0 7.45 0.195833 6.97917 0.5875 6.5875C0.979167 6.19583 1.45 6 2 6C2.55 6 3.02083 6.19583 3.4125 6.5875C3.80417 6.97917 4 7.45 4 8C4 8.55 3.80417 9.02083 3.4125 9.4125C3.02083 9.80417 2.55 10 2 10ZM8 10C7.45 10 6.97917 9.80417 6.5875 9.4125C6.19583 9.02083 6 8.55 6 8C6 7.45 6.19583 6.97917 6.5875 6.5875C6.97917 6.19583 7.45 6 8 6C8.55 6 9.02083 6.19583 9.4125 6.5875C9.80417 6.97917 10 7.45 10 8C10 8.55 9.80417 9.02083 9.4125 9.4125C9.02083 9.80417 8.55 10 8 10ZM2 4C1.45 4 0.979167 3.80417 0.5875 3.4125C0.195833 3.02083 0 2.55 0 2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0C2.55 0 3.02083 0.195833 3.4125 0.5875C3.80417 0.979167 4 1.45 4 2C4 2.55 3.80417 3.02083 3.4125 3.4125C3.02083 3.80417 2.55 4 2 4ZM8 4C7.45 4 6.97917 3.80417 6.5875 3.4125C6.19583 3.02083 6 2.55 6 2C6 1.45 6.19583 0.979167 6.5875 0.5875C6.97917 0.195833 7.45 0 8 0C8.55 0 9.02083 0.195833 9.4125 0.5875C9.80417 0.979167 10 1.45 10 2C10 2.55 9.80417 3.02083 9.4125 3.4125C9.02083 3.80417 8.55 4 8 4Z" fill="#94A3B8"/>
                                </svg>
                                <span class="text-base text-[#0F172A] font-poppins">Variation 2: Color</span>
                            </div>
                            <button class="text-[#94A3B8]">
                                <svg width="14" height="15" viewBox="0 0 14 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M2.5 15C2.04167 15 1.64931 14.8368 1.32292 14.5104C0.996528 14.184 0.833333 13.7917 0.833333 13.3333V2.5H0V0.833333H4.16667V0H9.16667V0.833333H13.3333V2.5H12.5V13.3333C12.5 13.7917 12.3368 14.184 12.0104 14.5104C11.684 14.8368 11.2917 15 10.8333 15H2.5ZM10.8333 2.5H2.5V13.3333H10.8333V2.5ZM4.16667 11.6667H5.83333V4.16667H4.16667V11.6667ZM7.5 11.6667H9.16667V4.16667H7.5V11.6667ZM2.5 2.5V13.3333V2.5Z" fill="#94A3B8"/>
                                </svg>
                            </button>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <!-- color chips with dot -->
                            <div class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 flex items-center gap-2 shadow-sm">
                                <span class="w-3 h-3 bg-[#2563EB] rounded-full"></span>
                                <span class="text-sm font-medium">Blue</span>
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0.933333 9.33333L0 8.4L3.73333 4.66667L0 0.933333L0.933333 0L4.66667 3.73333L8.4 0L9.33333 0.933333L5.6 4.66667L9.33333 8.4L8.4 9.33333L4.66667 5.6L0.933333 9.33333Z" fill="#94A3B8"/>
                                </svg>
                            </div>
                            <div class="bg-white border border-[#E2E8F0] rounded-lg px-3 py-1.5 flex items-center gap-2 shadow-sm">
                                <span class="w-3 h-3 bg-[#0F172A] rounded-full"></span>
                                <span class="text-sm font-medium">Black</span>
                                <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M0.933333 9.33333L0 8.4L3.73333 4.66667L0 0.933333L0.933333 0L4.66667 3.73333L8.4 0L9.33333 0.933333L5.6 4.66667L9.33333 8.4L8.4 9.33333L4.66667 5.6L0.933333 9.33333Z" fill="#94A3B8"/>
                                </svg>
                            </div>
                            <!-- add option -->
                            <div class="border-2 border-dashed border-[#CBD5E1] rounded-lg px-3 py-1.5 text-sm text-[#6B7280]">Add option...</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Variants Matrix Preview card -->
            <div class="bg-white rounded-xl shadow-sm border border-[#E2E8F0] p-8 mb-6">
                <div class="flex flex-wrap justify-between items-center mb-6">
                    <div class="flex items-center gap-2">
                        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2ZM2 16H5.325V12.675H2V16ZM7.325 16H10.675V12.675H7.325V16ZM12.675 16H16V12.675H12.675V16ZM2 10.675H5.325V7.325H2V10.675ZM7.325 10.675H10.675V7.325H7.325V10.675ZM12.675 10.675H16V7.325H12.675V10.675ZM2 5.325H5.325V2H2V5.325ZM7.325 5.325H10.675V2H7.325V5.325ZM12.675 5.325H16V2H12.675V5.325Z" fill="#0052CC"/>
                        </svg>
                        <h2 class="text-xl font-medium text-[#0F172A] font-[Poppins]">Variants Matrix Preview</h2>
                    </div>
                    <div class="flex items-center gap-4">
                        <button class="px-3 py-1 border border-[#E2E8F0] rounded text-sm text-[#64748B] font-medium">Bulk Edit</button>
                        <span class="text-sm text-[#94A3B8]">6 combinations generated</span>
                    </div>
                </div>

                <!-- table -->
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="border-b border-[#F1F5F9]">
                            <tr>
                                <th class="text-left py-4 px-2 text-xs font-bold uppercase text-[#94A3B8]">Variant</th>
                                <th class="text-left py-4 px-2 text-xs font-bold uppercase text-[#94A3B8]">Image</th>
                                <th class="text-left py-4 px-2 text-xs font-bold uppercase text-[#94A3B8]">SKU</th>
                                <th class="text-left py-4 px-2 text-xs font-bold uppercase text-[#94A3B8]">Price ($)</th>
                                <th class="text-left py-4 px-2 text-xs font-bold uppercase text-[#94A3B8]">Stock</th>
                                <th class="text-left py-4 px-2 text-xs font-bold uppercase text-[#94A3B8]">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#F1F5F9]">
                            <!-- row 1 -->
                            <tr>
                                <td class="py-5 px-2 font-medium text-[#0F172A]">S / Blue</td>
                                <td class="py-5 px-2">
                                    <div class="w-10 h-10 bg-[#F1F5F9] border border-[#E2E8F0] rounded flex items-center justify-center">
                                        <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M1.5 13.5C1.0875 13.5 0.734375 13.3531 0.440625 13.0594C0.146875 12.7656 0 12.4125 0 12V1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0H7.5C7.5 0.2125 7.5 0.44375 7.5 0.69375C7.5 0.94375 7.5 1.2125 7.5 1.5H1.5V12H12V6C12.2875 6 12.5562 6 12.8062 6C13.0562 6 13.2875 6 13.5 6V12C13.5 12.4125 13.3531 12.7656 13.0594 13.0594C12.7656 13.3531 12.4125 13.5 12 13.5H1.5ZM2.25 10.5H11.25L8.4375 6.75L6.1875 9.75L4.5 7.5L2.25 10.5ZM10.5 4.5V3H9V1.5H10.5V0H12V1.5H13.5V3H12V4.5H10.5Z" fill="#94A3B8"/>
                                        </svg>
                                    </div>
                                </td>
                                <td class="py-5 px-2">
                                    <span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">TS-S-BLUE</span>
                                </td>
                                <td class="py-5 px-2">
                                    <span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">29.99</span>
                                </td>
                                <td class="py-5 px-2">
                                    <span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">50</span>
                                </td>
                                <td class="py-5 px-2">
                                    <button class="text-[#94A3B8]">
                                        <svg width="3" height="12" viewBox="0 0 3 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M1.5 12C1.0875 12 0.734375 11.8531 0.440625 11.5594C0.146875 11.2656 0 10.9125 0 10.5C0 10.0875 0.146875 9.73438 0.440625 9.44063C0.734375 9.14688 1.0875 9 1.5 9C1.9125 9 2.26562 9.14688 2.55938 9.44063C2.85313 9.73438 3 10.0875 3 10.5C3 10.9125 2.85313 11.2656 2.55938 11.5594C2.26562 11.8531 1.9125 12 1.5 12ZM1.5 7.5C1.0875 7.5 0.734375 7.35312 0.440625 7.05937C0.146875 6.76562 0 6.4125 0 6C0 5.5875 0.146875 5.23438 0.440625 4.94063C0.734375 4.64688 1.0875 4.5 1.5 4.5C1.9125 4.5 2.26562 4.64688 2.55938 4.94063C2.85313 5.23438 3 5.5875 3 6C3 6.4125 2.85313 6.76562 2.55938 7.05937C2.26562 7.35312 1.9125 7.5 1.5 7.5ZM1.5 3C1.0875 3 0.734375 2.85313 0.440625 2.55938C0.146875 2.26562 0 1.9125 0 1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0C1.9125 0 2.26562 0.146875 2.55938 0.440625C2.85313 0.734375 3 1.0875 3 1.5C3 1.9125 2.85313 2.26562 2.55938 2.55938C2.26562 2.85313 1.9125 3 1.5 3Z" fill="#94A3B8"/>
                                        </svg>
                                    </button>
                                </td>
                            </tr>
                            <!-- row 2 -->
                            <tr>
                                <td class="py-5 px-2 font-medium">M / Blue</td>
                                <td class="py-5 px-2"><div class="w-10 h-10 bg-[#F1F5F9] border border-[#E2E8F0] rounded flex items-center justify-center"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 13.5C1.0875 13.5 0.734375 13.3531 0.440625 13.0594C0.146875 12.7656 0 12.4125 0 12V1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0H7.5C7.5 0.2125 7.5 0.44375 7.5 0.69375C7.5 0.94375 7.5 1.2125 7.5 1.5H1.5V12H12V6C12.2875 6 12.5562 6 12.8062 6C13.0562 6 13.2875 6 13.5 6V12C13.5 12.4125 13.3531 12.7656 13.0594 13.0594C12.7656 13.3531 12.4125 13.5 12 13.5H1.5ZM2.25 10.5H11.25L8.4375 6.75L6.1875 9.75L4.5 7.5L2.25 10.5ZM10.5 4.5V3H9V1.5H10.5V0H12V1.5H13.5V3H12V4.5H10.5Z" fill="#94A3B8"/></svg></div></td>
                                <td class="py-5 px-2"><span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">TS-M-BLUE</span></td>
                                <td class="py-5 px-2"><span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">29.99</span></td>
                                <td class="py-5 px-2"><span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">50</span></td>
                                <td class="py-5 px-2"><button class="text-[#94A3B8]"><svg width="3" height="12" viewBox="0 0 3 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 12C1.0875 12 0.734375 11.8531 0.440625 11.5594C0.146875 11.2656 0 10.9125 0 10.5C0 10.0875 0.146875 9.73438 0.440625 9.44063C0.734375 9.14688 1.0875 9 1.5 9C1.9125 9 2.26562 9.14688 2.55938 9.44063C2.85313 9.73438 3 10.0875 3 10.5C3 10.9125 2.85313 11.2656 2.55938 11.5594C2.26562 11.8531 1.9125 12 1.5 12ZM1.5 7.5C1.0875 7.5 0.734375 7.35312 0.440625 7.05937C0.146875 6.76562 0 6.4125 0 6C0 5.5875 0.146875 5.23438 0.440625 4.94063C0.734375 4.64688 1.0875 4.5 1.5 4.5C1.9125 4.5 2.26562 4.64688 2.55938 4.94063C2.85313 5.23438 3 5.5875 3 6C3 6.4125 2.85313 6.76562 2.55938 7.05937C2.26562 7.35312 1.9125 7.5 1.5 7.5ZM1.5 3C1.0875 3 0.734375 2.85313 0.440625 2.55938C0.146875 2.26562 0 1.9125 0 1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0C1.9125 0 2.26562 0.146875 2.55938 0.440625C2.85313 0.734375 3 1.0875 3 1.5C3 1.9125 2.85313 2.26562 2.55938 2.55938C2.26562 2.85313 1.9125 3 1.5 3Z" fill="#94A3B8"/></svg></button></td>
                            </tr>
                            <!-- row 3 -->
                            <tr>
                                <td class="py-5 px-2 font-medium">L / Blue</td>
                                <td class="py-5 px-2"><div class="w-10 h-10 bg-[#F1F5F9] border border-[#E2E8F0] rounded flex items-center justify-center"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 13.5C1.0875 13.5 0.734375 13.3531 0.440625 13.0594C0.146875 12.7656 0 12.4125 0 12V1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0H7.5C7.5 0.2125 7.5 0.44375 7.5 0.69375C7.5 0.94375 7.5 1.2125 7.5 1.5H1.5V12H12V6C12.2875 6 12.5562 6 12.8062 6C13.0562 6 13.2875 6 13.5 6V12C13.5 12.4125 13.3531 12.7656 13.0594 13.0594C12.7656 13.3531 12.4125 13.5 12 13.5H1.5ZM2.25 10.5H11.25L8.4375 6.75L6.1875 9.75L4.5 7.5L2.25 10.5ZM10.5 4.5V3H9V1.5H10.5V0H12V1.5H13.5V3H12V4.5H10.5Z" fill="#94A3B8"/></svg></div></td>
                                <td class="py-5 px-2"><span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">TS-L-BLUE</span></td>
                                <td class="py-5 px-2"><span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">29.99</span></td>
                                <td class="py-5 px-2"><span class="px-2 py-1 border border-[#E2E8F0] rounded text-xs">50</span></td>
                                <td class="py-5 px-2"><button class="text-[#94A3B8]"><svg width="3" height="12" viewBox="0 0 3 12" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M1.5 12C1.0875 12 0.734375 11.8531 0.440625 11.5594C0.146875 11.2656 0 10.9125 0 10.5C0 10.0875 0.146875 9.73438 0.440625 9.44063C0.734375 9.14688 1.0875 9 1.5 9C1.9125 9 2.26562 9.14688 2.55938 9.44063C2.85313 9.73438 3 10.0875 3 10.5C3 10.9125 2.85313 11.2656 2.55938 11.5594C2.26562 11.8531 1.9125 12 1.5 12ZM1.5 7.5C1.0875 7.5 0.734375 7.35312 0.440625 7.05937C0.146875 6.76562 0 6.4125 0 6C0 5.5875 0.146875 5.23438 0.440625 4.94063C0.734375 4.64688 1.0875 4.5 1.5 4.5C1.9125 4.5 2.26562 4.64688 2.55938 4.94063C2.85313 5.23438 3 5.5875 3 6C3 6.4125 2.85313 6.76562 2.55938 7.05937C2.26562 7.35312 1.9125 7.5 1.5 7.5ZM1.5 3C1.0875 3 0.734375 2.85313 0.440625 2.55938C0.146875 2.26562 0 1.9125 0 1.5C0 1.0875 0.146875 0.734375 0.440625 0.440625C0.734375 0.146875 1.0875 0 1.5 0C1.9125 0 2.26562 0.146875 2.55938 0.440625C2.85313 0.734375 3 1.0875 3 1.5C3 1.9125 2.85313 2.26562 2.55938 2.55938C2.26562 2.85313 1.9125 3 1.5 3Z" fill="#94A3B8"/></svg></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- footer actions -->
            <div class="flex justify-between items-center pt-6 border-t border-[#E2E8F0]">
                <button class="flex items-center gap-2 text-[#475569] font-bold">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M3.825 9L9.425 14.6L8 16L0 8L8 0L9.425 1.4L3.825 7H16V9H3.825Z" fill="#475569"/>
                    </svg>
                    <span>Back to Basic Setup</span>
                </button>
                <div class="flex items-center gap-4">
                    <button class="text-[#475569] font-bold px-6 py-2">Skip for Now</button>
                    <button class="bg-[#0052CC] text-white font-bold px-8 py-3 rounded-lg shadow-lg shadow-[#0052CC]/20 flex items-center gap-2">
                        <span>Save & Continue</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.175 9H0V7H12.175L6.575 1.4L8 0L16 8L8 16L6.575 14.6L12.175 9Z" fill="white"/>
                        </svg>
                    </button>
                </div>
            </div>
        </main>
    </div>
</body>
</html>


