@php
    $googleSignInEnabled = app(\App\Services\GoogleSignInService::class)->isEnabled();
@endphp
@if ($googleSignInEnabled)
    <a
        href="{{ route('auth.google.redirect') }}"
        class="mb-6 flex h-12 w-full items-center justify-center gap-3 rounded-xl border border-[#E2E8F0] bg-white text-sm font-semibold text-[#0F172A] transition hover:bg-[#F8FAFC]"
    >
        <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
            <path fill="#4285F4" d="M17.64 9.2c0-.64-.06-1.25-.16-1.84H9v3.48h4.84a4.14 4.14 0 0 1-1.8 2.72v2.26h2.92c1.7-1.57 2.68-3.88 2.68-6.62Z"/>
            <path fill="#34A853" d="M9 18c2.43 0 4.47-.8 5.96-2.18l-2.92-2.26c-.8.54-1.84.86-3.04.86-2.34 0-4.32-1.58-5.03-3.71H.96v2.33A9 9 0 0 0 9 18Z"/>
            <path fill="#FBBC05" d="M3.97 10.71A5.41 5.41 0 0 1 3.68 9c0-.59.1-1.17.26-1.71V4.96H.96A9 9 0 0 0 0 9c0 1.45.35 2.82.96 4.04l3.01-2.33Z"/>
            <path fill="#EA4335" d="M9 3.58c1.32 0 2.5.45 3.44 1.35l2.58-2.58C13.46.89 11.43 0 9 0A9 9 0 0 0 .96 4.96l3.01 2.33C4.68 5.16 6.66 3.58 9 3.58Z"/>
        </svg>
        Continue with Google
    </a>
    <div class="mb-6 flex items-center gap-3 text-xs font-semibold uppercase tracking-[0.12em] text-[#94A3B8]">
        <span class="h-px flex-1 bg-[#E2E8F0]"></span>
        or
        <span class="h-px flex-1 bg-[#E2E8F0]"></span>
    </div>
@endif
