<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Variation Type · BaaS</title>
    <!-- Tailwind + Inter font (clean) -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link href='https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap' rel='stylesheet'>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
<body class="min-h-screen flex items-center justify-center p-4 overflow-x-hidden font-[Inter]">

    <!-- semiâ€‘transparent overlay (mimics the popup background) -->
    <div class="fixed inset-0 bg-[#0F172A]/60 backdrop-blur-[2px] flex items-center justify-center p-4 z-50">
        
        <!-- popup card Â· 512px width, white, rounded-xl, shadow -->
        <div class="w-full max-w-[512px] bg-white rounded-xl shadow-2xl border border-[#E2E8F0] overflow-hidden">
            
            <!-- header with title and close icon -->
            <div class="flex justify-between items-center px-6 py-4 border-b border-[#F1F5F9]">
                <div>
                    <h3 class="text-lg font-poppins font-medium text-[#0F172A]">Add Variation Type</h3>
                    <p class="text-xs text-[#64748B] mt-0.5">Define how customers will differentiate your items</p>
                </div>
                <a href="{{ route('onboarding-Step2-AddProductVariations') }}" target="_top" class="text-[#94A3B8] hover:text-[#64748B]">
                    <svg width="14" height="14" viewBox="0 0 14 14" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M1.4 14L0 12.6L5.6 7L0 1.4L1.4 0L7 5.6L12.6 0L14 1.4L8.4 7L14 12.6L12.6 14L7 8.4L1.4 14Z" fill="currentColor"/>
                    </svg>
                </a>
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

            <form id="variation-popup-form" action="{{ route('onboarding_AddProduct_VariationsPopup.store') }}" method="POST" target="_top">
                @csrf

            <!-- body: category & variation name -->
            <div class="p-6 space-y-6">
                <!-- two column grid for category & name -->
                <div class="grid grid-cols-2 gap-4">
                    <!-- category dropdown -->
                    <div>
                        <label class="block text-sm font-semibold text-[#334155] mb-2">Input Type</label>
                        <div class="relative">
                            <select name="type" class="w-full appearance-none border border-[#E2E8F0] rounded-lg px-4 py-2.5 text-sm text-[#0F172A] bg-white focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                                <option value="select" @selected(old('type', 'select') === 'select')>Select</option>
                                <option value="radio" @selected(old('type') === 'radio')>Radio</option>
                                <option value="checkbox" @selected(old('type') === 'checkbox')>Checkbox</option>
                            </select>
                            <svg class="absolute right-3 top-1/2 -translate-y-1/2 w-5 h-5 text-[#6B7280]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                            </svg>
                        </div>
                    </div>
                    <!-- variation name input -->
                    <div>
                        <label class="block text-sm font-semibold text-[#334155] mb-2">Variation Name</label>
                        <input id="variationName" name="name" type="text" placeholder="e.g., Fabric" value="{{ old('name', 'Fabric') }}"
                               class="w-full border border-[#E2E8F0] rounded-lg px-4 py-2.5 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-[#334155] mb-2">Option Values</label>
                    <textarea id="variationOptions" name="options" rows="3" placeholder="S, M, L" class="w-full border border-[#E2E8F0] rounded-lg px-4 py-2.5 text-sm text-[#0F172A] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20">{{ old('options') }}</textarea>
                </div>

                <!-- Type-Specific Suggestions section -->
                <div class="space-y-3">
                    <div class="flex items-center gap-2 text-[#94A3B8]">
                        <svg width="15" height="15" viewBox="0 0 15 15" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 5.33333L11.1667 3.5L9.33333 2.66667L11.1667 1.83333L12 0L12.8333 1.83333L14.6667 2.66667L12.8333 3.5L12 5.33333ZM12 14.6667L11.1667 12.8333L9.33333 12L11.1667 11.1667L12 9.33333L12.8333 11.1667L14.6667 12L12.8333 12.8333L12 14.6667ZM5.33333 12.6667L3.66667 9L0 7.33333L3.66667 5.66667L5.33333 2L7 5.66667L10.6667 7.33333L7 9L5.33333 12.6667ZM5.33333 9.43333L6 8L7.43333 7.33333L6 6.66667L5.33333 5.23333L4.66667 6.66667L3.23333 7.33333L4.66667 8L5.33333 9.43333Z" fill="#94A3B8"/>
                        </svg>
                        <span class="text-xs font-bold uppercase tracking-[0.6px]">Typeâ€‘Specific Suggestions</span>
                    </div>

                    <div class="space-y-3">
                        <!-- Fashion -->
                        <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                            <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Fashion</div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Size</button>
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Color</button>
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Material</button>
                            </div>
                        </div>

                        <!-- Digital -->
                        <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                            <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Digital</div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">License Type</button>
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Resolution</button>
                            </div>
                        </div>

                        <!-- Food & Beverage -->
                        <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                            <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Food & Beverage</div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Add-ons</button>
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Dietary</button>
                            </div>
                        </div>

                        <!-- Subscriptions -->
                        <div class="bg-[#F8FAFC] border border-[#F1F5F9] rounded-lg p-4">
                            <div class="text-[#0052CC]/70 text-[10px] font-bold uppercase tracking-[1px] mb-2">Subscriptions</div>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Tier</button>
                                <button type="button" class="suggestion-btn bg-white border border-[#E2E8F0] rounded-md px-3 py-1.5 text-xs font-inter font-medium text-[#475569] shadow-sm">Billing Interval</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- footer: cancel + add variation button -->
            <div class="bg-[#F8FAFC] border-t border-[#F1F5F9] px-6 py-4 flex justify-end items-center gap-3">
                <a href="{{ route('onboarding-Step2-AddProductVariations') }}" target="_top" class="px-4 py-2 text-sm font-semibold text-[#475569] hover:text-[#1E293B]">Cancel</a>
                <button type="submit" class="bg-[#0052CC] text-white text-sm font-bold px-5 py-2 rounded-lg shadow-md shadow-[#0052CC]/20 flex items-center gap-2 hover:bg-[#0042a3] transition">
                    <span>Add Variation</span>
                    <svg width="10" height="10" viewBox="0 0 10 10" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4 5.33333H0V4H4V0H5.33333V4H9.33333V5.33333H5.33333V9.33333H4V5.33333Z" fill="white"/>
                    </svg>
                </button>
            </div>
            </form>
        </div>
    </div>

    <!-- subtle note: this is the popup only; in real app it would open from the product page -->
    <script>
        (() => {
            const nameInput = document.getElementById('variationName');
            const optionsInput = document.getElementById('variationOptions');
            document.querySelectorAll('.suggestion-btn').forEach((button) => {
                button.addEventListener('click', () => {
                    const value = button.textContent.trim();
                    nameInput.value = value;
                    if (!optionsInput.value.trim()) {
                        optionsInput.value = value;
                    }
                });
            });
        })();
    </script>
</body>
</html>
