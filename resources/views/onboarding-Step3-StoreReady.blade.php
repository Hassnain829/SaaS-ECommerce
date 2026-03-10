<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Ready Â· BaaS Eâ€‘commerce</title>
    <!-- Tailwind + fonts (Inter & Poppins) -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col overflow-x-hidden font-[Inter] bg-[#F5F7F8]">

    <!-- main container (full width) -->
    <div class="w-full flex flex-col relative overflow-hidden">
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
                    <a href="{{ route('dashboard') }}" class="hidden sm:inline-flex bg-[#0052CC] text-white text-sm font-bold px-4 py-2 rounded-lg shadow-sm">Go to Dashboard</a>
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

        <!-- main content (centered, max-width 800px) -->
        <main class="flex-1 flex flex-col items-center px-4 py-12 md:py-16">
            <div class="w-full max-w-[800px] flex flex-col items-center">

                <!-- progress stepper (three steps, all complete) -->
                <div class="w-full max-w-[672px] mb-12 relative">
                    <!-- connecting line (behind circles) -->
                    <div class="absolute top-5 left-[calc(16.67%+20px)] right-[calc(16.67%+20px)] h-0.5 bg-[#0052CC] z-0"></div>
                    <div class="relative z-10 flex justify-between items-start w-full">
                        <!-- step 1: Create Store -->
                        <div class="flex flex-col items-center w-1/3">
                            <div class="w-10 h-10 bg-[#0052CC] rounded-full flex items-center justify-center">
                                <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Create Store</span>
                        </div>
                        <!-- step 2: Add Product -->
                        <div class="flex flex-col items-center w-1/3">
                            <div class="w-10 h-10 bg-[#0052CC] rounded-full flex items-center justify-center">
                                <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Add Product</span>
                        </div>
                        <!-- step 3: Launch -->
                        <div class="flex flex-col items-center w-1/3">
                            <div class="w-10 h-10 bg-[#0052CC] rounded-full flex items-center justify-center">
                                <svg width="14" height="11" viewBox="0 0 14 11" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4.75 10.0208L0 5.27083L1.1875 4.08333L4.75 7.64583L12.3958 0L13.5833 1.1875L4.75 10.0208Z" fill="white"/>
                                </svg>
                            </div>
                            <span class="mt-2 text-xs font-bold uppercase tracking-[0.6px] text-[#0052CC]">Launch</span>
                        </div>
                    </div>
                </div>

                <!-- celebration icon + text -->
                <div class="flex flex-col items-center text-center mb-10">
                    <div class="w-32 h-32 bg-[#0052CC]/10 rounded-full flex items-center justify-center">
                        <svg width="54" height="52" viewBox="0 0 54 52" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M0 51.25L12.5 16.25L35 38.75L0 51.25ZM8.25 43L25.875 36.75L14.5 25.375L8.25 43ZM31.375 27.625L28.75 25L42.75 11C44.0833 9.66667 45.6875 9 47.5625 9C49.4375 9 51.0417 9.66667 52.375 11L53.875 12.5L51.25 15.125L49.75 13.625C49.1667 13.0417 48.4375 12.75 47.5625 12.75C46.6875 12.75 45.9583 13.0417 45.375 13.625L31.375 27.625ZM21.375 17.625L18.75 15L20.25 13.5C20.8333 12.9167 21.125 12.2083 21.125 11.375C21.125 10.5417 20.8333 9.83333 20.25 9.25L18.625 7.625L21.25 5L22.875 6.625C24.2083 7.95833 24.875 9.54167 24.875 11.375C24.875 13.2083 24.2083 14.7917 22.875 16.125L21.375 17.625ZM26.375 22.625L23.75 20L32.75 11C33.3333 10.4167 33.625 9.6875 33.625 8.8125C33.625 7.9375 33.3333 7.20833 32.75 6.625L28.75 2.625L31.375 0L35.375 4C36.7083 5.33333 37.375 6.9375 37.375 8.8125C37.375 10.6875 36.7083 12.2917 35.375 13.625L26.375 22.625ZM36.375 32.625L33.75 30L37.75 26C39.0833 24.6667 40.6875 24 42.5625 24C44.4375 24 46.0417 24.6667 47.375 26L51.375 30L48.75 32.625L44.75 28.625C44.1667 28.0417 43.4375 27.75 42.5625 27.75C41.6875 27.75 40.9583 28.0417 40.375 28.625L36.375 32.625Z" fill="#0052CC"/>
                        </svg>
                    </div>
                    <h1 class="text-3xl md:text-4xl font-medium text-[#0F172A] font-[Poppins] mt-6">Congratulations, your store is ready!</h1>
                    <p class="text-lg text-[#475569] mt-2 max-w-[500px]">Your marketplace is live and ready for your first customers.</p>
                </div>

                <!-- store summary card (448px max) -->
                <div class="w-full max-w-[448px] mb-8">
                    <div class="bg-white rounded-xl shadow-xl border border-[#0052CC]/10 overflow-hidden">
                        <!-- header with icon -->
                        <div class="bg-[#0052CC]/5 px-6 py-4 border-b border-[#0052CC]/10">
                            <div class="flex items-center gap-2">
                                <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M4 14H6V9H4V14ZM12 14H14V4H12V14ZM8 14H10V11H8V14ZM8 9H10V7H8V9ZM2 18C1.45 18 0.979167 17.8042 0.5875 17.4125C0.195833 17.0208 0 16.55 0 16V2C0 1.45 0.195833 0.979167 0.5875 0.5875C0.979167 0.195833 1.45 0 2 0H16C16.55 0 17.0208 0.195833 17.4125 0.5875C17.8042 0.979167 18 1.45 18 2V16C18 16.55 17.8042 17.0208 17.4125 17.4125C17.0208 17.8042 16.55 18 16 18H2ZM2 16H16V2H2V16ZM2 2V16V2Z" fill="#0052CC"/>
                                </svg>
                                <span class="text-base font-medium text-[#1E293B] font-[Poppins]">Store Summary</span>
                            </div>
                        </div>
                        <!-- content -->
                        <div class="p-6 space-y-4">
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-[#64748B]">Store Name</span>
                                <span class="text-sm font-medium text-[#0F172A] text-right">Lumina Lifestyle Boutique</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-[#64748B]">First Product</span>
                                <span class="text-sm font-medium text-[#0F172A] text-right">Minimalist Ceramic Vase</span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-sm text-[#64748B]">Category</span>
                                <span class="text-sm font-medium text-[#0F172A] text-right">Home & Living</span>
                            </div>
                            <!-- status line with green icon + live url -->
                            <div class="flex items-start gap-3 pt-4 border-t border-[#F1F5F9]">
                                <div class="w-10 h-10 bg-[#DCFCE7] rounded-lg flex items-center justify-center shrink-0">
                                    <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path d="M10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20ZM9 17.95V16C8.45 16 7.97917 15.8042 7.5875 15.4125C7.19583 15.0208 7 14.55 7 14V13L2.2 8.2C2.15 8.5 2.10417 8.8 2.0625 9.1C2.02083 9.4 2 9.7 2 10C2 12.0167 2.6625 13.7833 3.9875 15.3C5.3125 16.8167 6.98333 17.7 9 17.95ZM15.9 15.4C16.5833 14.65 17.1042 13.8125 17.4625 12.8875C17.8208 11.9625 18 11 18 10C18 8.36667 17.5458 6.875 16.6375 5.525C15.7292 4.175 14.5167 3.2 13 2.6V3C13 3.55 12.8042 4.02083 12.4125 4.4125C12.0208 4.80417 11.55 5 11 5H9V7C9 7.28333 8.90417 7.52083 8.7125 7.7125C8.52083 7.90417 8.28333 8 8 8H6V10H12C12.2833 10 12.5208 10.0958 12.7125 10.2875C12.9042 10.4792 13 10.7167 13 11V14H14C14.4333 14 14.825 14.1292 15.175 14.3875C15.525 14.6458 15.7667 14.9833 15.9 15.4Z" fill="#16A34A"/>
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-xs font-bold uppercase tracking-wide text-[#64748B]">STATUS</span>
                                    <p class="text-sm font-bold text-[#16A34A]">Live at lumina-lifestyle.baas.com</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- action buttons (448px max) -->
                <div class="w-full max-w-[448px] flex flex-col gap-4">
                    <a href="{{ route('dashboard') }}" class="w-full bg-[#0052CC] text-white font-bold py-4 px-8 rounded-xl shadow-lg shadow-[#0052CC]/20 flex items-center justify-center gap-2 hover:bg-[#0042a3] transition">
                        <span>Go to Dashboard</span>
                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12.175 9H0V7H12.175L6.575 1.4L8 0L16 8L8 16L6.575 14.6L12.175 9Z" fill="white"/>
                        </svg>
                    </a>
                    <button class="w-full bg-white border border-[#E2E8F0] text-[#334155] font-semibold py-3 px-8 rounded-xl flex items-center justify-center gap-2 hover:bg-gray-50 transition">
                        <!-- play icon (triangle) -->
                        <svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M6.66667 14.1667L14.1667 10L6.66667 5.83333V14.1667ZM10 20C8.61667 20 7.31667 19.7375 6.1 19.2125C4.88333 18.6875 3.825 17.975 2.925 17.075C2.025 16.175 1.3125 15.1167 0.7875 13.9C0.2625 12.6833 0 11.3833 0 10C0 8.61667 0.2625 7.31667 0.7875 6.1C1.3125 4.88333 2.025 3.825 2.925 2.925C3.825 2.025 4.88333 1.3125 6.1 0.7875C7.31667 0.2625 8.61667 0 10 0C11.3833 0 12.6833 0.2625 13.9 0.7875C15.1167 1.3125 16.175 2.025 17.075 2.925C17.975 3.825 18.6875 4.88333 19.2125 6.1C19.7375 7.31667 20 8.61667 20 10C20 11.3833 19.7375 12.6833 19.2125 13.9C18.6875 15.1167 17.975 16.175 17.075 17.075C16.175 17.975 15.1167 18.6875 13.9 19.2125C12.6833 19.7375 11.3833 20 10 20Z" fill="#334155"/>
                        </svg>
                        <span>Take a quick tour</span>
                    </button>
                </div>

                <!-- checkbox "Don't show again" -->
                <div class="flex items-center gap-2 mt-8">
                    <!-- unchecked square (like PNG) -->
                    <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect x="0.5" y="0.5" width="17" height="17" rx="2.5" fill="white" stroke="#CBD5E1"/>
                    </svg>
                    <span class="text-sm text-[#64748B]">Don't show this screen again</span>
                </div>
            </div>
        </main>

        <!-- decorative background circles (as per original HTML, subtle) -->
        <div class="absolute bottom-0 left-0 right-0 h-24 flex justify-between items-end px-12 opacity-20 pointer-events-none">
            <div class="w-28 h-28 bg-[#0052CC]/20 rounded-full -mb-8"></div>
            <div class="w-64 h-64 bg-[#0052CC]/10 rounded-full -mb-16"></div>
            <div class="w-28 h-28 bg-[#0052CC]/20 rounded-full -mb-8"></div>
        </div>
    </div>
</body>
</html>


