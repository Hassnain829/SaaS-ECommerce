<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - BaaS Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-white antialiased min-h-screen flex flex-col overflow-x-hidden font-[Inter]">
    <div class="flex-1 flex flex-col md:flex-row">
        <div class="w-full md:w-1/2 bg-white px-6 py-8 md:px-12 lg:px-16 xl:px-20 flex flex-col justify-center items-center">
            <div class="w-full max-w-[448px]">
                <div class="flex items-center gap-3 mb-8 md:mb-10">
                    <div class="bg-[#0052CC] p-2.5 rounded-lg flex items-center justify-center">
                        <svg width="22" height="22" viewBox="0 0 22 22" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M11 13L9 11L11 9L13 11L11 13V13M8.875 7.125L6.375 4.625L11 0L15.625 4.625L13.125 7.125L11 5L8.875 7.125V7.125M4.625 15.625L0 11L4.625 6.375L7.125 8.875L5 11L7.125 13.125L4.625 15.625V15.625M17.375 15.625L14.875 13.125L17 11L14.875 8.875L17.375 6.375L22 11L17.375 15.625V15.625M11 22L6.375 17.375L8.875 14.875L11 17L13.125 14.875L15.625 17.375L11 22V22" fill="white"/>
                        </svg>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[#0F172A] text-xl font-bold leading-5">BaaS Core</span>
                        <span class="text-[#94A3B8] text-[10px] font-bold uppercase tracking-[1px]">Infrastructure</span>
                    </div>
                </div>

                <div class="mb-8">
                    <h1 class="text-[#0F172A] text-3xl font-[Poppins] font-medium leading-9">Welcome back</h1>
                    <p class="text-[#64748B] text-base leading-6 mt-1">Enter your credentials to access your marketplace dashboard.</p>
                </div>

                <form class="space-y-6" method="POST" action="{{ route('signin.attempt') }}">
                    @csrf

                    @if ($errors->any())
                        <div class="rounded-xl border border-[#FFDAD6] bg-[#FFF6F5] p-3 text-sm text-[#BA1A1A]">
                            {{ $errors->first() }}
                        </div>
                    @endif
                    <div class="space-y-2">
                        <label class="block text-sm font-semibold text-[#334155]">Email Address</label>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                <svg width="17" height="28" viewBox="0 0 17 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1.66667 13.3333C1.20833 13.3333 0.815972 13.1701 0.489583 12.8438C0.163194 12.5174 0 12.125 0 11.6667V1.66667C0 1.20833 0.163194 0.815972 0.489583 0.489583C0.815972 0.163194 1.20833 0 1.66667 0H15C15.4583 0 15.8507 0.163194 16.1771 0.489583C16.5035 0.815972 16.6667 1.20833 16.6667 1.66667V11.6667C16.6667 12.125 16.5035 12.5174 16.1771 12.8438C15.8507 13.1701 15.4583 13.3333 15 13.3333H1.66667V13.3333M8.33333 7.5L1.66667 3.33333V11.6667H15V3.33333L8.33333 7.5ZM8.33333 5.83333L15 1.66667H1.66667L8.33333 5.83333ZM1.66667 3.33333V1.66667V11.6667V3.33333Z" fill="#94A3B8"/>
                                </svg>
                            </span>
                            <input type="email" name="email" value="{{ old('email') }}" placeholder="name@company.com" class="w-full pl-11 pr-4 py-3.5 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl text-sm text-[#334155] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20 focus:border-[#0052CC] transition">
                        </div>
                    </div>

                    <div class="space-y-2">
                        <div class="flex justify-between items-center">
                            <label class="text-sm font-semibold text-[#334155]">Password</label>
                            <a href="#" class="text-xs font-bold text-[#0052CC] hover:underline">Forgot password?</a>
                        </div>
                        <div class="relative">
                            <span class="absolute inset-y-0 left-3 flex items-center pointer-events-none">
                                <svg width="14" height="28" viewBox="0 0 14 28" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M1.66667 17.5C1.20833 17.5 0.815972 17.3368 0.489583 17.0104C0.163194 16.684 0 16.2917 0 15.8333V7.5C0 7.04167 0.163194 6.64931 0.489583 6.32292C0.815972 5.99653 1.20833 5.83333 1.66667 5.83333H2.5V4.16667C2.5 3.01389 2.90625 2.03125 3.71875 1.21875C4.53125 0.40625 5.51389 0 6.66667 0C7.81944 0 8.80208 0.40625 9.61458 1.21875C10.4271 2.03125 10.8333 3.01389 10.8333 4.16667V5.83333H11.6667C12.125 5.83333 12.5174 5.99653 12.8438 6.32292C13.1701 6.64931 13.3333 7.04167 13.3333 7.5V15.8333C13.3333 16.2917 13.1701 16.684 12.8438 17.0104C12.5174 17.3368 12.125 17.5 11.6667 17.5H1.66667ZM1.66667 15.8333H11.6667V7.5H1.66667V15.8333ZM6.66667 13.3333C7.125 13.3333 7.51736 13.1701 7.84375 12.8438C8.17014 12.5174 8.33333 12.125 8.33333 11.6667C8.33333 11.2083 8.17014 10.816 7.84375 10.4896C7.51736 10.1632 7.125 10 6.66667 10C6.20833 10 5.81597 10.1632 5.48958 10.4896C5.16319 10.816 5 11.2083 5 11.6667C5 12.125 5.16319 12.5174 5.48958 12.8438C5.81597 13.1701 6.20833 13.3333 6.66667 13.3333ZM4.16667 5.83333H9.16667V4.16667C9.16667 3.47222 8.92361 2.88194 8.4375 2.39583C7.95139 1.90972 7.36111 1.66667 6.66667 1.66667C5.97222 1.66667 5.38194 1.90972 4.89583 2.39583C4.40972 2.88194 4.16667 3.47222 4.16667 4.16667V5.83333Z" fill="#94A3B8"/>
                                </svg>
                            </span>
                            <input type="password" name="password" placeholder="********" class="w-full pl-11 pr-4 py-3.5 bg-[#F8FAFC] border border-[#E2E8F0] rounded-xl text-sm text-[#334155] placeholder:text-[#6B7280] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20 focus:border-[#0052CC] transition">
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <input type="checkbox" id="keep-signed" name="remember" class="w-4 h-4 rounded border-[#CBD5E1] text-[#0052CC] focus:ring-[#0052CC]">
                        <label for="keep-signed" class="text-sm text-[#475569]">Keep me signed in</label>
                    </div>

                    <button type="submit" class="w-full bg-[#0052CC] text-white font-bold py-4 px-4 rounded-xl text-base shadow-[0_10px_15px_-3px_rgba(0,82,204,0.20),0_4px_6px_-4px_rgba(0,82,204,0.20)] hover:bg-[#0042a3] transition-colors">Sign In</button>

                    <div class="flex justify-center items-center gap-1 pt-6 border-t border-[#F1F5F9]">
                        <span class="text-sm text-[#64748B]">Don't have an account?</span>
                        <a href="{{ route('register') }}" class="text-sm font-bold text-[#0052CC] hover:underline">Sign up</a>
                    </div>
                </form>
            </div>
        </div>

        <div class="hidden md:flex w-full md:w-1/2 bg-[#F4F7FA] relative items-center justify-center p-6 md:p-10 lg:p-16 overflow-hidden">
            <div class="absolute inset-0 opacity-40" style="background: radial-gradient(ellipse 70.71% 70.71% at 50% 50%, #0052CC 1%, rgba(0,82,204,0) 70%);"></div>
            <div class="absolute w-[500px] h-[500px] -top-20 right-10 bg-[#0052CC]/5 rounded-full blur-3xl"></div>
            <div class="absolute w-[400px] h-[400px] -bottom-20 -left-20 bg-[#0052CC]/5 rounded-full blur-3xl"></div>

            <div class="relative z-10 max-w-[672px] flex flex-col items-center text-center gap-8">
                <div class="inline-flex items-center gap-2 bg-white/80 backdrop-blur-sm border border-[#0052CC]/10 rounded-full px-4 py-2 shadow-sm">
                    <span class="w-2 h-2 bg-[#0052CC] rounded-full"></span>
                    <span class="text-[#0052CC] text-xs font-bold uppercase tracking-[0.3px]">Scalable Infrastructure</span>
                </div>

                <h2 class="text-4xl md:text-5xl font-bold leading-tight text-[#0F172A]">
                    Powering the next<br>generation of <span class="text-[#0052CC]">multi-tenant</span><br><span class="text-[#0052CC]">marketplaces</span>.
                </h2>

                <p class="text-[#475569] text-lg md:text-xl leading-relaxed max-w-[594px]">
                    Standardize your e-commerce operations with a unified core<br>designed for massive scale and complex service architectures.
                </p>

                <div class="flex flex-wrap justify-center gap-6 mt-4">
                    <div class="bg-white rounded-2xl border border-[#F1F5F9] p-6 w-[186px] shadow-[0_20px_25px_-5px_rgba(226,232,240,0.5),0_8px_10px_-6px_rgba(226,232,240,0.5)]">
                        <div class="text-[#0052CC] text-2xl font-bold">99.9%</div>
                        <div class="text-[#94A3B8] text-[10px] font-bold uppercase tracking-wider mt-1">UPTIME SLA</div>
                    </div>
                    <div class="bg-white rounded-2xl border border-[#F1F5F9] p-6 w-[186px] shadow-[0_20px_25px_-5px_rgba(226,232,240,0.5),0_8px_10px_-6px_rgba(226,232,240,0.5)]">
                        <div class="text-[#0052CC] text-2xl font-bold">200ms</div>
                        <div class="text-[#94A3B8] text-[10px] font-bold uppercase tracking-wider mt-1">API LATENCY</div>
                    </div>
                    <div class="bg-white rounded-2xl border border-[#F1F5F9] p-6 w-[186px] shadow-[0_20px_25px_-5px_rgba(226,232,240,0.5),0_8px_10px_-6px_rgba(226,232,240,0.5)]">
                        <div class="text-[#0052CC] text-2xl font-bold">10k+</div>
                        <div class="text-[#94A3B8] text-[10px] font-bold uppercase tracking-wider mt-1">ACTIVE NODES</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <footer class="border-t border-gray-200 py-5 px-4 text-center text-sm text-[#94A3B8] font-medium">
        <div class="max-w-7xl mx-auto flex flex-wrap justify-center items-center gap-x-4 gap-y-1">
            <span>&copy; 2024 BaaS Core Systems</span>
            <span class="hidden sm:inline">&middot;</span>
            <a href="#" class="hover:text-[#64748B] transition">Privacy Policy</a>
            <span class="hidden sm:inline">&middot;</span>
            <a href="#" class="hover:text-[#64748B] transition">Terms of Service</a>
        </div>
    </footer>
</body>
</html>

