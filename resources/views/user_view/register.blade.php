<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Register | BaaS Core</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@500;600;700&display=swap" rel="stylesheet" />
  @vite(['resources/css/app.css', 'resources/js/app.js'])

</head>
<body class="bg-white antialiased min-h-screen flex flex-col overflow-x-hidden font-[Inter]">
  <div class="flex-1 flex flex-col md:flex-row">
    <section class="w-full md:w-1/2 bg-white px-6 py-8 md:px-12 lg:px-16 xl:px-20 flex flex-col justify-center items-center">
      <div class="w-full max-w-[448px]">
        <a href="{{ route('signin') }}" class="inline-flex items-center gap-3 mb-10">
          <span class="h-10 w-10 rounded-lg bg-[#0052CC] grid place-items-center">
            <svg width="22" height="22" viewBox="0 0 22 22" fill="none" aria-hidden="true"><path d="M11 13L9 11L11 9L13 11L11 13ZM8.875 7.125L6.375 4.625L11 0L15.625 4.625L13.125 7.125L11 5L8.875 7.125ZM4.625 15.625L0 11L4.625 6.375L7.125 8.875L5 11L7.125 13.125L4.625 15.625ZM17.375 15.625L14.875 13.125L17 11L14.875 8.875L17.375 6.375L22 11L17.375 15.625ZM11 22L6.375 17.375L8.875 14.875L11 17L13.125 14.875L15.625 17.375L11 22Z" fill="white"/></svg>
          </span>
          <div class="flex flex-col">
            <span class="text-[#0F172A] text-xl font-bold leading-5">BaaS Core</span>
            <span class="text-[#94A3B8] text-[10px] font-bold uppercase tracking-[1px]">Infrastructure</span>
          </div>
        </a>

        <div class="mb-8">
          <h1 class="text-[#0F172A] text-3xl font-[Poppins] font-medium leading-9">Create your<br>workspace</h1>
          <p class="text-[#64748B] text-base leading-6 mt-1">Join the enterprise platform for modern e-commerce and multi-tenant services.</p>
        </div>

        <form class="space-y-5" method="GET" action="{{ route('dashboard') }}">
          <label class="block">
            <span class="mb-2 block text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Full Name</span>
            <input type="text" placeholder="Alex Rivers" class="h-12 w-full rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-4 text-sm placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" />
          </label>

          <label class="block">
            <span class="mb-2 block text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Work Email</span>
            <input type="email" placeholder="alex@company.com" class="h-12 w-full rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-4 text-sm placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" />
          </label>

          <label class="block">
            <span class="mb-2 block text-xs font-bold uppercase tracking-[1px] text-[#64748B]">Password</span>
            <div class="relative">
              <input type="password" placeholder="********" class="h-12 w-full rounded-lg border border-[#CBD5E1] bg-[#F8FAFC] px-4 pr-10 text-sm placeholder:text-[#94A3B8] focus:outline-none focus:ring-2 focus:ring-[#0052CC]/20" />
              <button type="button" class="absolute inset-y-0 right-3 text-[#94A3B8]" aria-label="Toggle password visibility">
                <svg width="17" height="12" viewBox="0 0 17 12" fill="none"><path d="M8.25 9C9.1875 9 9.98438 8.67188 10.6406 8.01562C11.2969 7.35938 11.625 6.5625 11.625 5.625C11.625 4.6875 11.2969 3.89062 10.6406 3.23438C9.98438 2.57812 9.1875 2.25 8.25 2.25C7.3125 2.25 6.51562 2.57812 5.85938 3.23438C5.20312 3.89062 4.875 4.6875 4.875 5.625C4.875 6.5625 5.20312 7.35938 5.85938 8.01562C6.51562 8.67188 7.3125 9 8.25 9ZM8.25 7.65C7.6875 7.65 7.20938 7.45312 6.81563 7.05937C6.42188 6.66562 6.225 6.1875 6.225 5.625C6.225 5.0625 6.42188 4.58438 6.81563 4.19063C7.20938 3.79688 7.6875 3.6 8.25 3.6C8.8125 3.6 9.29062 3.79688 9.68437 4.19063C10.0781 4.58438 10.275 5.0625 10.275 5.625C10.275 6.1875 10.0781 6.66562 9.68437 7.05937C9.29062 7.45312 8.8125 7.65 8.25 7.65ZM8.25 11.25C6.425 11.25 4.7625 10.7406 3.2625 9.72188C1.7625 8.70312 0.675 7.3375 0 5.625C0.675 3.9125 1.7625 2.54688 3.2625 1.52813C4.7625 0.509375 6.425 0 8.25 0C10.075 0 11.7375 0.509375 13.2375 1.52813C14.7375 2.54688 15.825 3.9125 16.5 5.625C15.825 7.3375 14.7375 8.70312 13.2375 9.72188C11.7375 10.7406 10.075 11.25 8.25 11.25Z" fill="currentColor"/></svg>
              </button>
            </div>
          </label>

          <label class="flex items-start gap-3 pt-1">
            <input type="checkbox" class="mt-0.5 h-4 w-4 rounded border-[#CBD5E1] text-[#0052CC] focus:ring-[#0052CC]" />
            <span class="text-sm leading-5 text-[#64748B]">By creating an account, you agree to our <a href="#" class="font-semibold text-[#0052CC]">Terms of Service</a> and <a href="#" class="font-semibold text-[#0052CC]">Privacy Policy</a>.</span>
          </label>

          <button type="submit" class="w-full h-12 rounded-lg bg-[#0052CC] text-white text-base font-bold shadow-[0_8px_16px_rgba(0,82,204,0.22)] hover:bg-[#0047B3] transition-colors">Create Your Workspace -></button>
        </form>

        <p class="mt-8 text-[#64748B] text-base">Already have an account? <a href="{{ route('signin') }}" class="font-bold text-[#0052CC]">Sign in</a></p>

      </div>
    </section>

    <section class="hidden md:flex w-full md:w-1/2 relative overflow-hidden bg-[#0F172A] p-6 md:p-10 lg:p-16 items-center">
      <div class="absolute inset-0 opacity-10">
        <div class="absolute -top-24 right-[-8%] h-[380px] w-[380px] rounded-full bg-[#0052CC] blur-[70px]"></div>
        <div class="absolute -bottom-20 left-[-6%] h-[260px] w-[260px] rounded-full bg-[#2563EB] blur-[60px]"></div>
      </div>

      <div class="relative z-10 max-w-[580px] mx-auto text-white space-y-10">
        <div>
          <span class="inline-flex rounded-full bg-[#0052CC]/25 px-3 py-1 text-[10px] font-bold uppercase tracking-[1px] text-[#1D78FF]">Built for scale</span>
          <h2 class="mt-6 text-4xl leading-[1.15]">Empowering the next generation of digital commerce.</h2>
          <p class="mt-6 text-[#94A3B8] text-xl leading-8">Our unified backend service handles the complexity, so you can focus on building world-class customer experiences.</p>
        </div>

        <div class="space-y-8">
          <div class="flex gap-5">
            <div class="h-12 w-12 rounded-xl border border-[#334155] bg-[#1E293B] grid place-items-center text-[#0052CC] shrink-0">
              <svg width="16" height="20" viewBox="0 0 16 20" fill="none"><path d="M4 20L5 13H0L9 0H11L10 8H16L6 20H4Z" fill="currentColor"/></svg>
            </div>
            <div>
              <h3 class="text-2xl font-semibold">Instant Setup</h3>
              <p class="mt-1 text-base leading-7 text-[#94A3B8]">Deploy a production-ready multi-tenant environment in seconds with automated environment provisioning.</p>
            </div>
          </div>

          <div class="flex gap-5">
            <div class="h-12 w-12 rounded-xl border border-[#334155] bg-[#1E293B] grid place-items-center text-[#0052CC] shrink-0">
              <svg width="20" height="18" viewBox="0 0 20 18" fill="none"><path d="M3 18C2.16667 18 1.45833 17.7083 0.875 17.125C0.291667 16.5417 0 15.8333 0 15C0 14.1667 0.291667 13.4583 0.875 12.875C1.45833 12.2917 2.16667 12 3 12H17C17.8333 12 18.5417 12.2917 19.125 12.875C19.7083 13.4583 20 14.1667 20 15C20 15.8333 19.7083 16.5417 19.125 17.125C18.5417 17.7083 17.8333 18 17 18H3ZM8 10C7.71667 10 7.47917 9.90417 7.2875 9.7125C7.09583 9.52083 7 9.28333 7 9V1C7 0.716667 7.09583 0.479167 7.2875 0.2875C7.47917 0.0958333 7.71667 0 8 0H16C16.2833 0 16.5208 0.0958333 16.7125 0.2875C16.9042 0.479167 17 0.716667 17 1V9C17 9.28333 16.9042 9.52083 16.7125 9.7125C16.5208 9.90417 16.2833 10 16 10H8Z" fill="currentColor"/></svg>
            </div>
            <div>
              <h3 class="text-2xl font-[Poppins] font-medium">Automated Logistics</h3>
              <p class="mt-1 text-base leading-7 text-[#94A3B8]">Seamlessly integrate with major fulfillment providers. Real-time tracking and automated route optimization built-in.</p>
            </div>
          </div>

          <div class="flex gap-5">
            <div class="h-12 w-12 rounded-xl border border-[#334155] bg-[#1E293B] grid place-items-center text-[#0052CC] shrink-0">
              <svg width="19" height="20" viewBox="0 0 19 20" fill="none"><path d="M3.5 9L9 0L14.5 9H3.5ZM14.5 20C13.25 20 12.1875 19.5625 11.3125 18.6875C10.4375 17.8125 10 16.75 10 15.5C10 14.25 10.4375 13.1875 11.3125 12.3125C12.1875 11.4375 13.25 11 14.5 11C15.75 11 16.8125 11.4375 17.6875 12.3125C18.5625 13.1875 19 14.25 19 15.5C19 16.75 18.5625 17.8125 17.6875 18.6875C16.8125 19.5625 15.75 20 14.5 20ZM0 19.5V11.5H8V19.5H0Z" fill="currentColor"/></svg>
            </div>
            <div>
              <h3 class="text-2xl font-semibold">Multi-category support</h3>
              <p class="mt-1 text-base leading-7 text-[#94A3B8]">From digital products to physical goods and professional services. One platform to manage all your business verticals.</p>
            </div>
          </div>
        </div>

        <div class="rounded-2xl border border-[#334155] bg-[#1E293B]/50 p-5 backdrop-blur-sm">
          <div class="flex items-center gap-3">
            <div class="flex items-center">
              <span class="h-8 w-8 rounded-full border-2 border-[#1E293B] bg-[#F5D8BE]"></span>
              <span class="-ml-2 h-8 rounded-full border-2 border-[#1E293B] bg-[#334155] px-2 text-[10px] font-bold text-white inline-flex items-center">+5k</span>
            </div>
            <p class="text-xs text-[#CBD5E1]">"The most robust multi-tenant engine we've integrated."</p>
          </div>
          <div class="mt-4 flex items-center justify-between">
            <div class="text-[#EAB308] text-xs tracking-[2px]">*****</div>
            <span class="text-[10px] uppercase tracking-[1px] text-[#64748B] font-bold">Enterprise Verified</span>
          </div>
        </div>
      </div>
    </section>
  </div>

  <footer class="border-t border-gray-200 py-5 px-4 text-center text-sm text-[#94A3B8] font-medium">
    <div class="max-w-7xl mx-auto flex flex-wrap justify-center items-center gap-x-4 gap-y-1">
      <span>&copy; 2024 BaaS Core Enterprise. All rights reserved.</span>
    </div>
  </footer>
</body>
</html>
